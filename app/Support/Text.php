<?php

declare(strict_types=1);

namespace App\Support;

final class Text
{
    public static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value !== '' ? $value : 'n-a';
    }

    public static function sortable(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/^(der|die|das|the|a|an)\s+/i', '', $value) ?? $value;

        return $value;
    }
}
