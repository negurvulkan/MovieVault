<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CatalogRepository;
use App\Repositories\WatchRepository;

final class StatsService
{
    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly WatchRepository $watch
    ) {
    }

    public function dashboard(int $userId): array
    {
        $snapshot = $this->catalog->snapshotForDashboard($userId);
        $snapshot['recent_titles'] = $this->catalog->recentTitles();
        $snapshot['top_genres'] = $this->catalog->topGenres();
        $snapshot['last_watch'] = $this->watch->lastEvent($userId);

        return $snapshot;
    }
}
