<?php

declare(strict_types=1);

namespace App\Contracts;

interface MetadataProviderInterface
{
    public function name(): string;

    public function search(array $criteria): array;

    public function fetch(array $selection): ?array;
}
