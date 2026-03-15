<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\Text;

final class CatalogRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function listSeries(array $filters = []): array
    {
        $sql = 'SELECT series.*,
                       (SELECT COUNT(*) FROM catalog_titles WHERE series_id = series.id) AS season_count
                FROM series';
        $conditions = [];
        $params = [];

        if (!empty($filters['q'])) {
            $conditions[] = '(series.title LIKE :search OR series.original_title LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['q']) . '%';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY lower(sort_title)';

        return $this->db->fetchAll($sql, $params);
    }

    public function findSeries(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM series WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function findSeriesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->pdo()->prepare(
            'SELECT series.*,
                    (SELECT COUNT(*) FROM catalog_titles WHERE series_id = series.id) AS season_count
             FROM series
             WHERE series.id IN (' . $placeholders . ')
             ORDER BY lower(sort_title)'
        );
        $statement->execute($ids);

        return $statement->fetchAll() ?: [];
    }

    public function saveSeries(array $data, ?int $id = null): int
    {
        $payload = [
            'title' => trim((string) ($data['title'] ?? '')),
            'original_title' => trim((string) ($data['original_title'] ?? '')) ?: null,
            'sort_title' => Text::sortable((string) ($data['title'] ?? '')),
            'year_start' => $this->nullableInt($data['year_start'] ?? null),
            'year_end' => $this->nullableInt($data['year_end'] ?? null),
            'overview' => trim((string) ($data['overview'] ?? '')) ?: null,
            'poster_path' => $data['poster_path'] ?? null,
            'updated_at' => $this->now(),
        ];

        if ($id === null) {
            $payload['created_at'] = $this->now();
            $this->db->execute(
                'INSERT INTO series (title, original_title, sort_title, year_start, year_end, overview, poster_path, created_at, updated_at)
                 VALUES (:title, :original_title, :sort_title, :year_start, :year_end, :overview, :poster_path, :created_at, :updated_at)',
                $payload
            );
            return $this->db->lastInsertId();
        }

        $payload['id'] = $id;
        $this->db->execute(
            'UPDATE series
             SET title = :title, original_title = :original_title, sort_title = :sort_title, year_start = :year_start,
                 year_end = :year_end, overview = :overview, poster_path = :poster_path, updated_at = :updated_at
             WHERE id = :id',
            $payload
        );

        return $id;
    }

    public function createSeriesFromTitle(int $titleId): int
    {
        $title = $this->findTitle($titleId);
        if (!$title) {
            throw new \RuntimeException('Titel nicht gefunden.');
        }

        if (!empty($title['series_id'])) {
            return (int) $title['series_id'];
        }

        foreach ($this->listSeries() as $series) {
            if (strtolower((string) $series['title']) === strtolower((string) $title['title'])) {
                $this->db->execute(
                    'UPDATE catalog_titles SET series_id = :series_id, updated_at = :updated_at WHERE id = :id',
                    ['series_id' => $series['id'], 'updated_at' => $this->now(), 'id' => $titleId]
                );

                return (int) $series['id'];
            }
        }

        $seriesId = $this->saveSeries([
            'title' => $title['title'],
            'original_title' => $title['original_title'] ?? null,
            'year_start' => $title['year'] ?? null,
            'year_end' => $title['year'] ?? null,
            'overview' => $title['overview'] ?? null,
            'poster_path' => $title['poster_path'] ?? null,
        ]);

        $this->db->execute(
            'UPDATE catalog_titles SET series_id = :series_id, updated_at = :updated_at WHERE id = :id',
            ['series_id' => $seriesId, 'updated_at' => $this->now(), 'id' => $titleId]
        );

        return $seriesId;
    }

    public function allTitles(array $filters = [], ?int $userId = null): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['q'])) {
            $conditions[] = '(ct.title LIKE :search OR ct.original_title LIKE :search OR s.title LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['kind'])) {
            $conditions[] = 'ct.kind = :kind';
            $params['kind'] = $filters['kind'];
        }

        if (!empty($filters['genre'])) {
            $conditions[] = 'EXISTS (
                SELECT 1 FROM title_genres tg
                INNER JOIN genres g ON g.id = tg.genre_id
                WHERE tg.catalog_title_id = ct.id AND g.slug = :genre
            )';
            $params['genre'] = Text::slug((string) $filters['genre']);
        }

        if (!empty($filters['watch_filter']) && $userId !== null) {
            if ($filters['watch_filter'] === 'watched') {
                $conditions[] = 'EXISTS (SELECT 1 FROM watch_events we WHERE we.catalog_title_id = ct.id AND we.user_id = :watch_user_id)';
            } elseif ($filters['watch_filter'] === 'unwatched') {
                $conditions[] = 'NOT EXISTS (SELECT 1 FROM watch_events we WHERE we.catalog_title_id = ct.id AND we.user_id = :watch_user_id)';
            }
            $params['watch_user_id'] = $userId;
        }

        $sql = 'SELECT ct.*,
                       s.title AS series_title,
                       (SELECT COUNT(*) FROM copies c WHERE c.catalog_title_id = ct.id) AS copies_count';
        if ($userId !== null) {
            $sql .= ',
                       EXISTS(SELECT 1 FROM watch_events we WHERE we.catalog_title_id = ct.id AND we.user_id = :current_user) AS watched';
            $params['current_user'] = $userId;
        } else {
            $sql .= ', 0 AS watched';
        }

        $sql .= ' FROM catalog_titles ct
                  LEFT JOIN series s ON s.id = ct.series_id';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY lower(ct.sort_title), ct.year DESC, ct.season_number';

        $titles = $this->db->fetchAll($sql, $params);
        return $this->attachGenres($titles);
    }

    public function findTitlesByIds(array $ids, ?int $userId = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids;
        $sql = 'SELECT ct.*,
                       s.title AS series_title,
                       (SELECT COUNT(*) FROM copies c WHERE c.catalog_title_id = ct.id) AS copies_count';
        if ($userId !== null) {
            $sql .= ',
                       EXISTS(SELECT 1 FROM watch_events we WHERE we.catalog_title_id = ct.id AND we.user_id = ?) AS watched';
            $params = array_merge([$userId], $ids);
        } else {
            $sql .= ', 0 AS watched';
        }

        $sql .= ' FROM catalog_titles ct
                  LEFT JOIN series s ON s.id = ct.series_id
                  WHERE ct.id IN (' . $placeholders . ')
                  ORDER BY lower(ct.sort_title), ct.year DESC, ct.season_number';

        $statement = $this->db->pdo()->prepare($sql);
        $statement->execute($params);
        $titles = $statement->fetchAll() ?: [];

        return $this->attachGenres($titles);
    }

    public function allGenres(): array
    {
        return $this->db->fetchAll('SELECT * FROM genres ORDER BY lower(name)');
    }

    public function findTitle(int $id): ?array
    {
        $title = $this->db->fetchOne(
            'SELECT ct.*, s.title AS series_title
             FROM catalog_titles ct
             LEFT JOIN series s ON s.id = ct.series_id
             WHERE ct.id = :id
             LIMIT 1',
            ['id' => $id]
        );

        if (!$title) {
            return null;
        }

        $title = $this->attachGenres([$title])[0];
        $title['copies'] = $this->copiesForTitle($id);
        $title['external_refs'] = $this->db->fetchAll(
            'SELECT * FROM external_refs WHERE catalog_title_id = :catalog_title_id ORDER BY provider',
            ['catalog_title_id' => $id]
        );

        return $title;
    }

    public function titlesForSelect(): array
    {
        $titles = $this->db->fetchAll(
            'SELECT ct.id, ct.kind, ct.title, ct.year, ct.season_number, s.title AS series_title
             FROM catalog_titles ct
             LEFT JOIN series s ON s.id = ct.series_id
             ORDER BY lower(ct.sort_title), ct.year DESC'
        );

        foreach ($titles as &$title) {
            $title['label'] = $title['kind'] === 'season'
                ? sprintf('%s - Staffel %s', $title['series_title'] ?: $title['title'], $title['season_number'])
                : sprintf('%s%s', $title['title'], $title['year'] ? ' (' . $title['year'] . ')' : '');
        }

        return $titles;
    }

    public function saveTitle(array $data, ?int $id = null): int
    {
        $payload = [
            'kind' => $data['kind'],
            'title' => trim((string) $data['title']),
            'original_title' => trim((string) ($data['original_title'] ?? '')) ?: null,
            'sort_title' => Text::sortable((string) $data['title']),
            'year' => $this->nullableInt($data['year'] ?? null),
            'series_id' => $this->nullableInt($data['series_id'] ?? null),
            'season_number' => $this->nullableInt($data['season_number'] ?? null),
            'overview' => trim((string) ($data['overview'] ?? '')) ?: null,
            'runtime_minutes' => $this->nullableInt($data['runtime_minutes'] ?? null),
            'poster_path' => $data['poster_path'] ?? null,
            'metadata_status' => $data['metadata_status'] ?? 'manual',
            'updated_at' => $this->now(),
        ];

        return $this->db->transaction(function () use ($payload, $id, $data): int {
            if ($id === null) {
                $payload['created_at'] = $this->now();
                $this->db->execute(
                    'INSERT INTO catalog_titles (kind, title, original_title, sort_title, year, series_id, season_number, overview, runtime_minutes, poster_path, metadata_status, created_at, updated_at)
                     VALUES (:kind, :title, :original_title, :sort_title, :year, :series_id, :season_number, :overview, :runtime_minutes, :poster_path, :metadata_status, :created_at, :updated_at)',
                    $payload
                );
                $id = $this->db->lastInsertId();
            } else {
                $payload['id'] = $id;
                $this->db->execute(
                    'UPDATE catalog_titles
                     SET kind = :kind, title = :title, original_title = :original_title, sort_title = :sort_title, year = :year,
                         series_id = :series_id, season_number = :season_number, overview = :overview,
                         runtime_minutes = :runtime_minutes, poster_path = :poster_path, metadata_status = :metadata_status,
                         updated_at = :updated_at
                     WHERE id = :id',
                    $payload
                );
            }

            $this->syncGenres($id, $data['genres'] ?? []);

            return $id;
        });
    }

    public function syncGenres(int $titleId, array $genres): void
    {
        $names = array_values(array_unique(array_filter(array_map(
            static fn (mixed $genre): string => trim((string) $genre),
            $genres
        ))));

        $this->db->execute('DELETE FROM title_genres WHERE catalog_title_id = :catalog_title_id', ['catalog_title_id' => $titleId]);

        foreach ($names as $name) {
            $slug = Text::slug($name);
            $this->db->execute(
                'INSERT INTO genres (name, slug) VALUES (:name, :slug)
                 ON CONFLICT(slug) DO UPDATE SET name = excluded.name',
                ['name' => $name, 'slug' => $slug]
            );
            $genre = $this->db->fetchOne('SELECT id FROM genres WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
            if ($genre) {
                $this->db->execute(
                    'INSERT INTO title_genres (catalog_title_id, genre_id) VALUES (:catalog_title_id, :genre_id)',
                    ['catalog_title_id' => $titleId, 'genre_id' => $genre['id']]
                );
            }
        }
    }

    public function copiesForTitle(int $titleId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM copies WHERE catalog_title_id = :catalog_title_id ORDER BY media_format, lower(COALESCE(edition, \'\'))',
            ['catalog_title_id' => $titleId]
        );
    }

    public function storeCopy(int $titleId, array $data): int
    {
        $this->db->execute(
            'INSERT INTO copies (catalog_title_id, media_format, edition, barcode, item_condition, storage_location, notes, created_at, updated_at)
             VALUES (:catalog_title_id, :media_format, :edition, :barcode, :item_condition, :storage_location, :notes, :created_at, :updated_at)',
            [
                'catalog_title_id' => $titleId,
                'media_format' => $data['media_format'],
                'edition' => trim((string) ($data['edition'] ?? '')) ?: null,
                'barcode' => trim((string) ($data['barcode'] ?? '')) ?: null,
                'item_condition' => trim((string) ($data['item_condition'] ?? '')) ?: null,
                'storage_location' => trim((string) ($data['storage_location'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]
        );

        return $this->db->lastInsertId();
    }

    public function updateCopy(int $copyId, array $data): void
    {
        $this->db->execute(
            'UPDATE copies
             SET media_format = :media_format, edition = :edition, barcode = :barcode, item_condition = :item_condition,
                 storage_location = :storage_location, notes = :notes, updated_at = :updated_at
             WHERE id = :id',
            [
                'id' => $copyId,
                'media_format' => $data['media_format'],
                'edition' => trim((string) ($data['edition'] ?? '')) ?: null,
                'barcode' => trim((string) ($data['barcode'] ?? '')) ?: null,
                'item_condition' => trim((string) ($data['item_condition'] ?? '')) ?: null,
                'storage_location' => trim((string) ($data['storage_location'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'updated_at' => $this->now(),
            ]
        );
    }

    public function findCopy(int $copyId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM copies WHERE id = :id LIMIT 1', ['id' => $copyId]);
    }

    public function deleteTitles(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->pdo()->prepare(
            'DELETE FROM catalog_titles WHERE id IN (' . $placeholders . ')'
        );
        $statement->execute($ids);

        return $statement->rowCount();
    }

    public function deleteSeries(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->pdo()->prepare(
            'DELETE FROM series WHERE id IN (' . $placeholders . ')'
        );
        $statement->execute($ids);

        return $statement->rowCount();
    }

    public function findTitleByExternal(string $provider, string $externalId): ?array
    {
        $title = $this->db->fetchOne(
            'SELECT ct.* FROM catalog_titles ct
             INNER JOIN external_refs er ON er.catalog_title_id = ct.id
             WHERE er.provider = :provider AND er.external_id = :external_id
             LIMIT 1',
            ['provider' => $provider, 'external_id' => $externalId]
        );

        return $title ? $this->findTitle((int) $title['id']) : null;
    }

    public function findDuplicateTitle(array $data): ?array
    {
        if (!empty($data['external_id']) && !empty($data['provider'])) {
            $external = $this->findTitleByExternal((string) $data['provider'], (string) $data['external_id']);
            if ($external) {
                return $external;
            }
        }

        $sql = 'SELECT * FROM catalog_titles
                WHERE kind = :kind AND lower(title) = lower(:title) AND COALESCE(year, 0) = COALESCE(:year, 0)';
        $params = [
            'kind' => $data['kind'],
            'title' => trim((string) $data['title']),
            'year' => $this->nullableInt($data['year'] ?? null),
        ];

        if (($data['kind'] ?? 'movie') === 'season') {
            $sql .= ' AND COALESCE(series_id, 0) = COALESCE(:series_id, 0) AND COALESCE(season_number, 0) = COALESCE(:season_number, 0)';
            $params['series_id'] = $this->nullableInt($data['series_id'] ?? null);
            $params['season_number'] = $this->nullableInt($data['season_number'] ?? null);
        }

        $sql .= ' LIMIT 1';

        $title = $this->db->fetchOne($sql, $params);
        return $title ? $this->findTitle((int) $title['id']) : null;
    }

    public function findDuplicateCopy(int $titleId, array $data): ?array
    {
        if (!empty($data['barcode'])) {
            return $this->db->fetchOne(
                'SELECT * FROM copies WHERE barcode = :barcode LIMIT 1',
                ['barcode' => trim((string) $data['barcode'])]
            );
        }

        return $this->db->fetchOne(
            'SELECT * FROM copies
             WHERE catalog_title_id = :catalog_title_id
               AND media_format = :media_format
               AND COALESCE(edition, \'\') = COALESCE(:edition, \'\')
               AND COALESCE(storage_location, \'\') = COALESCE(:storage_location, \'\')
             LIMIT 1',
            [
                'catalog_title_id' => $titleId,
                'media_format' => $data['media_format'],
                'edition' => trim((string) ($data['edition'] ?? '')) ?: null,
                'storage_location' => trim((string) ($data['storage_location'] ?? '')) ?: null,
            ]
        );
    }

    public function upsertExternalRef(int $titleId, string $provider, string $externalId, ?string $sourceUrl, ?string $payloadJson): void
    {
        $now = $this->now();
        $this->db->execute(
            'INSERT INTO external_refs (catalog_title_id, provider, external_id, source_url, payload_json, last_synced_at, created_at, updated_at)
             VALUES (:catalog_title_id, :provider, :external_id, :source_url, :payload_json, :last_synced_at, :created_at, :updated_at)
             ON CONFLICT(catalog_title_id, provider)
             DO UPDATE SET external_id = excluded.external_id, source_url = excluded.source_url,
                           payload_json = excluded.payload_json, last_synced_at = excluded.last_synced_at,
                           updated_at = excluded.updated_at',
            [
                'catalog_title_id' => $titleId,
                'provider' => $provider,
                'external_id' => $externalId,
                'source_url' => $sourceUrl,
                'payload_json' => $payloadJson,
                'last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function setPoster(int $titleId, string $provider, string $remoteUrl, string $localPath, ?string $checksum): void
    {
        $now = $this->now();
        $this->db->execute(
            'UPDATE catalog_titles SET poster_path = :poster_path, updated_at = :updated_at WHERE id = :id',
            ['poster_path' => $localPath, 'updated_at' => $now, 'id' => $titleId]
        );
        $this->db->execute(
            'INSERT INTO poster_cache (catalog_title_id, provider, remote_url, local_path, checksum, downloaded_at, created_at)
             VALUES (:catalog_title_id, :provider, :remote_url, :local_path, :checksum, :downloaded_at, :created_at)
             ON CONFLICT(catalog_title_id, provider)
             DO UPDATE SET remote_url = excluded.remote_url, local_path = excluded.local_path,
                           checksum = excluded.checksum, downloaded_at = excluded.downloaded_at',
            [
                'catalog_title_id' => $titleId,
                'provider' => $provider,
                'remote_url' => $remoteUrl,
                'local_path' => $localPath,
                'checksum' => $checksum,
                'downloaded_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function applyMetadata(int $titleId, array $metadata, bool $overwrite = false): void
    {
        $existing = $this->findTitle($titleId);
        if (!$existing) {
            return;
        }

        $payload = [
            'title' => $overwrite || empty($existing['title']) ? ($metadata['title'] ?? $existing['title']) : $existing['title'],
            'original_title' => $overwrite || empty($existing['original_title']) ? ($metadata['original_title'] ?? $existing['original_title']) : $existing['original_title'],
            'year' => $overwrite || empty($existing['year']) ? ($metadata['year'] ?? $existing['year']) : $existing['year'],
            'overview' => $overwrite || empty($existing['overview']) ? ($metadata['overview'] ?? $existing['overview']) : $existing['overview'],
            'runtime_minutes' => $overwrite || empty($existing['runtime_minutes']) ? ($metadata['runtime_minutes'] ?? $existing['runtime_minutes']) : $existing['runtime_minutes'],
            'poster_path' => $overwrite || empty($existing['poster_path']) ? ($metadata['poster_path'] ?? $existing['poster_path']) : $existing['poster_path'],
            'metadata_status' => $metadata['metadata_status'] ?? 'enriched',
            'kind' => $existing['kind'],
            'series_id' => $existing['series_id'],
            'season_number' => $existing['season_number'],
        ];

        $payload['genres'] = $overwrite
            ? ($metadata['genres'] ?? $existing['genres'])
            : (empty($existing['genres']) ? ($metadata['genres'] ?? []) : $existing['genres']);

        $this->saveTitle($payload, $titleId);
    }

    public function titlesForRecommendation(?string $genreSlug, int $userId, string $filter = 'unwatched'): array
    {
        $conditions = ['EXISTS (SELECT 1 FROM copies c WHERE c.catalog_title_id = ct.id)'];
        $params = ['user_id' => $userId];

        if ($filter === 'unwatched') {
            $conditions[] = 'NOT EXISTS (SELECT 1 FROM watch_events we WHERE we.catalog_title_id = ct.id AND we.user_id = :user_id)';
        }

        if ($genreSlug) {
            $conditions[] = 'EXISTS (
                SELECT 1 FROM title_genres tg
                INNER JOIN genres g ON g.id = tg.genre_id
                WHERE tg.catalog_title_id = ct.id AND g.slug = :genre_slug
            )';
            $params['genre_slug'] = $genreSlug;
        }

        $titles = $this->db->fetchAll(
            'SELECT ct.*, s.title AS series_title
             FROM catalog_titles ct
             LEFT JOIN series s ON s.id = ct.series_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY lower(ct.sort_title)',
            $params
        );

        return $this->attachGenres($titles);
    }

    public function snapshotForDashboard(int $userId): array
    {
        return [
            'title_count' => (int) ($this->db->fetchOne('SELECT COUNT(*) AS value FROM catalog_titles')['value'] ?? 0),
            'copy_count' => (int) ($this->db->fetchOne('SELECT COUNT(*) AS value FROM copies')['value'] ?? 0),
            'movie_count' => (int) ($this->db->fetchOne("SELECT COUNT(*) AS value FROM catalog_titles WHERE kind = 'movie'")['value'] ?? 0),
            'season_count' => (int) ($this->db->fetchOne("SELECT COUNT(*) AS value FROM catalog_titles WHERE kind = 'season'")['value'] ?? 0),
            'watched_count' => (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS value FROM watch_events WHERE user_id = :user_id',
                ['user_id' => $userId]
            )['value'] ?? 0),
            'unwatched_count' => (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS value
                 FROM catalog_titles ct
                 WHERE NOT EXISTS (
                    SELECT 1 FROM watch_events we WHERE we.catalog_title_id = ct.id AND we.user_id = :user_id
                 )',
                ['user_id' => $userId]
            )['value'] ?? 0),
            'watchtime_minutes' => (int) ($this->db->fetchOne(
                'SELECT COALESCE(SUM(ct.runtime_minutes), 0) AS value
                 FROM watch_events we
                 INNER JOIN catalog_titles ct ON ct.id = we.catalog_title_id
                 WHERE we.user_id = :user_id',
                ['user_id' => $userId]
            )['value'] ?? 0),
            'metadata_coverage' => (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS value FROM catalog_titles WHERE metadata_status <> :status',
                ['status' => 'manual']
            )['value'] ?? 0),
        ];
    }

    public function recentTitles(int $limit = 5): array
    {
        $titles = $this->db->fetchAll(
            'SELECT ct.*, s.title AS series_title
             FROM catalog_titles ct
             LEFT JOIN series s ON s.id = ct.series_id
             ORDER BY ct.created_at DESC
             LIMIT ' . (int) $limit
        );

        return $this->attachGenres($titles);
    }

    public function topGenres(int $limit = 6): array
    {
        return $this->db->fetchAll(
            'SELECT g.name, COUNT(*) AS count
             FROM genres g
             INNER JOIN title_genres tg ON tg.genre_id = g.id
             GROUP BY g.id, g.name
             ORDER BY count DESC, lower(g.name)
             LIMIT ' . (int) $limit
        );
    }

    private function attachGenres(array $titles): array
    {
        if ($titles === []) {
            return [];
        }

        $ids = array_map(static fn (array $title): int => (int) $title['id'], $titles);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->pdo()->prepare(
            'SELECT tg.catalog_title_id, g.name, g.slug
             FROM title_genres tg
             INNER JOIN genres g ON g.id = tg.genre_id
             WHERE tg.catalog_title_id IN (' . $placeholders . ')
             ORDER BY lower(g.name)'
        );
        $rows->execute($ids);
        $genreRows = $rows->fetchAll() ?: [];

        $genresByTitle = [];
        foreach ($genreRows as $row) {
            $genresByTitle[$row['catalog_title_id']][] = $row;
        }

        foreach ($titles as &$title) {
            $title['genres'] = array_map(
                static fn (array $genre): string => $genre['name'],
                $genresByTitle[$title['id']] ?? []
            );
            $title['genre_slugs'] = array_map(
                static fn (array $genre): string => $genre['slug'],
                $genresByTitle[$title['id']] ?? []
            );
        }

        return $titles;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
