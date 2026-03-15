<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\CatalogRepository;
use RuntimeException;

final class MetadataService
{
    public function __construct(
        private readonly Config $config,
        private readonly CatalogRepository $catalog,
        private readonly HttpClient $http
    ) {
    }

    public function searchForTitle(int $titleId): array
    {
        $title = $this->catalog->findTitle($titleId);
        if (!$title) {
            throw new RuntimeException('Titel nicht gefunden.');
        }

        $results = [];
        if ($this->config->get('metadata.tmdb_api_key')) {
            $results = array_merge($results, $this->searchTmdb($title));
        }
        $results = array_merge($results, $this->searchWikidata($title));

        return $results;
    }

    public function apply(int $titleId, string $provider, string $externalId, bool $overwrite = false): array
    {
        $title = $this->catalog->findTitle($titleId);
        if (!$title) {
            throw new RuntimeException('Titel nicht gefunden.');
        }

        $metadata = match ($provider) {
            'tmdb' => $this->fetchTmdbDetails($title, $externalId),
            'wikidata' => $this->fetchWikidataDetails($externalId),
            default => throw new RuntimeException('Unbekannter Metadatenanbieter.'),
        };

        $this->catalog->applyMetadata($titleId, $metadata, $overwrite);
        $this->catalog->upsertExternalRef(
            $titleId,
            $provider,
            $externalId,
            $metadata['source_url'] ?? null,
            json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        if (!empty($metadata['poster_url'])) {
            $poster = $this->downloadPoster((string) $metadata['poster_url']);
            if ($poster !== null) {
                $this->catalog->setPoster($titleId, $provider, (string) $metadata['poster_url'], $poster['relative_path'], $poster['checksum']);
            }
        }

        return $this->catalog->findTitle($titleId) ?? [];
    }

    private function searchTmdb(array $title): array
    {
        $isSeason = $title['kind'] === 'season';
        $query = http_build_query([
            'api_key' => $this->config->get('metadata.tmdb_api_key'),
            'query' => $isSeason ? ($title['series_title'] ?: $title['title']) : $title['title'],
            'language' => 'de-DE',
            'year' => $title['year'] ?: null,
        ]);

        $endpoint = $isSeason ? '/search/tv?' : '/search/movie?';
        $payload = $this->http->getJson($this->config->get('metadata.tmdb_base_url') . $endpoint . $query);
        $results = [];
        foreach ($payload['results'] ?? [] as $item) {
            $results[] = [
                'provider' => 'tmdb',
                'external_id' => (string) $item['id'],
                'title' => $item['title'] ?? $item['name'] ?? 'TMDb-Treffer',
                'year' => substr((string) ($item['release_date'] ?? $item['first_air_date'] ?? ''), 0, 4) ?: null,
                'overview' => $item['overview'] ?? '',
                'poster_url' => !empty($item['poster_path']) ? $this->config->get('metadata.tmdb_image_url') . $item['poster_path'] : null,
                'source_url' => 'https://www.themoviedb.org/' . ($isSeason ? 'tv/' : 'movie/') . $item['id'],
            ];
        }

        return $results;
    }

    private function fetchTmdbDetails(array $title, string $externalId): array
    {
        $isSeason = $title['kind'] === 'season';
        $baseUrl = (string) $this->config->get('metadata.tmdb_base_url');
        $apiKey = (string) $this->config->get('metadata.tmdb_api_key');

        if ($isSeason) {
            $tv = $this->http->getJson($baseUrl . '/tv/' . rawurlencode($externalId) . '?' . http_build_query([
                'api_key' => $apiKey,
                'language' => 'de-DE',
            ]));
            $season = $this->http->getJson($baseUrl . '/tv/' . rawurlencode($externalId) . '/season/' . (int) $title['season_number'] . '?' . http_build_query([
                'api_key' => $apiKey,
                'language' => 'de-DE',
            ]));

            return [
                'title' => $season['name'] ?? $title['title'],
                'original_title' => $tv['original_name'] ?? $title['original_title'],
                'year' => substr((string) ($season['air_date'] ?? $tv['first_air_date'] ?? ''), 0, 4) ?: null,
                'overview' => $season['overview'] ?? $tv['overview'] ?? '',
                'runtime_minutes' => $tv['episode_run_time'][0] ?? $title['runtime_minutes'],
                'poster_url' => !empty($season['poster_path'])
                    ? $this->config->get('metadata.tmdb_image_url') . $season['poster_path']
                    : (!empty($tv['poster_path']) ? $this->config->get('metadata.tmdb_image_url') . $tv['poster_path'] : null),
                'genres' => array_map(static fn (array $genre): string => $genre['name'], $tv['genres'] ?? []),
                'metadata_status' => 'enriched',
                'source_url' => 'https://www.themoviedb.org/tv/' . $externalId . '/season/' . (int) $title['season_number'],
                'poster_path' => null,
            ];
        }

        $movie = $this->http->getJson($baseUrl . '/movie/' . rawurlencode($externalId) . '?' . http_build_query([
            'api_key' => $apiKey,
            'language' => 'de-DE',
        ]));

        return [
            'title' => $movie['title'] ?? $title['title'],
            'original_title' => $movie['original_title'] ?? $title['original_title'],
            'year' => substr((string) ($movie['release_date'] ?? ''), 0, 4) ?: null,
            'overview' => $movie['overview'] ?? '',
            'runtime_minutes' => $movie['runtime'] ?? $title['runtime_minutes'],
            'poster_url' => !empty($movie['poster_path']) ? $this->config->get('metadata.tmdb_image_url') . $movie['poster_path'] : null,
            'genres' => array_map(static fn (array $genre): string => $genre['name'], $movie['genres'] ?? []),
            'metadata_status' => 'enriched',
            'source_url' => 'https://www.themoviedb.org/movie/' . $externalId,
            'poster_path' => null,
        ];
    }

    private function searchWikidata(array $title): array
    {
        $payload = $this->http->getJson((string) $this->config->get('metadata.wikidata_api_url') . '?' . http_build_query([
            'action' => 'wbsearchentities',
            'search' => $title['kind'] === 'season' ? ($title['series_title'] ?: $title['title']) : $title['title'],
            'language' => 'de',
            'format' => 'json',
            'limit' => 5,
            'origin' => '*',
        ]));

        $results = [];
        foreach ($payload['search'] ?? [] as $item) {
            $results[] = [
                'provider' => 'wikidata',
                'external_id' => (string) $item['id'],
                'title' => $item['label'] ?? $item['id'],
                'year' => null,
                'overview' => $item['description'] ?? '',
                'poster_url' => null,
                'source_url' => 'https://www.wikidata.org/wiki/' . $item['id'],
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

        $title = $labels['de']['value'] ?? $labels['en']['value'] ?? $externalId;
        $description = $descriptions['de']['value'] ?? $descriptions['en']['value'] ?? '';
        $imdb = $claims['P345'][0]['mainsnak']['datavalue']['value'] ?? null;

        return [
            'title' => $title,
            'original_title' => $labels['en']['value'] ?? null,
            'overview' => $description,
            'runtime_minutes' => null,
            'genres' => [],
            'poster_url' => null,
            'metadata_status' => 'enriched',
            'source_url' => 'https://www.wikidata.org/wiki/' . $externalId,
            'imdb_id' => $imdb,
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
            'checksum' => sha1($binary),
        ];
    }
}
