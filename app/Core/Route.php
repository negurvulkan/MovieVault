<?php

declare(strict_types=1);

namespace App\Core;

final class Route
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $handler,
        public readonly string $name,
        public readonly array $options = []
    ) {
    }
}
