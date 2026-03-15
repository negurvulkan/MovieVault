<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class SettingRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function allKeyed(): array
    {
        try {
            $rows = $this->db->fetchAll('SELECT key, value FROM settings ORDER BY key');
        } catch (\Throwable) {
            return [];
        }

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return $settings;
    }

    public function upsertMany(array $settings): void
    {
        $now = $this->now();
        foreach ($settings as $key => $value) {
            $this->db->execute(
                'INSERT INTO settings (key, value, created_at, updated_at)
                 VALUES (:key, :value, :created_at, :updated_at)
                 ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at',
                [
                    'key' => $key,
                    'value' => (string) $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
