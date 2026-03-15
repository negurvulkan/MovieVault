<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\UserRepository;
use RuntimeException;

final class MigrationService
{
    private const MIGRATIONS_TABLE = 'schema_migrations';

    public function __construct(
        private readonly Config $config,
        private readonly Database $db,
        private readonly UserRepository $users
    ) {
    }

    public function databaseExists(): bool
    {
        return is_file($this->databasePath());
    }

    public function isInstalledDatabase(): bool
    {
        if (!$this->databaseExists()) {
            return false;
        }

        try {
            return $this->db->isInstalled();
        } catch (\Throwable) {
            return false;
        }
    }

    public function ensureCurrentSchema(): array
    {
        if (!$this->databaseExists() || !$this->isInstalledDatabase()) {
            return [];
        }

        return $this->runPendingMigrations();
    }

    public function installSchema(): array
    {
        return $this->runPendingMigrations();
    }

    private function runPendingMigrations(): array
    {
        $lockHandle = $this->acquireLock();

        try {
            $this->ensureMigrationsTable();
            $applied = $this->appliedMigrationNames();
            $appliedNow = [];

            foreach ($this->migrationFiles() as $file) {
                $name = basename($file);
                if (in_array($name, $applied, true)) {
                    continue;
                }

                $sql = file_get_contents($file);
                if (!is_string($sql)) {
                    throw new RuntimeException('Migration konnte nicht gelesen werden: ' . $name);
                }

                if (trim($sql) !== '') {
                    $this->db->pdo()->exec($sql);
                }

                $this->db->execute(
                    'INSERT INTO ' . self::MIGRATIONS_TABLE . ' (name, applied_at) VALUES (:name, :applied_at)',
                    ['name' => $name, 'applied_at' => $this->now()]
                );
                $appliedNow[] = $name;
            }

            $this->seedPermissionsAndRoles();

            return $appliedNow;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::MIGRATIONS_TABLE . ' (
                name TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL
            )'
        );
    }

    private function appliedMigrationNames(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT name FROM ' . self::MIGRATIONS_TABLE . ' ORDER BY name'
        );

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    private function migrationFiles(): array
    {
        $files = glob(__DIR__ . '/../../database/migrations/*.sql') ?: [];
        sort($files);
        return $files;
    }

    private function seedPermissionsAndRoles(): void
    {
        $permissions = require __DIR__ . '/../../config/permissions.php';
        $this->users->seedPermissions($permissions);
        $this->users->ensureSystemRoles();
    }

    private function acquireLock()
    {
        $lockDirectory = dirname($this->databasePath());
        if (!is_dir($lockDirectory)) {
            mkdir($lockDirectory, 0777, true);
        }

        $handle = fopen($lockDirectory . DIRECTORY_SEPARATOR . 'schema-update.lock', 'c+');
        if ($handle === false) {
            throw new RuntimeException('Schema-Lockdatei konnte nicht erstellt werden.');
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException('Schema-Lock konnte nicht gesetzt werden.');
        }

        return $handle;
    }

    private function databasePath(): string
    {
        return (string) $this->config->get('database.path');
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
