<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\DTO\ImportPreviewResult;
use App\Repositories\CatalogRepository;
use App\Repositories\WatchRepository;
use RuntimeException;

final class CsvImportService
{
    public const CANONICAL_FIELDS = [
        'kind',
        'title',
        'original_title',
        'year',
        'series_title',
        'season_number',
        'media_format',
        'edition',
        'barcode',
        'condition',
        'storage_location',
        'genres',
        'runtime_minutes',
        'notes',
        'watched',
        'watched_at',
        'external_id',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly CatalogRepository $catalog,
        private readonly WatchRepository $watch
    ) {
    }

    public function storeUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('CSV-Datei konnte nicht hochgeladen werden.');
        }

        $content = file_get_contents((string) $file['tmp_name']);
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('Die CSV-Datei ist leer.');
        }

        $content = $this->normalizeEncoding($content);
        $delimiter = $this->detectDelimiter($content);
        $lines = preg_split("/\r\n|\n|\r/", trim($content)) ?: [];

        $header = str_getcsv(array_shift($lines) ?: '', $delimiter);
        $sampleRows = [];
        foreach (array_slice($lines, 0, 5) as $line) {
            $sampleRows[] = str_getcsv($line, $delimiter);
        }

        $filename = uniqid('import_', true) . '.csv';
        $path = rtrim((string) $this->config->get('paths.imports'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $content);

        return [
            'path' => $path,
            'delimiter' => $delimiter,
            'headers' => $header,
            'sample_rows' => $sampleRows,
            'auto_mapping' => $this->guessMapping($header),
        ];
    }

    public function preview(string $path, array $mapping): ImportPreviewResult
    {
        $content = file_get_contents($path);
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('Importdatei nicht gefunden.');
        }

        $delimiter = $this->detectDelimiter($content);
        $lines = preg_split("/\r\n|\n|\r/", trim($content)) ?: [];
        $headers = str_getcsv(array_shift($lines) ?: '', $delimiter);

        $rows = [];
        $errors = [];
        $warnings = [];

        foreach ($lines as $index => $line) {
            $raw = str_getcsv($line, $delimiter);
            $normalized = [];
            foreach ($headers as $columnIndex => $header) {
                $target = $mapping[$header] ?? null;
                if ($target && in_array($target, self::CANONICAL_FIELDS, true)) {
                    $normalized[$target] = $raw[$columnIndex] ?? null;
                }
            }

            $rowErrors = $this->validateRow($normalized);
            $rowWarnings = [];
            if ($rowErrors === []) {
                $normalized['kind'] = $normalized['kind'] ?: 'movie';
                $normalized['genres'] = $this->splitGenres((string) ($normalized['genres'] ?? ''));
                $duplicate = $this->catalog->findDuplicateTitle([
                    'kind' => $normalized['kind'],
                    'title' => $normalized['title'],
                    'year' => $normalized['year'] ?? null,
                    'season_number' => $normalized['season_number'] ?? null,
                ]);
                if ($duplicate) {
                    $rowWarnings[] = 'Titel existiert bereits und wird gemerged.';
                    $normalized['_duplicate_title_id'] = $duplicate['id'];
                }
            }

            $rows[] = [
                'line' => $index + 2,
                'data' => $normalized,
                'errors' => $rowErrors,
                'warnings' => $rowWarnings,
            ];

            $errors = array_merge($errors, $rowErrors);
            $warnings = array_merge($warnings, $rowWarnings);
        }

        return new ImportPreviewResult(
            $rows,
            $errors,
            $warnings,
            $mapping,
            [
                'row_count' => count($rows),
                'error_count' => count($errors),
                'warning_count' => count($warnings),
            ]
        );
    }

    public function commit(array $rows, int $userId): array
    {
        $created = 0;
        $updated = 0;
        $copies = 0;
        $watched = 0;

        foreach ($rows as $row) {
            if (($row['errors'] ?? []) !== []) {
                continue;
            }

            $data = $row['data'];
            $seriesId = null;
            if (($data['kind'] ?? 'movie') === 'season' && !empty($data['series_title'])) {
                $seriesId = $this->findOrCreateSeries((string) $data['series_title']);
            }

            $existing = !empty($data['_duplicate_title_id'])
                ? $this->catalog->findTitle((int) $data['_duplicate_title_id'])
                : null;

            $payload = [
                'kind' => $data['kind'] ?? 'movie',
                'title' => $data['title'],
                'original_title' => $data['original_title'] ?? null,
                'year' => $data['year'] ?? null,
                'series_id' => $seriesId,
                'season_number' => $data['season_number'] ?? null,
                'overview' => $data['notes'] ?? null,
                'runtime_minutes' => $data['runtime_minutes'] ?? null,
                'metadata_status' => 'imported',
                'genres' => $data['genres'] ?? [],
            ];

            if ($existing) {
                $payload = array_merge($existing, array_filter($payload, static fn (mixed $value): bool => $value !== null && $value !== ''));
                $titleId = $this->catalog->saveTitle($payload, (int) $existing['id']);
                $updated++;
            } else {
                $titleId = $this->catalog->saveTitle($payload);
                $created++;
            }

            if (!empty($data['external_id'])) {
                $this->catalog->upsertExternalRef($titleId, 'import', (string) $data['external_id'], null, null);
            }

            if (!empty($data['media_format'])) {
                $copy = $this->catalog->findDuplicateCopy($titleId, [
                    'barcode' => $data['barcode'] ?? null,
                    'media_format' => $data['media_format'],
                    'edition' => $data['edition'] ?? null,
                    'storage_location' => $data['storage_location'] ?? null,
                ]);

                if (!$copy) {
                    $this->catalog->storeCopy($titleId, [
                        'media_format' => $data['media_format'],
                        'edition' => $data['edition'] ?? null,
                        'barcode' => $data['barcode'] ?? null,
                        'item_condition' => $data['condition'] ?? null,
                        'storage_location' => $data['storage_location'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ]);
                    $copies++;
                }
            }

            if ($this->isTruthy($data['watched'] ?? null)) {
                $this->watch->addEvent(
                    $userId,
                    $titleId,
                    !empty($data['watched_at']) ? (string) $data['watched_at'] : date('Y-m-d H:i:s'),
                    $data['notes'] ?? null
                );
                $watched++;
            }
        }

        return compact('created', 'updated', 'copies', 'watched');
    }

    private function guessMapping(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $header) {
            $normalized = strtolower(trim((string) $header));
            $mapping[$header] = match ($normalized) {
                'typ', 'kind', 'type' => 'kind',
                'titel', 'title' => 'title',
                'originaltitel', 'original_title' => 'original_title',
                'jahr', 'year' => 'year',
                'serie', 'series', 'series_title' => 'series_title',
                'staffel', 'season', 'season_number' => 'season_number',
                'format', 'media_format' => 'media_format',
                'edition' => 'edition',
                'barcode', 'ean' => 'barcode',
                'zustand', 'condition' => 'condition',
                'lagerort', 'storage_location' => 'storage_location',
                'genre', 'genres' => 'genres',
                'laufzeit', 'runtime', 'runtime_minutes' => 'runtime_minutes',
                'notizen', 'notes' => 'notes',
                'gesehen', 'watched' => 'watched',
                'gesehen_am', 'watched_at' => 'watched_at',
                'external_id', 'tmdb_id', 'wikidata_id' => 'external_id',
                default => '',
            };
        }

        return $mapping;
    }

    private function validateRow(array $row): array
    {
        $errors = [];
        if (empty($row['title'])) {
            $errors[] = 'Titel fehlt.';
        }

        if (!empty($row['kind']) && !in_array($row['kind'], ['movie', 'season'], true)) {
            $errors[] = 'Typ muss movie oder season sein.';
        }

        if (($row['kind'] ?? 'movie') === 'season' && empty($row['series_title'])) {
            $errors[] = 'Fuer Staffeln ist ein Serientitel erforderlich.';
        }

        if (!empty($row['media_format']) && !in_array($row['media_format'], ['dvd', 'bluray'], true)) {
            $errors[] = 'Format muss dvd oder bluray sein.';
        }

        return $errors;
    }

    private function splitGenres(string $value): array
    {
        $parts = preg_split('/[,;|]/', $value) ?: [];
        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\r\n") ?: '';
        $candidates = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
        arsort($candidates);
        return (string) array_key_first($candidates);
    }

    private function normalizeEncoding(string $content): string
    {
        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
            if ($encoding && $encoding !== 'UTF-8') {
                return mb_convert_encoding($content, 'UTF-8', $encoding);
            }
            return $content;
        }

        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $content);
        return is_string($converted) ? $converted : $content;
    }

    private function findOrCreateSeries(string $title): int
    {
        foreach ($this->catalog->listSeries() as $series) {
            if (strtolower((string) $series['title']) === strtolower($title)) {
                return (int) $series['id'];
            }
        }

        return $this->catalog->saveSeries(['title' => $title]);
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ja', 'yes', 'x'], true);
    }
}
