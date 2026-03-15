<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Repositories\CatalogRepository;

final class SeriesBulkService
{
    private const MAX_TARGETS = 500;

    public function __construct(private readonly CatalogRepository $catalog)
    {
    }

    public function preview(string $action, string $selectionMode, array $selectedIds, array $filters): array
    {
        if ($action !== 'delete_series') {
            throw new ValidationException(['Unbekannte Bulk-Aktion fuer Serien.']);
        }

        $seriesList = $this->resolveSeries($selectionMode, $selectedIds, $filters);
        if ($seriesList === []) {
            throw new ValidationException(['Keine passenden Serien fuer die Bulk-Aktion gefunden.']);
        }

        $items = [];
        $resolvedIds = [];
        foreach ($seriesList as $series) {
            $items[] = [
                'id' => (int) $series['id'],
                'label' => (string) $series['title'],
                'detail' => sprintf('%d verknuepfte Titel', (int) ($series['season_count'] ?? 0)),
                'status' => 'ready',
                'message' => 'Die Serie wird geloescht, verknuepfte Titel bleiben bestehen.',
            ];
            $resolvedIds[] = (int) $series['id'];
        }

        return [
            'module' => 'series',
            'action' => $action,
            'action_label' => 'Serien loeschen',
            'resolved_ids' => $resolvedIds,
            'payload' => [],
            'payload_lines' => [],
            'items' => $items,
            'summary' => [
                'affected' => count($items),
                'executable' => count($resolvedIds),
                'skipped' => 0,
            ],
            'warnings' => ['Verknuepfte Staffeln oder Titel werden dabei nur entkoppelt.'],
        ];
    }

    public function execute(array $snapshot): array
    {
        $resolvedIds = array_values(array_map('intval', (array) ($snapshot['resolved_ids'] ?? [])));
        $existing = $this->catalog->findSeriesByIds($resolvedIds);
        $existingIds = array_map(static fn (array $series): int => (int) $series['id'], $existing);
        $processed = $this->catalog->deleteSeries($existingIds);
        $skipped = max(0, count($resolvedIds) - count($existingIds));

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => 0,
            'warnings' => $skipped > 0 ? ['Mindestens eine Serie war vor der Ausfuehrung bereits nicht mehr vorhanden.'] : [],
        ];
    }

    private function resolveSeries(string $selectionMode, array $selectedIds, array $filters): array
    {
        if ($selectionMode === 'filtered') {
            $seriesList = $this->catalog->listSeries($filters);
        } elseif ($selectionMode === 'ids') {
            $seriesList = $this->catalog->findSeriesByIds($selectedIds);
        } else {
            throw new ValidationException(['Die Auswahlart fuer die Bulk-Aktion ist ungueltig.']);
        }

        if (count($seriesList) > self::MAX_TARGETS) {
            throw new ValidationException(['Es duerfen hoechstens 500 Serien auf einmal verarbeitet werden.']);
        }

        return $seriesList;
    }
}
