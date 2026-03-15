<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Repositories\WatchRepository;

final class WatchedBulkService
{
    private const MAX_TARGETS = 500;

    public function __construct(private readonly WatchRepository $watch)
    {
    }

    public function preview(string $action, string $selectionMode, array $selectedIds, array $filters, int $userId): array
    {
        if ($action !== 'delete_events') {
            throw new ValidationException(['Unbekannte Bulk-Aktion fuer die Watched-List.']);
        }

        $events = $this->resolveEvents($selectionMode, $selectedIds, $filters, $userId);
        if ($events === []) {
            throw new ValidationException(['Keine passenden Watch-Events fuer die Bulk-Aktion gefunden.']);
        }

        $items = [];
        $resolvedIds = [];
        foreach ($events as $event) {
            $items[] = [
                'id' => (int) $event['id'],
                'label' => $this->eventLabel($event),
                'detail' => (string) $event['watched_at'],
                'status' => 'ready',
                'message' => 'Das Watch-Event wird entfernt.',
            ];
            $resolvedIds[] = (int) $event['id'];
        }

        return [
            'module' => 'watched',
            'action' => $action,
            'action_label' => 'Watch-Events loeschen',
            'resolved_ids' => $resolvedIds,
            'payload' => [],
            'payload_lines' => [],
            'items' => $items,
            'summary' => [
                'affected' => count($items),
                'executable' => count($resolvedIds),
                'skipped' => 0,
            ],
            'warnings' => [],
        ];
    }

    public function execute(array $snapshot, int $userId): array
    {
        $resolvedIds = array_values(array_map('intval', (array) ($snapshot['resolved_ids'] ?? [])));
        $existing = $this->watch->findEventsByIds($resolvedIds, $userId);
        $existingIds = array_map(static fn (array $event): int => (int) $event['id'], $existing);
        $processed = $this->watch->deleteEvents($existingIds, $userId);
        $skipped = max(0, count($resolvedIds) - count($existingIds));

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => 0,
            'warnings' => $skipped > 0 ? ['Mindestens ein Watch-Event war vor der Ausfuehrung bereits nicht mehr vorhanden.'] : [],
        ];
    }

    private function resolveEvents(string $selectionMode, array $selectedIds, array $filters, int $userId): array
    {
        if ($selectionMode === 'filtered') {
            $events = $this->watch->listEvents($userId, $filters);
        } elseif ($selectionMode === 'ids') {
            $events = $this->watch->findEventsByIds($selectedIds, $userId);
        } else {
            throw new ValidationException(['Die Auswahlart fuer die Bulk-Aktion ist ungueltig.']);
        }

        if (count($events) > self::MAX_TARGETS) {
            throw new ValidationException(['Es duerfen hoechstens 500 Watch-Events auf einmal verarbeitet werden.']);
        }

        return $events;
    }

    private function eventLabel(array $event): string
    {
        if (($event['kind'] ?? 'movie') === 'season') {
            return sprintf(
                '%s - Staffel %s',
                $event['series_title'] ?: $event['title'],
                $event['season_number'] ?: '?'
            );
        }

        return (string) $event['title'];
    }
}
