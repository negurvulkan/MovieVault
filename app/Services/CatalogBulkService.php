<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Repositories\CatalogRepository;

final class CatalogBulkService
{
    private const MAX_TARGETS = 500;

    public function __construct(private readonly CatalogRepository $catalog)
    {
    }

    public function preview(
        string $action,
        string $selectionMode,
        array $selectedIds,
        array $filters,
        array $payload,
        int $userId
    ): array {
        $titles = $this->resolveTitles($selectionMode, $selectedIds, $filters, $userId);

        if ($titles === []) {
            throw new ValidationException(['Keine passenden Katalogeintraege fuer die Bulk-Aktion gefunden.']);
        }

        $preparedPayload = [];
        $payloadLines = [];
        $items = [];
        $resolvedIds = [];
        $warnings = [];

        if ($action === 'create_copies') {
            $preparedPayload = $this->normalizeCopyPayload($payload);
            $payloadLines = $this->copyPayloadLines($preparedPayload);

            foreach ($titles as $title) {
                $duplicate = $this->catalog->findDuplicateCopy((int) $title['id'], $preparedPayload);
                if ($duplicate) {
                    $items[] = $this->previewItem($title, 'skip', 'Passendes Exemplar existiert bereits.');
                    continue;
                }

                $items[] = $this->previewItem($title, 'ready', 'Exemplar wird angelegt.');
                $resolvedIds[] = (int) $title['id'];
            }
        } elseif ($action === 'create_series_master') {
            foreach ($titles as $title) {
                if (!empty($title['series_id'])) {
                    $items[] = $this->previewItem($title, 'skip', 'Titel ist bereits mit einer Serie verknuepft.');
                    continue;
                }

                $items[] = $this->previewItem($title, 'ready', 'Serienstamm wird erstellt oder verknuepft.');
                $resolvedIds[] = (int) $title['id'];
            }
        } elseif ($action === 'delete_titles') {
            foreach ($titles as $title) {
                $items[] = $this->previewItem(
                    $title,
                    'ready',
                    sprintf('%d Exemplare werden mit entfernt.', (int) ($title['copies_count'] ?? 0))
                );
                $resolvedIds[] = (int) $title['id'];
            }
            $warnings[] = 'Das Loeschen entfernt auch Exemplare, externe Referenzen, Poster und Watch-Events.';
        } else {
            throw new ValidationException(['Unbekannte Bulk-Aktion fuer den Katalog.']);
        }

        return $this->buildPreview(
            'catalog',
            $action,
            $this->actionLabel($action),
            $resolvedIds,
            $preparedPayload,
            $payloadLines,
            $items,
            array_values(array_unique($warnings))
        );
    }

    public function execute(array $snapshot): array
    {
        $action = (string) ($snapshot['action'] ?? '');
        $resolvedIds = array_values(array_map('intval', (array) ($snapshot['resolved_ids'] ?? [])));
        $payload = (array) ($snapshot['payload'] ?? []);

        return match ($action) {
            'create_copies' => $this->executeCreateCopies($resolvedIds, $payload),
            'create_series_master' => $this->executeCreateSeriesMasters($resolvedIds),
            'delete_titles' => $this->executeDeleteTitles($resolvedIds),
            default => throw new ValidationException(['Unbekannte Bulk-Aktion fuer den Katalog.']),
        };
    }

    private function executeCreateCopies(array $titleIds, array $payload): array
    {
        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $warnings = [];

        foreach ($titleIds as $titleId) {
            $title = $this->catalog->findTitle($titleId);
            if (!$title) {
                $failed++;
                $warnings[] = 'Ein Titel war zwischen Vorschau und Ausfuehrung nicht mehr vorhanden.';
                continue;
            }

            $duplicate = $this->catalog->findDuplicateCopy($titleId, $payload);
            if ($duplicate) {
                $skipped++;
                $warnings[] = sprintf('%s wurde uebersprungen, weil bereits ein passendes Exemplar existiert.', $this->titleLabel($title));
                continue;
            }

            $this->catalog->storeCopy($titleId, $payload);
            $processed++;
        }

        return $this->result($processed, $skipped, $failed, $warnings);
    }

    private function executeCreateSeriesMasters(array $titleIds): array
    {
        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $warnings = [];

        foreach ($titleIds as $titleId) {
            $title = $this->catalog->findTitle($titleId);
            if (!$title) {
                $failed++;
                $warnings[] = 'Ein Titel war zwischen Vorschau und Ausfuehrung nicht mehr vorhanden.';
                continue;
            }

            if (!empty($title['series_id'])) {
                $skipped++;
                $warnings[] = sprintf('%s ist bereits mit einer Serie verknuepft.', $this->titleLabel($title));
                continue;
            }

            $this->catalog->createSeriesFromTitle($titleId);
            $processed++;
        }

        return $this->result($processed, $skipped, $failed, $warnings);
    }

