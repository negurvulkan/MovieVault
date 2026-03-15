<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\Text;

final class WishlistRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function listLists(int $userId): array
    {
        $lists = $this->db->fetchAll(
            'SELECT wl.*,
                    creator.display_name AS created_by_name,
                    COUNT(wlm.user_id) AS member_count,
                    (SELECT COUNT(*) FROM wish_items wi WHERE wi.wish_list_id = wl.id AND wi.status IN (\'open\', \'reserved\')) AS active_items_count
             FROM wish_lists wl
             LEFT JOIN users creator ON creator.id = wl.created_by
             LEFT JOIN wish_list_members wlm ON wlm.wish_list_id = wl.id
             WHERE EXISTS (
                 SELECT 1
                 FROM wish_list_members access
                 WHERE access.wish_list_id = wl.id AND access.user_id = :user_id
             )
             GROUP BY wl.id
             ORDER BY lower(wl.name)',
            ['user_id' => $userId]
        );

        return $this->attachMembers($lists);
    }

    public function findList(int $listId, int $userId): ?array
    {
        $list = $this->db->fetchOne(
            'SELECT wl.*,
                    creator.display_name AS created_by_name,
                    COUNT(wlm.user_id) AS member_count,
                    (SELECT COUNT(*) FROM wish_items wi WHERE wi.wish_list_id = wl.id AND wi.status IN (\'open\', \'reserved\')) AS active_items_count
             FROM wish_lists wl
             LEFT JOIN users creator ON creator.id = wl.created_by
             LEFT JOIN wish_list_members wlm ON wlm.wish_list_id = wl.id
             WHERE wl.id = :id
               AND EXISTS (
                   SELECT 1
                   FROM wish_list_members access
                   WHERE access.wish_list_id = wl.id AND access.user_id = :user_id
               )
             GROUP BY wl.id
             LIMIT 1',
            ['id' => $listId, 'user_id' => $userId]
        );

        if (!$list) {
            return null;
        }

        return $this->attachMembers([$list])[0];
    }

    public function accessibleListIds(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT wish_list_id
             FROM wish_list_members
             WHERE user_id = :user_id
             ORDER BY wish_list_id',
            ['user_id' => $userId]
        );

        return array_map(static fn (array $row): int => (int) $row['wish_list_id'], $rows);
    }

    public function saveList(array $data, int $currentUserId, ?int $listId = null): int
    {
        $memberIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($data['member_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));
        if (!in_array($currentUserId, $memberIds, true)) {
            $memberIds[] = $currentUserId;
        }

        return $this->db->transaction(function () use ($data, $currentUserId, $listId, $memberIds): int {
            $payload = [
                'name' => trim((string) ($data['name'] ?? '')),
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'updated_at' => $this->now(),
            ];

            if ($listId === null) {
                $payload['created_by'] = $currentUserId;
                $payload['created_at'] = $this->now();
                $this->db->execute(
                    'INSERT INTO wish_lists (name, description, created_by, created_at, updated_at)
                     VALUES (:name, :description, :created_by, :created_at, :updated_at)',
                    $payload
                );
                $listId = $this->db->lastInsertId();
            } else {
                $payload['id'] = $listId;
                $this->db->execute(
                    'UPDATE wish_lists
                     SET name = :name,
                         description = :description,
                         updated_at = :updated_at
                     WHERE id = :id',
                    $payload
                );
                $this->db->execute('DELETE FROM wish_list_members WHERE wish_list_id = :wish_list_id', [
                    'wish_list_id' => $listId,
                ]);
            }

            foreach ($memberIds as $memberId) {
                $this->db->execute(
                    'INSERT INTO wish_list_members (wish_list_id, user_id, created_at)
                     VALUES (:wish_list_id, :user_id, :created_at)',
                    [
                        'wish_list_id' => $listId,
                        'user_id' => $memberId,
                        'created_at' => $this->now(),
                    ]
                );
            }

            return $listId;
        });
    }

    public function listItems(int $userId, array $filters = []): array
    {
        $conditions = [
            'EXISTS (
                SELECT 1
                FROM wish_list_members access
                WHERE access.wish_list_id = wi.wish_list_id AND access.user_id = :user_id
            )',
        ];
        $params = ['user_id' => $userId];

        if (!empty($filters['q'])) {
            $conditions[] = '(wi.title LIKE :search OR wi.original_title LIKE :search OR COALESCE(wi.series_title, \'\') LIKE :search OR COALESCE(wi.notes, \'\') LIKE :search OR wl.name LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['list_id'])) {
            $conditions[] = 'wi.wish_list_id = :wish_list_id';
            $params['wish_list_id'] = (int) $filters['list_id'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'wi.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $conditions[] = 'wi.priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['target_format'])) {
            $conditions[] = 'wi.target_format = :target_format';
            $params['target_format'] = $filters['target_format'];
        }

        if (!empty($filters['kind'])) {
            $conditions[] = 'wi.kind = :kind';
            $params['kind'] = $filters['kind'];
        }

        $items = $this->db->fetchAll(
            'SELECT wi.*,
                    wl.name AS list_name,
                    creator.display_name AS created_by_name,
                    reserved.display_name AS reserved_by_name,
                    ct.title AS converted_title
             FROM wish_items wi
             INNER JOIN wish_lists wl ON wl.id = wi.wish_list_id
             LEFT JOIN users creator ON creator.id = wi.created_by
             LEFT JOIN users reserved ON reserved.id = wi.reserved_by
             LEFT JOIN catalog_titles ct ON ct.id = wi.converted_catalog_title_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY
                 CASE wi.status
                     WHEN \'open\' THEN 0
                     WHEN \'reserved\' THEN 1
                     WHEN \'bought\' THEN 2
                     ELSE 3
                 END,
                 CASE wi.priority
                     WHEN \'high\' THEN 0
                     WHEN \'medium\' THEN 1
                     ELSE 2
                 END,
                 lower(wi.sort_title),
                 wi.created_at DESC',
            $params
        );

        return $this->hydrateItems($items);
    }

    public function findItem(int $itemId, int $userId): ?array
    {
        $item = $this->db->fetchOne(
            'SELECT wi.*,
                    wl.name AS list_name,
                    creator.display_name AS created_by_name,
                    reserved.display_name AS reserved_by_name,
                    ct.title AS converted_title
             FROM wish_items wi
             INNER JOIN wish_lists wl ON wl.id = wi.wish_list_id
             LEFT JOIN users creator ON creator.id = wi.created_by
             LEFT JOIN users reserved ON reserved.id = wi.reserved_by
             LEFT JOIN catalog_titles ct ON ct.id = wi.converted_catalog_title_id
             WHERE wi.id = :id
               AND EXISTS (
                   SELECT 1
                   FROM wish_list_members access
                   WHERE access.wish_list_id = wi.wish_list_id AND access.user_id = :user_id
               )
             LIMIT 1',
            ['id' => $itemId, 'user_id' => $userId]
        );

        if (!$item) {
            return null;
        }

        $item = $this->hydrateItems([$item])[0];
        $item['external_refs'] = $this->db->fetchAll(
            'SELECT * FROM wish_external_refs WHERE wish_item_id = :wish_item_id ORDER BY provider',
            ['wish_item_id' => $itemId]
        );

        return $item;
    }

    public function findItemsByIds(array $ids, int $userId): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->pdo()->prepare(
            'SELECT wi.*,
                    wl.name AS list_name,
                    creator.display_name AS created_by_name,
                    reserved.display_name AS reserved_by_name,
                    ct.title AS converted_title
             FROM wish_items wi
             INNER JOIN wish_lists wl ON wl.id = wi.wish_list_id
             LEFT JOIN users creator ON creator.id = wi.created_by
             LEFT JOIN users reserved ON reserved.id = wi.reserved_by
             LEFT JOIN catalog_titles ct ON ct.id = wi.converted_catalog_title_id
             WHERE wi.id IN (' . $placeholders . ')
               AND EXISTS (
                   SELECT 1
                   FROM wish_list_members access
                   WHERE access.wish_list_id = wi.wish_list_id AND access.user_id = ?
               )
             ORDER BY lower(wi.sort_title), wi.created_at DESC'
        );
        $statement->execute(array_merge($ids, [$userId]));
        $items = $statement->fetchAll() ?: [];

        return $this->hydrateItems($items);
    }

    public function saveItem(array $data, int $currentUserId, ?int $itemId = null): int
    {
        $genres = array_values(array_unique(array_filter(array_map(
            static fn (mixed $genre): string => trim((string) $genre),
            (array) ($data['genres'] ?? [])
        ))));

        return $this->db->transaction(function () use ($data, $currentUserId, $itemId, $genres): int {
            $payload = [
                'wish_list_id' => (int) $data['wish_list_id'],
                'kind' => (string) $data['kind'],
                'title' => trim((string) $data['title']),
                'original_title' => trim((string) ($data['original_title'] ?? '')) ?: null,
                'sort_title' => Text::sortable((string) $data['title']),
                'year' => $this->nullableInt($data['year'] ?? null),
                'series_title' => trim((string) ($data['series_title'] ?? '')) ?: null,
                'season_number' => $this->nullableInt($data['season_number'] ?? null),
                'target_format' => (string) ($data['target_format'] ?? 'dvd'),
                'priority' => (string) ($data['priority'] ?? 'medium'),
                'status' => (string) ($data['status'] ?? 'open'),
                'target_price' => $this->nullableFloat($data['target_price'] ?? null),
                'seen_price' => $this->nullableFloat($data['seen_price'] ?? null),
                'bought_price' => $this->nullableFloat($data['bought_price'] ?? null),
                'store_name' => trim((string) ($data['store_name'] ?? '')) ?: null,
                'location' => trim((string) ($data['location'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'overview' => trim((string) ($data['overview'] ?? '')) ?: null,
                'runtime_minutes' => $this->nullableInt($data['runtime_minutes'] ?? null),
                'poster_path' => trim((string) ($data['poster_path'] ?? '')) ?: null,
                'genres_json' => $genres === [] ? null : json_encode($genres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'metadata_status' => (string) ($data['metadata_status'] ?? 'manual'),
                'reserved_by' => $this->nullableInt($data['reserved_by'] ?? null),
                'converted_catalog_title_id' => $this->nullableInt($data['converted_catalog_title_id'] ?? null),
                'found_at' => trim((string) ($data['found_at'] ?? '')) ?: null,
                'reserved_at' => trim((string) ($data['reserved_at'] ?? '')) ?: null,
                'bought_at' => trim((string) ($data['bought_at'] ?? '')) ?: null,
                'updated_at' => $this->now(),
            ];

            if ($itemId === null) {
                $payload['created_by'] = $currentUserId;
                $payload['created_at'] = $this->now();
                $this->db->execute(
                    'INSERT INTO wish_items (
                        wish_list_id, kind, title, original_title, sort_title, year, series_title, season_number,
                        target_format, priority, status, target_price, seen_price, bought_price, store_name, location,
                        notes, overview, runtime_minutes, poster_path, genres_json, metadata_status, created_by,
                        reserved_by, converted_catalog_title_id, found_at, reserved_at, bought_at, created_at, updated_at
                     ) VALUES (
                        :wish_list_id, :kind, :title, :original_title, :sort_title, :year, :series_title, :season_number,
                        :target_format, :priority, :status, :target_price, :seen_price, :bought_price, :store_name, :location,
                        :notes, :overview, :runtime_minutes, :poster_path, :genres_json, :metadata_status, :created_by,
                        :reserved_by, :converted_catalog_title_id, :found_at, :reserved_at, :bought_at, :created_at, :updated_at
                     )',
                    $payload
                );

                return $this->db->lastInsertId();
            }

            $payload['id'] = $itemId;
            $this->db->execute(
                'UPDATE wish_items
                 SET wish_list_id = :wish_list_id,
                     kind = :kind,
                     title = :title,
                     original_title = :original_title,
                     sort_title = :sort_title,
                     year = :year,
                     series_title = :series_title,
                     season_number = :season_number,
                     target_format = :target_format,
                     priority = :priority,
                     status = :status,
                     target_price = :target_price,
                     seen_price = :seen_price,
                     bought_price = :bought_price,
                     store_name = :store_name,
                     location = :location,
                     notes = :notes,
                     overview = :overview,
                     runtime_minutes = :runtime_minutes,
                     poster_path = :poster_path,
                     genres_json = :genres_json,
                     metadata_status = :metadata_status,
                     reserved_by = :reserved_by,
                     converted_catalog_title_id = :converted_catalog_title_id,
                     found_at = :found_at,
                     reserved_at = :reserved_at,
                     bought_at = :bought_at,
                     updated_at = :updated_at
                 WHERE id = :id',
                $payload
            );

            return $itemId;
        });
    }

    public function deleteItems(array $ids, int $userId): int
    {
        $existingIds = array_map(
            static fn (array $item): int => (int) $item['id'],
            $this->findItemsByIds($ids, $userId)
        );

        if ($existingIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($existingIds), '?'));
        $statement = $this->db->pdo()->prepare('DELETE FROM wish_items WHERE id IN (' . $placeholders . ')');
        $statement->execute($existingIds);

        return $statement->rowCount();
    }

    public function updateStatuses(array $ids, string $status, int $userId): int
    {
        $itemIds = array_map(
            static fn (array $item): int => (int) $item['id'],
            $this->findItemsByIds($ids, $userId)
        );
        if ($itemIds === []) {
            return 0;
        }

        $now = $this->now();
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $params = [$status, $now];
        $sql = 'UPDATE wish_items
                SET status = ?,
                    updated_at = ?';

        if ($status === 'reserved') {
            $sql .= ',
                    reserved_by = ?,
                    reserved_at = COALESCE(reserved_at, ?),
                    found_at = COALESCE(found_at, ?)';
            $params[] = $userId;
            $params[] = $now;
            $params[] = $now;
        } elseif ($status === 'bought') {
            $sql .= ',
                    reserved_by = COALESCE(reserved_by, ?),
                    reserved_at = COALESCE(reserved_at, ?),
                    found_at = COALESCE(found_at, ?),
                    bought_at = COALESCE(bought_at, ?)';
            $params[] = $userId;
            $params[] = $now;
            $params[] = $now;
            $params[] = $now;
        } elseif ($status === 'dropped') {
            $sql .= ',
                    reserved_by = NULL,
                    reserved_at = NULL';
        }

        $sql .= ' WHERE id IN (' . $placeholders . ')';

        $statement = $this->db->pdo()->prepare($sql);
        $statement->execute(array_merge($params, $itemIds));

        return $statement->rowCount();
    }

    public function updatePriority(array $ids, string $priority, int $userId): int
    {
        return $this->updateFieldForAccessibleItems($ids, $userId, 'priority', $priority);
    }

    public function updateTargetFormat(array $ids, string $targetFormat, int $userId): int
    {
        return $this->updateFieldForAccessibleItems($ids, $userId, 'target_format', $targetFormat);
    }

    public function upsertExternalRef(int $itemId, string $provider, string $externalId, ?string $sourceUrl, ?string $payloadJson): void
    {
        $now = $this->now();
        $this->db->execute(
            'INSERT INTO wish_external_refs (wish_item_id, provider, external_id, source_url, payload_json, last_synced_at, created_at, updated_at)
             VALUES (:wish_item_id, :provider, :external_id, :source_url, :payload_json, :last_synced_at, :created_at, :updated_at)
             ON CONFLICT(wish_item_id, provider)
             DO UPDATE SET external_id = excluded.external_id,
                           source_url = excluded.source_url,
                           payload_json = excluded.payload_json,
                           last_synced_at = excluded.last_synced_at,
                           updated_at = excluded.updated_at',
            [
                'wish_item_id' => $itemId,
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

    public function setPosterPath(int $itemId, string $posterPath): void
    {
        $this->db->execute(
            'UPDATE wish_items SET poster_path = :poster_path, updated_at = :updated_at WHERE id = :id',
            [
                'id' => $itemId,
                'poster_path' => $posterPath,
                'updated_at' => $this->now(),
            ]
        );
    }

    public function applyMetadata(int $itemId, array $metadata, bool $overwrite = false): void
    {
        $existing = $this->findItemForMetadata($itemId);
        if (!$existing) {
            return;
        }

        $payload = [
            'wish_list_id' => $existing['wish_list_id'],
            'kind' => $existing['kind'],
            'title' => $overwrite || empty($existing['title']) ? ($metadata['title'] ?? $existing['title']) : $existing['title'],
            'original_title' => $overwrite || empty($existing['original_title']) ? ($metadata['original_title'] ?? $existing['original_title']) : $existing['original_title'],
            'year' => $overwrite || empty($existing['year']) ? ($metadata['year'] ?? $existing['year']) : $existing['year'],
            'series_title' => $existing['series_title'],
            'season_number' => $existing['season_number'],
            'target_format' => $existing['target_format'],
            'priority' => $existing['priority'],
            'status' => $existing['status'],
            'target_price' => $existing['target_price'],
            'seen_price' => $existing['seen_price'],
            'bought_price' => $existing['bought_price'],
            'store_name' => $existing['store_name'],
            'location' => $existing['location'],
            'notes' => $existing['notes'],
            'overview' => $overwrite || empty($existing['overview']) ? ($metadata['overview'] ?? $existing['overview']) : $existing['overview'],
            'runtime_minutes' => $overwrite || empty($existing['runtime_minutes']) ? ($metadata['runtime_minutes'] ?? $existing['runtime_minutes']) : $existing['runtime_minutes'],
            'poster_path' => $overwrite || empty($existing['poster_path']) ? ($metadata['poster_path'] ?? $existing['poster_path']) : $existing['poster_path'],
            'metadata_status' => $metadata['metadata_status'] ?? 'enriched',
            'reserved_by' => $existing['reserved_by'],
            'converted_catalog_title_id' => $existing['converted_catalog_title_id'],
            'found_at' => $existing['found_at'],
            'reserved_at' => $existing['reserved_at'],
            'bought_at' => $existing['bought_at'],
            'genres' => $overwrite
                ? ($metadata['genres'] ?? $existing['genres'])
                : ($existing['genres'] === [] ? ($metadata['genres'] ?? []) : $existing['genres']),
        ];

        $this->saveItem($payload, (int) ($existing['created_by'] ?? 0), $itemId);
    }

    public function markConverted(int $itemId, int $catalogTitleId, int $userId, ?float $boughtPrice = null): void
    {
        $this->db->execute(
            'UPDATE wish_items
             SET status = :status,
                 converted_catalog_title_id = :converted_catalog_title_id,
                 reserved_by = COALESCE(reserved_by, :reserved_by),
                 reserved_at = COALESCE(reserved_at, :reserved_at),
                 found_at = COALESCE(found_at, :found_at),
                 bought_at = COALESCE(bought_at, :bought_at),
                 bought_price = COALESCE(:bought_price, bought_price),
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'id' => $itemId,
                'status' => 'bought',
                'converted_catalog_title_id' => $catalogTitleId,
                'reserved_by' => $userId,
                'reserved_at' => $this->now(),
                'found_at' => $this->now(),
                'bought_at' => $this->now(),
                'bought_price' => $boughtPrice,
                'updated_at' => $this->now(),
            ]
        );
    }

    public function dashboardSnapshot(int $userId): array
    {
        $access = 'EXISTS (
            SELECT 1
            FROM wish_list_members access
            WHERE access.wish_list_id = wi.wish_list_id AND access.user_id = :user_id
        )';

        return [
            'open_count' => (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS value FROM wish_items wi WHERE ' . $access . ' AND wi.status = :status',
                ['user_id' => $userId, 'status' => 'open']
            )['value'] ?? 0),
            'reserved_count' => (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS value FROM wish_items wi WHERE ' . $access . ' AND wi.status = :status',
                ['user_id' => $userId, 'status' => 'reserved']
            )['value'] ?? 0),
            'high_priority_count' => (int) ($this->db->fetchOne(
                'SELECT COUNT(*) AS value
                 FROM wish_items wi
                 WHERE ' . $access . '
                   AND wi.status IN (\'open\', \'reserved\')
                   AND wi.priority = :priority',
                ['user_id' => $userId, 'priority' => 'high']
            )['value'] ?? 0),
            'list_count' => (int) count($this->accessibleListIds($userId)),
        ];
    }

    public function lastBought(int $userId): ?array
    {
        $item = $this->db->fetchOne(
            'SELECT wi.*, wl.name AS list_name
             FROM wish_items wi
             INNER JOIN wish_lists wl ON wl.id = wi.wish_list_id
             WHERE wi.status = :status
               AND EXISTS (
                   SELECT 1
                   FROM wish_list_members access
                   WHERE access.wish_list_id = wi.wish_list_id AND access.user_id = :user_id
               )
             ORDER BY wi.bought_at DESC, wi.updated_at DESC
             LIMIT 1',
            ['status' => 'bought', 'user_id' => $userId]
        );

        if (!$item) {
            return null;
        }

        return $this->hydrateItems([$item])[0];
    }

    private function findItemForMetadata(int $itemId): ?array
    {
        $item = $this->db->fetchOne('SELECT * FROM wish_items WHERE id = :id LIMIT 1', ['id' => $itemId]);
        if (!$item) {
            return null;
        }

        return $this->hydrateItems([$item])[0];
    }

    private function updateFieldForAccessibleItems(array $ids, int $userId, string $field, string $value): int
    {
        $itemIds = array_map(
            static fn (array $item): int => (int) $item['id'],
            $this->findItemsByIds($ids, $userId)
        );
        if ($itemIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $statement = $this->db->pdo()->prepare(
            'UPDATE wish_items SET ' . $field . ' = ?, updated_at = ? WHERE id IN (' . $placeholders . ')'
        );
        $statement->execute(array_merge([$value, $this->now()], $itemIds));

        return $statement->rowCount();
    }

    private function attachMembers(array $lists): array
    {
        if ($lists === []) {
            return [];
        }

        $listIds = array_map(static fn (array $list): int => (int) $list['id'], $lists);
        $placeholders = implode(',', array_fill(0, count($listIds), '?'));
        $statement = $this->db->pdo()->prepare(
            'SELECT wlm.wish_list_id, users.id, users.display_name, users.email
             FROM wish_list_members wlm
             INNER JOIN users ON users.id = wlm.user_id
             WHERE wlm.wish_list_id IN (' . $placeholders . ')
             ORDER BY lower(users.display_name), lower(users.email)'
        );
        $statement->execute($listIds);
        $rows = $statement->fetchAll() ?: [];

        $membersByList = [];
        foreach ($rows as $row) {
            $membersByList[$row['wish_list_id']][] = $row;
        }

        foreach ($lists as &$list) {
            $list['members'] = $membersByList[$list['id']] ?? [];
            $list['member_ids'] = array_map(
                static fn (array $member): int => (int) $member['id'],
                $list['members']
            );
            $list['member_names'] = implode(', ', array_map(
                static fn (array $member): string => (string) $member['display_name'],
                $list['members']
            ));
        }

        return $lists;
    }

    private function hydrateItems(array $items): array
    {
        foreach ($items as &$item) {
            $genres = json_decode((string) ($item['genres_json'] ?? '[]'), true);
            $item['genres'] = is_array($genres) ? array_values(array_filter(array_map('strval', $genres))) : [];
            $item['is_active'] = in_array((string) ($item['status'] ?? ''), ['open', 'reserved'], true);
            $item['is_converted'] = !empty($item['converted_catalog_title_id']);
        }

        return $items;
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
