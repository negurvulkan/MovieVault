<?php

declare(strict_types=1);

namespace App\DTO;

final class RecommendationQuery
{
    public function __construct(
        public readonly string $mode = 'random',
        public readonly ?string $genre = null,
        public readonly string $filter = 'unwatched'
    ) {
    }
}
