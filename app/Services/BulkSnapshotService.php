<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

final class BulkSnapshotService
{
    private const SESSION_KEY = '_bulk_snapshots';
    private const TTL_SECONDS = 900;

    public function __construct(private readonly Session $session)
    {
    }

    public function create(array $snapshot): string
    {
        $snapshots = $this->cleanup($this->all());
        $token = bin2hex(random_bytes(16));
        $snapshot['created_at'] = date('Y-m-d H:i:s');
        $snapshots[$token] = $snapshot;

        $this->session->put(self::SESSION_KEY, $snapshots);

        return $token;
    }

    public function get(string $token): ?array
    {
        $snapshots = $this->cleanup($this->all());
        $this->session->put(self::SESSION_KEY, $snapshots);

        $snapshot = $snapshots[$token] ?? null;
        return is_array($snapshot) ? $snapshot : null;
    }

    public function forget(string $token): void
    {
        $snapshots = $this->all();
        unset($snapshots[$token]);
        $this->session->put(self::SESSION_KEY, $snapshots);
    }

    private function all(): array
    {
        $snapshots = $this->session->get(self::SESSION_KEY, []);
        return is_array($snapshots) ? $snapshots : [];
    }

    private function cleanup(array $snapshots): array
    {
        $threshold = time() - self::TTL_SECONDS;

        return array_filter(
            $snapshots,
            static function (mixed $snapshot) use ($threshold): bool {
                if (!is_array($snapshot) || empty($snapshot['created_at'])) {
                    return false;
                }

                $createdAt = strtotime((string) $snapshot['created_at']);
                return $createdAt !== false && $createdAt >= $threshold;
            }
        );
    }
}
