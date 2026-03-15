<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Die eingegebenen Daten sind unvollstaendig oder ungueltig.');
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
