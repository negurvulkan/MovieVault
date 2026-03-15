<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class WatchRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function listEvents(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT we.*, ct.title, ct.kind, ct.runtime_minutes, ct.season_number, s.title AS series_title
             FROM watch_events we
             INNER JOIN catalog_titles ct ON ct.id = we.catalog_title_id
             LEFT JOIN series s ON s.id = ct.series_id
             WHERE we.user_id = :user_id
             ORDER BY we.watched_at DESC, we.id DESC',
            ['user_id' => $userId]
        );
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
