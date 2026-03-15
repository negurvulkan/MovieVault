<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Repositories\UserRepository;

final class InvitationBulkService
{
    private const MAX_TARGETS = 500;

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function preview(string $action, string $selectionMode, array $selectedIds, array $filters): array
    {
        if ($action !== 'revoke_invitations') {
            throw new ValidationException(['Unbekannte Bulk-Aktion fuer Einladungen.']);
        }

        $invitations = $this->resolveInvitations($selectionMode, $selectedIds, $filters);
        if ($invitations === []) {
            throw new ValidationException(['Keine passenden Einladungen fuer die Bulk-Aktion gefunden.']);
        }

        $items = [];
        $resolvedIds = [];

        foreach ($invitations as $invitation) {
            if (!($invitation['is_open'] ?? false)) {
                $items[] = [
                    'id' => (int) $invitation['id'],
                    'label' => (string) $invitation['email'],
                    'detail' => sprintf('Gueltig bis %s', (string) $invitation['expires_at']),
                    'status' => 'skip',
                    'message' => $invitation['accepted_at'] ? 'Einladung wurde bereits angenommen.' : 'Einladung ist nicht mehr offen.',
                ];
                continue;
            }

            $items[] = [
                'id' => (int) $invitation['id'],
                'label' => (string) $invitation['email'],
                'detail' => sprintf('Gueltig bis %s', (string) $invitation['expires_at']),
                'status' => 'ready',
                'message' => 'Einladung wird widerrufen.',
            ];
            $resolvedIds[] = (int) $invitation['id'];
        }

        return [
            'module' => 'invitations',
            'action' => $action,
            'action_label' => 'Einladungen widerrufen',
            'resolved_ids' => $resolvedIds,
            'payload' => [],
            'payload_lines' => [],
            'items' => $items,
            'summary' => [
                'affected' => count($items),
                'executable' => count($resolvedIds),
                'skipped' => max(0, count($items) - count($resolvedIds)),
            ],
            'warnings' => [],
        ];
    }

    public function execute(array $snapshot): array
    {
        $resolvedIds = array_values(array_map('intval', (array) ($snapshot['resolved_ids'] ?? [])));
        $processed = $this->users->revokeInvitations($resolvedIds);
        $skipped = max(0, count($resolvedIds) - $processed);

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => 0,
            'warnings' => $skipped > 0 ? ['Mindestens eine Einladung war vor der Ausfuehrung nicht mehr offen.'] : [],
        ];
    }

    private function resolveInvitations(string $selectionMode, array $selectedIds, array $filters): array
    {
        if ($selectionMode === 'filtered') {
            $invitations = $this->users->listInvitations($filters);
        } elseif ($selectionMode === 'ids') {
            $invitations = $this->users->findInvitationsByIds($selectedIds);
        } else {
            throw new ValidationException(['Die Auswahlart fuer die Bulk-Aktion ist ungueltig.']);
        }

        if (count($invitations) > self::MAX_TARGETS) {
            throw new ValidationException(['Es duerfen hoechstens 500 Einladungen auf einmal verarbeitet werden.']);
        }

        return $invitations;
    }
}
