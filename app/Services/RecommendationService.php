<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\RecommendationQuery;
use App\Repositories\CatalogRepository;
use App\Repositories\WatchRepository;

final class RecommendationService
{
    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly WatchRepository $watch
    ) {
    }

    public function recommend(int $userId, RecommendationQuery $query): ?array
    {
        $genreSlug = $query->mode === 'genre' && $query->genre !== null
            ? strtolower(trim($query->genre))
            : null;

        $titles = $this->catalog->titlesForRecommendation($genreSlug, $userId, $query->filter);
        if ($titles === []) {
            return null;
        }

        shuffle($titles);
        return $titles[0];
    }
}
