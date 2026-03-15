<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\ValidationException;
use App\Repositories\CatalogRepository;
use App\Repositories\UserRepository;
use App\Repositories\WishlistRepository;
use RuntimeException;

final class WishlistService
{
    public function __construct(
        private readonly Config $config,
        private readonly WishlistRepository $wishlist,
        private readonly UserRepository $users,
        private readonly CatalogRepository $catalog,
        private readonly HttpClient $http
    ) {
    }

    public function ensureDefaultList(int $userId, string $displayName): void
    {
        if ($this->wishlist->accessibleListIds($userId) !== []) {
            return;
        }

        $label = trim($displayName) !== ''
            ? sprintf('Wunschliste von %s', $displayName)
            : 'Wunschliste';

        $this->wishlist->saveList([
            'name' => $label,
            'description' => 'Persoenliche Einkaufsliste fuer Flohmarkt, Laden und spontane Funde.',
            'member_ids' => [$userId],
        ], $userId);
    }

    public function saveList(int $userId, array $input, ?int $listId = null): int
    {
        if ($listId !== null && !$this->wishlist->findList($listId, $userId)) {
            throw new ValidationException(['Die gewaehlte Wunschliste ist nicht verfuegbar.']);
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['Der Listenname ist erforderlich.']);
        }

        $memberIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) ($input['member_ids'] ?? [])
        ), static fn (int $id): bool => $id > 0)));

        if ($memberIds !== []) {
            $existingUsers = $this->users->findUsersByIds($memberIds);
            $existingIds = array_map(static fn (array $user): int => (int) $user['id'], $existingUsers);
            $memberIds = array_values(array_unique(array_merge([$userId], $existingIds)));
        } else {
            $memberIds = [$userId];
        }

        return $this->wishlist->saveList([
            'name' => $name,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'member_ids' => $memberIds,
        ], $userId, $listId);
    }

    public function saveItem(int $userId, array $input, ?int $itemId = null): int
    {
        $existing = null;
        if ($itemId !== null) {
            $existing = $this->wishlist->findItem($itemId, $userId);
            if (!$existing) {
                throw new ValidationException(['Der Wunsch-Eintrag wurde nicht gefunden.']);
            }
        }

        $kind = trim((string) ($input['kind'] ?? 'movie'));
        $title = trim((string) ($input['title'] ?? ''));
        $wishListId = (int) ($input['wish_list_id'] ?? 0);
        $targetFormat = trim((string) ($input['target_format'] ?? 'dvd'));
        $priority = trim((string) ($input['priority'] ?? 'medium'));
        $status = trim((string) ($input['status'] ?? 'open'));
        $seriesTitle = trim((string) ($input['series_title'] ?? ''));
        $seasonNumber = trim((string) ($input['season_number'] ?? ''));
        $year = trim((string) ($input['year'] ?? ''));
        $runtimeMinutes = trim((string) ($input['runtime_minutes'] ?? ''));
        $targetPrice = trim((string) ($input['target_price'] ?? ''));
        $seenPrice = trim((string) ($input['seen_price'] ?? ''));
        $boughtPrice = trim((string) ($input['bought_price'] ?? ''));

        $errors = [];
        if ($title === '') {
            $errors[] = 'Der Titel ist erforderlich.';
        }
        if (!in_array($kind, ['movie', 'season'], true)) {
            $errors[] = 'Der Typ ist ungueltig.';
        }
        if (!in_array($targetFormat, ['dvd', 'bluray', 'any'], true)) {
            $errors[] = 'Das Wunschformat ist ungueltig.';
        }
        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            $errors[] = 'Die Prioritaet ist ungueltig.';
        }
        if (!in_array($status, ['open', 'reserved', 'bought', 'dropped'], true)) {
            $errors[] = 'Der Status ist ungueltig.';
        }
        if ($kind === 'season' && $seriesTitle === '') {
            $errors[] = 'Fuer Staffeln ist ein Serientitel erforderlich.';
        }
        if ($kind === 'season' && $seasonNumber === '') {
            $errors[] = 'Fuer Staffeln ist eine Staffelnummer erforderlich.';
        }
        if ($year !== '' && !ctype_digit($year)) {
            $errors[] = 'Das Jahr muss numerisch sein.';
        }
        if ($seasonNumber !== '' && !ctype_digit($seasonNumber)) {
            $errors[] = 'Die Staffelnummer muss numerisch sein.';
        }
        if ($runtimeMinutes !== '' && !ctype_digit($runtimeMinutes)) {
            $errors[] = 'Die Laufzeit muss numerisch sein.';
        }
        foreach ([
            'Zielpreis' => $targetPrice,
            'Gesehen fuer' => $seenPrice,
            'Gekauft fuer' => $boughtPrice,
        ] as $label => $value) {
            if ($value !== '' && !$this->isNumericValue($value)) {
                $errors[] = $label . ' muss numerisch sein.';
            }
        }
        if (!$this->wishlist->findList($wishListId, $userId)) {
            $errors[] = 'Die gewaehlte Wunschliste ist nicht verfuegbar.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $reservedBy = $existing['reserved_by'] ?? null;
        $reservedAt = $existing['reserved_at'] ?? null;
        $foundAt = $existing['found_at'] ?? null;
        $boughtAt = $existing['bought_at'] ?? null;

        if ($status === 'reserved') {
            $reservedBy ??= $userId;
            $reservedAt ??= $this->now();
            $foundAt ??= $this->now();
        }

        if ($status === 'bought') {
            $reservedBy ??= $userId;
            $reservedAt ??= $this->now();
            $foundAt ??= $this->now();
            $boughtAt ??= $this->now();
        }

        if ($status === 'open') {
            $reservedBy = null;
            $reservedAt = null;
            $boughtAt = null;
        }

        if ($status === 'dropped') {
            $reservedBy = null;
            $reservedAt = null;
        }

        return $this->wishlist->saveItem([
            'wish_list_id' => $wishListId,
            'kind' => $kind,
            'title' => $title,
            'original_title' => trim((string) ($input['original_title'] ?? '')) ?: null,
            'year' => $year ?: null,
            'series_title' => $seriesTitle ?: null,
            'season_number' => $seasonNumber ?: null,
            'target_format' => $targetFormat,
            'priority' => $priority,
            'status' => $status,
            'target_price' => $targetPrice ?: null,
            'seen_price' => $seenPrice ?: null,
            'bought_price' => $boughtPrice ?: null,
            'store_name' => trim((string) ($input['store_name'] ?? '')) ?: null,
            'location' => trim((string) ($input['location'] ?? '')) ?: null,
            'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
            'overview' => trim((string) ($input['overview'] ?? '')) ?: null,
            'runtime_minutes' => $runtimeMinutes ?: null,
            'poster_path' => trim((string) ($input['poster_path'] ?? '')) ?: null,
            'metadata_status' => trim((string) ($input['metadata_status'] ?? 'manual')) ?: 'manual',
            'reserved_by' => $reservedBy,
            'converted_catalog_title_id' => $existing['converted_catalog_title_id'] ?? null,
            'found_at' => trim((string) ($input['found_at'] ?? '')) ?: $foundAt,
            'reserved_at' => trim((string) ($input['reserved_at'] ?? '')) ?: $reservedAt,
            'bought_at' => trim((string) ($input['bought_at'] ?? '')) ?: $boughtAt,
            'genres' => $this->splitGenres((string) ($input['genres_text'] ?? '')),
        ], $userId, $itemId);
    }

    public function deleteItem(int $itemId, int $userId): void
    {
        $deleted = $this->wishlist->deleteItems([$itemId], $userId);
        if ($deleted <= 0) {
            throw new ValidationException(['Der Wunsch-Eintrag konnte nicht geloescht werden.']);
        }
    }

    public function markStatus(int $itemId, int $userId, string $status): void
    {
        if (!in_array($status, ['reserved', 'bought', 'dropped'], true)) {
            throw new ValidationException(['Der neue Status ist ungueltig.']);
        }

        $updated = $this->wishlist->updateStatuses([$itemId], $status, $userId);
        if ($updated <= 0) {
            throw new ValidationException(['Der Wunsch-Eintrag konnte nicht aktualisiert werden.']);
        }
    }

    public function convert(int $itemId, int $userId): array
    {
        $item = $this->wishlist->findItem($itemId, $userId);
        if (!$item) {
            throw new ValidationException(['Der Wunsch-Eintrag wurde nicht gefunden.']);
        }
        if (($item['status'] ?? '') === 'dropped') {
            throw new ValidationException(['Verworfene Eintraege koennen nicht in die Sammlung uebernommen werden.']);
        }

        $seriesId = null;
        if (($item['kind'] ?? 'movie') === 'season') {
            if (empty($item['series_title']) || empty($item['season_number'])) {
                throw new ValidationException(['Fuer Staffeln werden Serientitel und Staffelnummer fuer die Uebernahme benoetigt.']);
            }

            $seriesId = $this->catalog->findOrCreateSeries((string) $item['series_title'], [
                'original_title' => $item['original_title'] ?? null,
                'year_start' => $item['year'] ?? null,
                'overview' => $item['overview'] ?? null,
                'poster_path' => $item['poster_path'] ?? null,
            ]);
        }

        $duplicatePayload = [
            'kind' => $item['kind'],
            'title' => $item['title'],
            'year' => $item['year'] ?? null,
            'series_id' => $seriesId,
            'season_number' => $item['season_number'] ?? null,
        ];

        $primaryRef = $item['external_refs'][0] ?? null;
        if ($primaryRef) {
            $duplicatePayload['provider'] = $primaryRef['provider'];
            $duplicatePayload['external_id'] = $primaryRef['external_id'];
        }

        $title = $this->catalog->findDuplicateTitle($duplicatePayload);
        $warnings = [];
        $createdTitle = false;

        if (!$title) {
            $titleId = $this->catalog->saveTitle([
                'kind' => $item['kind'],
                'title' => $item['title'],
                'original_title' => $item['original_title'] ?? null,
                'year' => $item['year'] ?? null,
                'series_id' => $seriesId,
                'season_number' => $item['season_number'] ?? null,
                'overview' => $item['overview'] ?? null,
                'runtime_minutes' => $item['runtime_minutes'] ?? null,
                'poster_path' => $item['poster_path'] ?? null,
                'metadata_status' => $item['metadata_status'] ?? 'manual',
                'genres' => $item['genres'] ?? [],
            ]);
            $title = $this->catalog->findTitle($titleId);
            $createdTitle = true;
        } else {
            $titleId = (int) $title['id'];
        }

        foreach ((array) ($item['external_refs'] ?? []) as $externalRef) {
            $this->catalog->upsertExternalRef(
                (int) $titleId,
                (string) $externalRef['provider'],
                (string) $externalRef['external_id'],
                $externalRef['source_url'] ?? null,
                $externalRef['payload_json'] ?? null
            );
        }

        $mediaFormat = ($item['target_format'] ?? 'dvd') === 'any'
            ? 'dvd'
            : (string) $item['target_format'];
        if (($item['target_format'] ?? 'dvd') === 'any') {
            $warnings[] = 'Fuer "egal" wurde beim Uebernehmen standardmaessig ein DVD-Exemplar angelegt.';
        }

        $copyPayload = [
            'media_format' => $mediaFormat,
            'edition' => null,
            'barcode' => null,
            'item_condition' => null,
            'storage_location' => trim((string) ($item['location'] ?? '')) ?: null,
            'notes' => $this->buildCopyNotes($item),
        ];

        $createdCopy = false;
        if (!$this->catalog->findDuplicateCopy((int) $titleId, $copyPayload)) {
            $this->catalog->storeCopy((int) $titleId, $copyPayload);
            $createdCopy = true;
        } else {
            $warnings[] = 'Ein passendes physisches Medium existiert bereits und wurde nicht doppelt angelegt.';
        }

        $this->wishlist->markConverted($itemId, (int) $titleId, $userId, isset($item['bought_price']) ? (float) $item['bought_price'] : null);

        return [
            'title' => $this->catalog->findTitle((int) $titleId),
            'created_title' => $createdTitle,
            'created_copy' => $createdCopy,
            'warnings' => $warnings,
        ];
    }

    public function searchMetadata(int $itemId, int $userId): array
    {
        $item = $this->wishlist->findItem($itemId, $userId);
        if (!$item) {
            throw new RuntimeException('Wunsch-Eintrag nicht gefunden.');
        }

        $results = [];
        if ($this->config->get('metadata.tmdb_api_key')) {
            $results = array_merge($results, $this->searchTmdb($item));
        }
        $results = array_merge($results, $this->searchWikidata($item));

        return $results;
    }

    public function applyMetadata(int $itemId, int $userId, string $provider, string $externalId, bool $overwrite = false): array
    {
        $item = $this->wishlist->findItem($itemId, $userId);
        if (!$item) {
            throw new RuntimeException('Wunsch-Eintrag nicht gefunden.');
        }

        $metadata = match ($provider) {
            'tmdb' => $this->fetchTmdbDetails($item, $externalId),
            'wikidata' => $this->fetchWikidataDetails($externalId),
            default => throw new RuntimeException('Unbekannter Metadatenanbieter.'),
        };

        $this->wishlist->applyMetadata($itemId, $metadata, $overwrite);
        $this->wishlist->upsertExternalRef(
            $itemId,
            $provider,
            $externalId,
            $metadata['source_url'] ?? null,
            json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        if (!empty($metadata['poster_url'])) {
            $poster = $this->downloadPoster((string) $metadata['poster_url']);
            if ($poster !== null) {
                $this->wishlist->setPosterPath($itemId, $poster['relative_path']);
            }
        }

        return $this->wishlist->findItem($itemId, $userId) ?? [];
    }

    private function buildCopyNotes(array $item): ?string
    {
        $lines = [];
        if (!empty($item['store_name'])) {
            $lines[] = 'Fundort: ' . $item['store_name'];
        }
        if (!empty($item['location'])) {
            $lines[] = 'Ort: ' . $item['location'];
        }
        if (!empty($item['bought_price'])) {
            $lines[] = 'Kaufpreis: ' . number_format((float) $item['bought_price'], 2, ',', '.') . ' EUR';
        } elseif (!empty($item['seen_price'])) {
            $lines[] = 'Gesehen fuer: ' . number_format((float) $item['seen_price'], 2, ',', '.') . ' EUR';
        }
        if (!empty($item['notes'])) {
            $lines[] = trim((string) $item['notes']);
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function splitGenres(string $value): array
    {
        $parts = preg_split('/[,;|]/', $value) ?: [];
        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function searchTmdb(array $item): array
    {
        $isSeason = ($item['kind'] ?? 'movie') === 'season';
        $query = http_build_query([
            'api_key' => $this->config->get('metadata.tmdb_api_key'),
            'query' => $isSeason ? ($item['series_title'] ?: $item['title']) : $item['title'],
            'language' => 'de-DE',
            'year' => $item['year'] ?: null,
        ]);

        $endpoint = $isSeason ? '/search/tv?' : '/search/movie?';
        $payload = $this->http->getJson($this->config->get('metadata.tmdb_base_url') . $endpoint . $query);
        $results = [];

        foreach ($payload['results'] ?? [] as $result) {
            $results[] = [
                'provider' => 'tmdb',
                'external_id' => (string) $result['id'],
                'title' => $result['title'] ?? $result['name'] ?? 'TMDb-Treffer',
                'year' => substr((string) ($result['release_date'] ?? $result['first_air_date'] ?? ''), 0, 4) ?: null,
                'overview' => $result['overview'] ?? '',
                'poster_url' => !empty($result['poster_path']) ? $this->config->get('metadata.tmdb_image_url') . $result['poster_path'] : null,
                'source_url' => 'https://www.themoviedb.org/' . ($isSeason ? 'tv/' : 'movie/') . $result['id'],
            ];
        }

        return $results;
    }

    private function fetchTmdbDetails(array $item, string $externalId): array
    {
        $isSeason = ($item['kind'] ?? 'movie') === 'season';
        $baseUrl = (string) $this->config->get('metadata.tmdb_base_url');
        $apiKey = (string) $this->config->get('metadata.tmdb_api_key');

        if ($isSeason) {
            $tv = $this->http->getJson($baseUrl . '/tv/' . rawurlencode($externalId) . '?' . http_build_query([
                'api_key' => $apiKey,
                'language' => 'de-DE',
            ]));
            $season = $this->http->getJson($baseUrl . '/tv/' . rawurlencode($externalId) . '/season/' . (int) $item['season_number'] . '?' . http_build_query([
                'api_key' => $apiKey,
                'language' => 'de-DE',
            ]));

            return [
                'title' => $season['name'] ?? $item['title'],
                'original_title' => $tv['original_name'] ?? $item['original_title'],
                'year' => substr((string) ($season['air_date'] ?? $tv['first_air_date'] ?? ''), 0, 4) ?: null,
                'overview' => $season['overview'] ?? $tv['overview'] ?? '',
                'runtime_minutes' => $tv['episode_run_time'][0] ?? $item['runtime_minutes'],
                'poster_url' => !empty($season['poster_path'])
                    ? $this->config->get('metadata.tmdb_image_url') . $season['poster_path']
                    : (!empty($tv['poster_path']) ? $this->config->get('metadata.tmdb_image_url') . $tv['poster_path'] : null),
                'genres' => array_map(static fn (array $genre): string => $genre['name'], $tv['genres'] ?? []),
                'metadata_status' => 'enriched',
                'source_url' => 'https://www.themoviedb.org/tv/' . $externalId . '/season/' . (int) $item['season_number'],
                'poster_path' => null,
            ];
        }

        $movie = $this->http->getJson($baseUrl . '/movie/' . rawurlencode($externalId) . '?' . http_build_query([
            'api_key' => $apiKey,
            'language' => 'de-DE',
        ]));

        return [
            'title' => $movie['title'] ?? $item['title'],
            'original_title' => $movie['original_title'] ?? $item['original_title'],
            'year' => substr((string) ($movie['release_date'] ?? ''), 0, 4) ?: null,
            'overview' => $movie['overview'] ?? '',
            'runtime_minutes' => $movie['runtime'] ?? $item['runtime_minutes'],
            'poster_url' => !empty($movie['poster_path']) ? $this->config->get('metadata.tmdb_image_url') . $movie['poster_path'] : null,
            'genres' => array_map(static fn (array $genre): string => $genre['name'], $movie['genres'] ?? []),
            'metadata_status' => 'enriched',
            'source_url' => 'https://www.themoviedb.org/movie/' . $externalId,
            'poster_path' => null,
        ];
    }

    private function searchWikidata(array $item): array
    {
        $payload = $this->http->getJson((string) $this->config->get('metadata.wikidata_api_url') . '?' . http_build_query([
            'action' => 'wbsearchentities',
            'search' => ($item['kind'] ?? 'movie') === 'season' ? ($item['series_title'] ?: $item['title']) : $item['title'],
            'language' => 'de',
            'format' => 'json',
            'limit' => 5,
            'origin' => '*',
        ]));

        $results = [];
        foreach ($payload['search'] ?? [] as $result) {
            $results[] = [
                'provider' => 'wikidata',
                'external_id' => (string) $result['id'],
                'title' => $result['label'] ?? $result['id'],
                'year' => null,
                'overview' => $result['description'] ?? '',
                'poster_url' => null,
                'source_url' => 'https://www.wikidata.org/wiki/' . $result['id'],
            ];
        }

        return $results;
    }

    private function fetchWikidataDetails(string $externalId): array
    {
        $entityUrl = sprintf((string) $this->config->get('metadata.wikidata_entity_url'), rawurlencode($externalId));
        $payload = $this->http->getJson($entityUrl);
        $entity = $payload['entities'][$externalId] ?? null;
        if (!$entity) {
            throw new RuntimeException('Wikidata-Eintrag konnte nicht geladen werden.');
        }

        $labels = $entity['labels'] ?? [];
        $descriptions = $entity['descriptions'] ?? [];
        $claims = $entity['claims'] ?? [];

        return [
            'title' => $labels['de']['value'] ?? $labels['en']['value'] ?? $externalId,
            'original_title' => $labels['en']['value'] ?? null,
            'overview' => $descriptions['de']['value'] ?? $descriptions['en']['value'] ?? '',
            'runtime_minutes' => null,
            'genres' => [],
            'poster_url' => null,
            'metadata_status' => 'enriched',
            'source_url' => 'https://www.wikidata.org/wiki/' . $externalId,
            'imdb_id' => $claims['P345'][0]['mainsnak']['datavalue']['value'] ?? null,
            'poster_path' => null,
        ];
    }

    private function downloadPoster(string $url): ?array
    {
        $binary = $this->http->getBinary($url);
        if ($binary === '') {
            return null;
        }

        $extension = str_contains($url, '.png') ? 'png' : 'jpg';
        $filename = sha1($url . $binary) . '.' . $extension;
        $absolutePath = rtrim((string) $this->config->get('paths.posters'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($absolutePath, $binary);

        return [
            'relative_path' => '/media/posters/' . $filename,
        ];
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function isNumericValue(string $value): bool
    {
        return is_numeric(str_replace(',', '.', $value));
    }
}
