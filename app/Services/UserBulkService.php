<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Repositories\UserRepository;

final class UserBulkService
{
    private const MAX_TARGETS = 500;

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function preview(
        string $action,
        string $selectionMode,
        array $selectedIds,
        array $filters,
        array $payload,
        int $currentUserId
    ): array {
        $userList = $this->resolveUsers($selectionMode, $selectedIds, $filters);
        if ($userList === []) {
            throw new ValidationException(['Keine passenden Benutzer fuer die Bulk-Aktion gefunden.']);
        }

        $items = [];
        $resolvedIds = [];
        $preparedPayload = [];
        $payloadLines = [];
        $warnings = [];
        $role = null;

        if (in_array($action, ['add_role', 'remove_role'], true)) {
            $roleId = (int) ($payload['role_id'] ?? 0);
            $role = $this->users->findRolesByIds([$roleId])[0] ?? null;
            if (!$role) {
                throw new ValidationException(['Bitte eine gueltige Rolle fuer die Bulk-Aktion auswaehlen.']);
            }

            $preparedPayload = ['role_id' => (int) $role['id']];
            $payloadLines[] = 'Rolle: ' . $role['name'];
        }

        foreach ($userList as $user) {
            $status = 'ready';
            $message = '';

            if ($action === 'activate_users') {
                if ((int) $user['is_active'] === 1) {
                    $status = 'skip';
                    $message = 'Benutzer ist bereits aktiv.';
                } else {
                    $message = 'Benutzer wird aktiviert.';
                }
            } elseif ($action === 'deactivate_users') {
                if ((int) $user['id'] === $currentUserId) {
                    $status = 'skip';
                    $message = 'Der aktuell eingeloggte Benutzer kann nicht deaktiviert werden.';
                } elseif ((int) $user['is_active'] === 0) {
                    $status = 'skip';
                    $message = 'Benutzer ist bereits inaktiv.';
                } else {
                    $message = 'Benutzer wird deaktiviert.';
                }
            } elseif ($action === 'delete_users') {
                if ((int) $user['id'] === $currentUserId) {
                    $status = 'skip';
                    $message = 'Der aktuell eingeloggte Benutzer kann nicht geloescht werden.';
                } else {
                    $message = 'Benutzer wird geloescht.';
                }
            } elseif ($action === 'add_role') {
                if (in_array((int) $role['id'], $user['role_ids'] ?? [], true)) {
                    $status = 'skip';
                    $message = 'Benutzer hat diese Rolle bereits.';
                } else {
                    $message = 'Rolle wird hinzugefuegt.';
                }
            } elseif ($action === 'remove_role') {
                if (!in_array((int) $role['id'], $user['role_ids'] ?? [], true)) {
                    $status = 'skip';
                    $message = 'Benutzer hat diese Rolle nicht.';
                } else {
                    $message = 'Rolle wird entfernt.';
                }
            } else {
                throw new ValidationException(['Unbekannte Bulk-Aktion fuer Benutzer.']);
            }

            $items[] = [
                'id' => (int) $user['id'],
                'label' => (string) $user['display_name'],
                'detail' => (string) $user['email'],
                'status' => $status,
                'message' => $message,
            ];

            if ($status === 'ready') {
                $resolvedIds[] = (int) $user['id'];
            }
        }

        if ($action === 'delete_users') {
            $warnings[] = 'Beim Loeschen werden Rollenverknuepfungen und Watch-Events des Benutzers mit entfernt.';
        }

        return [
            'module' => 'users',
            'action' => $action,
            'action_label' => $this->actionLabel($action),
            'resolved_ids' => $resolvedIds,
            'payload' => $preparedPayload,
            'payload_lines' => $payloadLines,
            'items' => $items,
            'summary' => [
                'affected' => count($items),
                'executable' => count($resolvedIds),
                'skipped' => max(0, count($items) - count($resolvedIds)),
            ],
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    public function execute(array $snapshot, int $currentUserId): array
    {
        $action = (string) ($snapshot['action'] ?? '');
        $resolvedIds = array_values(array_map('intval', (array) ($snapshot['resolved_ids'] ?? [])));
        $payload = (array) ($snapshot['payload'] ?? []);

        return match ($action) {
            'activate_users' => $this->result($this->users->setUsersActive($resolvedIds, true), 0, 0, []),
            'deactivate_users' => $this->executeDeactivate($resolvedIds, $currentUserId),
            'delete_users' => $this->executeDelete($resolvedIds, $currentUserId),
            'add_role' => $this->result($this->users->addRoleToUsers($resolvedIds, (int) ($payload['role_id'] ?? 0)), 0, 0, []),
            'remove_role' => $this->result($this->users->removeRoleFromUsers($resolvedIds, (int) ($payload['role_id'] ?? 0)), 0, 0, []),
            default => throw new ValidationException(['Unbekannte Bulk-Aktion fuer Benutzer.']),
        };
    }

    private function executeDeactivate(array $userIds, int $currentUserId): array
    {
        $safeIds = array_values(array_filter($userIds, static fn (int $id): bool => $id !== $currentUserId));
        $processed = $this->users->setUsersActive($safeIds, false);
        $skipped = count($userIds) - count($safeIds);
        $warnings = $skipped > 0 ? ['Der aktuell eingeloggte Benutzer wurde beim Deaktivieren ausgelassen.'] : [];

        return $this->result($processed, $skipped, 0, $warnings);
    }

    private function executeDelete(array $userIds, int $currentUserId): array
    {
        $safeIds = array_values(array_filter($userIds, static fn (int $id): bool => $id !== $currentUserId));
        $processed = $this->users->deleteUsers($safeIds);
        $skipped = count($userIds) - count($safeIds);
        $warnings = $skipped > 0 ? ['Der aktuell eingeloggte Benutzer wurde beim Loeschen ausgelassen.'] : [];

        return $this->result($processed, $skipped, 0, $warnings);
    }

    private function resolveUsers(string $selectionMode, array $selectedIds, array $filters): array
    {
        if ($selectionMode === 'filtered') {
            $userList = $this->users->listUsers($filters);
        } elseif ($selectionMode === 'ids') {
            $userList = $this->users->findUsersByIds($selectedIds);
        } else {
            throw new ValidationException(['Die Auswahlart fuer die Bulk-Aktion ist ungueltig.']);
        }

        if (count($userList) > self::MAX_TARGETS) {
            throw new ValidationException(['Es duerfen hoechstens 500 Benutzer auf einmal verarbeitet werden.']);
        }

        return $userList;
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'activate_users' => 'Benutzer aktivieren',
            'deactivate_users' => 'Benutzer deaktivieren',
            'delete_users' => 'Benutzer loeschen',
            'add_role' => 'Rolle zu Benutzern hinzufuegen',
            'remove_role' => 'Rolle von Benutzern entfernen',
            default => 'Bulk-Aktion',
        };
    }

    private function result(int $processed, int $skipped, int $failed, array $warnings): array
    {
        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }
}
