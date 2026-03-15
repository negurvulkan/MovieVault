<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class WatchRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function listEvents(int $userId, array $filters = []): array
    {
        $sql = 'SELECT we.*, ct.title, ct.kind, ct.runtime_minutes, ct.season_number, s.title AS series_title
                FROM watch_events we
                INNER JOIN catalog_titles ct ON ct.id = we.catalog_title_id
                LEFT JOIN series s ON s.id = ct.series_id
                WHERE we.user_id = :user_id';
        $params = ['user_id' => $userId];

        if (!empty($filters['q'])) {
            $sql .= ' AND (ct.title LIKE :search OR s.title LIKE :search OR COALESCE(we.notes, \'\') LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['q']) . '%';
        }

        $sql .= ' ORDER BY we.watched_at DESC, we.id DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function addEvent(int $userId, int $titleId, string $watchedAt, ?string $notes): int
    {
        $this->db->execute(
            'INSERT INTO watch_events (user_id, catalog_title_id, watched_at, notes, created_at)
             VALUES (:user_id, :catalog_title_id, :watched_at, :notes, :created_at)',
            [
                'user_id' => $userId,
                'catalog_title_id' => $titleId,
                'watched_at' => $watchedAt,
                'notes' => $notes,
                'created_at' => $this->now(),
            ]
        );

        return $this->db->lastInsertId();
    }

    public function deleteEvent(int $eventId, int $userId): void
    {
        $this->db->execute(
            'DELETE FROM watch_events WHERE id = :id AND user_id = :user_id',
            ['id' => $eventId, 'user_id' => $userId]
        );
    }

    public function findEventsByIds(array $ids, int $userId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->pdo()->prepare(
            'SELECT we.*, ct.title, ct.kind, ct.runtime_minutes, ct.season_number, s.title AS series_title
             FROM watch_events we
             INNER JOIN catalog_titles ct ON ct.id = we.catalog_title_id
             LEFT JOIN series s ON s.id = ct.series_id
             WHERE we.user_id = ? AND we.id IN (' . $placeholders . ')
             ORDER BY we.watched_at DESC, we.id DESC'
        );
        $statement->execute(array_merge([$userId], $ids));

        return $statement->fetchAll() ?: [];
    }

    public function deleteEvents(array $ids, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->pdo()->prepare(
            'DELETE FROM watch_events WHERE user_id = ? AND id IN (' . $placeholders . ')'
        );
        $statement->execute(array_merge([$userId], $ids));

        return $statement->rowCount();
    }

    public function lastEvent(int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT we.*, ct.title, ct.kind, ct.runtime_minutes, ct.season_number, s.title AS series_title
             FROM watch_events we
             INNER JOIN catalog_titles ct ON ct.id = we.catalog_title_id
             LEFT JOIN series s ON s.id = ct.series_id
             WHERE we.user_id = :user_id
             ORDER BY we.watched_at DESC, we.id DESC
             LIMIT 1',
            ['user_id' => $userId]
        );
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
