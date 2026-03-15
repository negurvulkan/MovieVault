<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly string $path)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('Die PHP-Erweiterung pdo_sqlite ist erforderlich.');
        }

        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        try {
            $pdo = new PDO('sqlite:' . $this->path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec('PRAGMA journal_mode = WAL;');
        } catch (PDOException $exception) {
            throw new RuntimeException('SQLite-Verbindung fehlgeschlagen: ' . $exception->getMessage(), 0, $exception);
        }

        $this->pdo = $pdo;

        return $this->pdo;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll() ?: [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);
        $result = $statement->fetch();

        return $result === false ? null : $result;
    }

    public function execute(string $sql, array $params = []): bool
    {
        $statement = $this->pdo()->prepare($sql);
        return $statement->execute($params);
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($this);
            $pdo->commit();
            return $result;
        } catch (\Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }
    }

    public function isInstalled(): bool
    {
        $result = $this->fetchOne(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'"
        );

        return $result !== null;
    }
}
