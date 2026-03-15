<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public function __construct(private readonly string $flashKey = '_flash')
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function flash(string $type, string $message): void
    {
        $_SESSION[$this->flashKey][] = ['type' => $type, 'message' => $message];
    }

    public function pullFlashes(): array
    {
        $flashes = $_SESSION[$this->flashKey] ?? [];
        unset($_SESSION[$this->flashKey]);

        return is_array($flashes) ? $flashes : [];
    }
}