    private function executeDeleteTitles(array $titleIds): array
    {
        $existing = $this->catalog->findTitlesByIds($titleIds);
        $existingIds = array_map(static fn (array $title): int => (int) $title['id'], $existing);
        $processed = $this->catalog->deleteTitles($existingIds);
        $skipped = max(0, count($titleIds) - count($existingIds));

        return $this->result(
            $processed,
            $skipped,
            0,
            $skipped > 0 ? ['Mindestens ein Titel war vor der Ausfuehrung bereits entfernt worden.'] : []
        );
    }

    private function resolveTitles(string $selectionMode, array $selectedIds, array $filters, int $userId): array
    {
        if ($selectionMode === 'filtered') {
            $titles = $this->catalog->allTitles($filters, $userId);
        } elseif ($selectionMode === 'ids') {
            $titles = $this->catalog->findTitlesByIds($selectedIds, $userId);
        } else {
            throw new ValidationException(['Die Auswahlart fuer die Bulk-Aktion ist ungueltig.']);
        }

        if (count($titles) > self::MAX_TARGETS) {
            throw new ValidationException(['Es duerfen hoechstens 500 Katalogeintraege auf einmal verarbeitet werden.']);
        }

        return $titles;
    }

    private function normalizeCopyPayload(array $payload): array
    {
        $mediaFormat = trim((string) ($payload['media_format'] ?? ''));
        if (!in_array($mediaFormat, ['dvd', 'bluray'], true)) {
            throw new ValidationException(['Bitte fuer das Bulk-Anlegen ein gueltiges Format auswaehlen.']);
        }

        return [
            'media_format' => $mediaFormat,
            'edition' => trim((string) ($payload['edition'] ?? '')) ?: null,
            'item_condition' => trim((string) ($payload['item_condition'] ?? '')) ?: null,
            'storage_location' => trim((string) ($payload['storage_location'] ?? '')) ?: null,
            'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
        ];
    }

    private function copyPayloadLines(array $payload): array
    {
        $lines = ['Format: ' . ($payload['media_format'] === 'bluray' ? 'Blu-ray' : 'DVD')];
        if (!empty($payload['edition'])) {
            $lines[] = 'Edition: ' . $payload['edition'];
        }
        if (!empty($payload['item_condition'])) {
            $lines[] = 'Zustand: ' . $payload['item_condition'];
        }
        if (!empty($payload['storage_location'])) {
            $lines[] = 'Lagerort: ' . $payload['storage_location'];
        }
        if (!empty($payload['notes'])) {
            $lines[] = 'Notiz: ' . $payload['notes'];
        }

        return $lines;
    }

    private function buildPreview(
        string $module,
        string $action,
        string $actionLabel,
        array $resolvedIds,
        array $payload,
        array $payloadLines,
        array $items,
        array $warnings
    ): array {
        return [
            'module' => $module,
            'action' => $action,
            'action_label' => $actionLabel,
            'resolved_ids' => $resolvedIds,
            'payload' => $payload,
            'payload_lines' => $payloadLines,
            'items' => $items,
            'summary' => [
                'affected' => count($items),
                'executable' => count($resolvedIds),
                'skipped' => max(0, count($items) - count($resolvedIds)),
            ],
            'warnings' => $warnings,
        ];
    }

    private function previewItem(array $title, string $status, string $message): array
    {
        return [
            'id' => (int) $title['id'],
            'label' => $this->titleLabel($title),
            'detail' => $this->titleDetail($title),
            'status' => $status,
            'message' => $message,
        ];
    }

    private function titleLabel(array $title): string
    {
        if (($title['kind'] ?? 'movie') === 'season') {
            return sprintf(
                '%s - Staffel %s',
                $title['series_title'] ?: $title['title'],
                $title['season_number'] ?: '?'
            );
        }

        return (string) $title['title'];
    }

    private function titleDetail(array $title): string
    {
        if (($title['kind'] ?? 'movie') === 'season') {
            return sprintf(
                '%s Exemplare',
                (string) ((int) ($title['copies_count'] ?? 0))
            );
        }

        return $title['year'] ? (string) $title['year'] : 'Film';
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'create_copies' => 'Physische Medien anlegen',
            'create_series_master' => 'Serienstaemme anlegen oder verknuepfen',
            'delete_titles' => 'Katalogeintraege loeschen',
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
