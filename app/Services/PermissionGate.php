<?php

declare(strict_types=1);

namespace App\Services;

final class PermissionGate
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function allows(string $permission): bool
    {
        $user = $this->auth->user();
        if (!$user) {
            return false;
        }

        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
