<?php

declare(strict_types=1);

namespace App\DTO;

final class ImportPreviewResult
{
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly array $mapping,
        public readonly array $summary
    ) {
    }
}
