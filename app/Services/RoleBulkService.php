<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Repositories\UserRepository;

final class RoleBulkService
{
    private const MAX_TARGETS = 500;

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function preview(string $action, string $selectionMode, array $selectedIds, array $filters, array $payload): array
    {
        $roles = $this->resolveRoles($selectionMode, $selectedIds, $filters);
        if ($roles === []) {
            throw new ValidationException(['Keine passenden Rollen fuer die Bulk-Aktion gefunden.']);
        }

        $items = [];
        $resolvedIds = [];
        $preparedPayload = [];
        $payloadLines = [];
        $warnings = [];

        if (in_array($action, ['add_permissions', 'remove_permissions'], true)) {
            $permissionNames = $this->normalizePermissionNames((array) ($payload['permission_names'] ?? []));
            if ($permissionNames === []) {
                throw new ValidationException(['Bitte mindestens eine Berechtigung fuer die Bulk-Aktion auswaehlen.']);
            }

            $preparedPayload = ['permission_names' => $permissionNames];
            $payloadLines[] = 'Berechtigungen: ' . implode(', ', $permissionNames);

            foreach ($roles as $role) {
                $rolePermissionNames = $role['permission_names'] ?? [];
                if ($action === 'add_permissions') {
                    $missing = array_values(array_diff($permissionNames, $rolePermissionNames));
                    if ($missing === []) {
                        $items[] = $this->previewItem($role, 'skip', 'Rolle hat diese Berechtigungen bereits.');
                        continue;
                    }

                    $items[] = $this->previewItem($role, 'ready', 'Berechtigungen werden hinzugefuegt.');
                    $resolvedIds[] = (int) $role['id'];
                } else {
                    $existing = array_values(array_intersect($permissionNames, $rolePermissionNames));
                    if ($existing === []) {
                        $items[] = $this->previewItem($role, 'skip', 'Rolle hat keine der ausgewaehlten Berechtigungen.');
                        continue;
                    }

                    $items[] = $this->previewItem($role, 'ready', 'Berechtigungen werden entfernt.');
                    $resolvedIds[] = (int) $role['id'];
                }
            }
        } elseif ($action === 'delete_roles') {
            foreach ($roles as $role) {
                if (!empty($role['is_system'])) {
                    $items[] = $this->previewItem($role, 'skip', 'Systemrollen koennen nicht geloescht werden.');
                    continue;
                }

                $items[] = $this->previewItem($role, 'ready', 'Rolle wird geloescht.');
                $resolvedIds[] = (int) $role['id'];
            }
            $warnings[] = 'Beim Loeschen werden Benutzer- und Einladungszuweisungen der Rolle mit entfernt.';
        } else {
            throw new ValidationException(['Unbekannte Bulk-Aktion fuer Rollen.']);
        }

        return [
            'module' => 'roles',
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

    public function execute(array $snapshot): array
    {
        $action = (string) ($snapshot['action'] ?? '');
        $resolvedIds = array_values(array_map('intval', (array) ($snapshot['resolved_ids'] ?? [])));
        $payload = (array) ($snapshot['payload'] ?? []);

        return match ($action) {
            'add_permissions' => $this->executePermissionChange($resolvedIds, (array) ($payload['permission_names'] ?? []), true),
            'remove_permissions' => $this->executePermissionChange($resolvedIds, (array) ($payload['permission_names'] ?? []), false),
            'delete_roles' => $this->executeDeleteRoles($resolvedIds),
            default => throw new ValidationException(['Unbekannte Bulk-Aktion fuer Rollen.']),
        };
    }

    private function executePermissionChange(array $roleIds, array $permissionNames, bool $add): array
    {
        $permissionNames = $this->normalizePermissionNames($permissionNames);
        $roles = $this->users->findRolesByIds($roleIds);
        $actionableIds = [];

        foreach ($roles as $role) {
            $rolePermissionNames = $role['permission_names'] ?? [];
            $matches = $add
                ? array_diff($permissionNames, $rolePermissionNames)
                : array_intersect($permissionNames, $rolePermissionNames);

            if ($matches !== []) {
                $actionableIds[] = (int) $role['id'];
            }
        }

        if ($add) {
            $this->users->addPermissionsToRoles($actionableIds, $permissionNames);
        } else {
            $this->users->removePermissionsFromRoles($actionableIds, $permissionNames);
        }

        $skipped = max(0, count($roleIds) - count($actionableIds));
        return [
            'processed' => count($actionableIds),
            'skipped' => $skipped,
            'failed' => 0,
            'warnings' => $skipped > 0 ? ['Mindestens eine Rolle hatte bei der Ausfuehrung nichts mehr zu aendern.'] : [],
        ];
    }

    private function executeDeleteRoles(array $roleIds): array
    {
        $roles = $this->users->findRolesByIds($roleIds);
        $actionableIds = [];
        $skipped = 0;

        foreach ($roles as $role) {
            if (!empty($role['is_system'])) {
                $skipped++;
                continue;
            }

            $actionableIds[] = (int) $role['id'];
        }

        $processed = $this->users->deleteRoles($actionableIds);
        $skipped += max(0, count($roleIds) - count($roles));

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => 0,
            'warnings' => $skipped > 0 ? ['Systemrollen oder bereits entfernte Rollen wurden ausgelassen.'] : [],
        ];
    }

    private function resolveRoles(string $selectionMode, array $selectedIds, array $filters): array
    {
        if ($selectionMode === 'filtered') {
            $roles = $this->users->listRoles($filters);
        } elseif ($selectionMode === 'ids') {
            $roles = $this->users->findRolesByIds($selectedIds);
        } else {
            throw new ValidationException(['Die Auswahlart fuer die Bulk-Aktion ist ungueltig.']);
        }

        if (count($roles) > self::MAX_TARGETS) {
            throw new ValidationException(['Es duerfen hoechstens 500 Rollen auf einmal verarbeitet werden.']);
        }

        return $roles;
    }

    private function normalizePermissionNames(array $permissionNames): array
    {
        $permissionNames = array_values(array_unique(array_filter(array_map(
            static fn (mixed $name): string => trim((string) $name),
            $permissionNames
        ))));

        $validNames = array_map(
            static fn (array $permission): string => $permission['name'],
            $this->users->listPermissions()
        );

        return array_values(array_intersect($permissionNames, $validNames));
    }

    private function previewItem(array $role, string $status, string $message): array
    {
        return [
            'id' => (int) $role['id'],
            'label' => (string) $role['name'],
            'detail' => !empty($role['is_system']) ? 'Systemrolle' : 'Benutzerrolle',
            'status' => $status,
            'message' => $message,
        ];
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'add_permissions' => 'Berechtigungen zu Rollen hinzufuegen',
            'remove_permissions' => 'Berechtigungen von Rollen entfernen',
            'delete_roles' => 'Rollen loeschen',
            default => 'Bulk-Aktion',
        };
    }
}
