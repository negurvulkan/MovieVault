<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Session;
use App\Repositories\UserRepository;

final class AuthService
{
    private ?array $user = null;

    public function __construct(
        private readonly Session $session,
        private readonly UserRepository $users,
        private readonly Config $config
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findUserByEmail($email);
        if (!$user || !(bool) $user['is_active']) {
            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        $this->session->regenerate();
        $this->session->put('auth_user_id', (int) $user['id']);
        $this->users->updateLastLogin((int) $user['id']);
        $this->user = $this->users->findUserById((int) $user['id']);

        return true;
    }

    public function loginById(int $userId): void
    {
        $this->session->regenerate();
        $this->session->put('auth_user_id', $userId);
        $this->user = $this->users->findUserById($userId);
    }

    public function logout(): void
    {
        $this->session->forget('auth_user_id');
        $this->user = null;
        $this->session->regenerate();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function user(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $id = $this->session->get('auth_user_id');
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return null;
        }

        $this->user = $this->users->findUserById((int) $id);
        return $this->user;
    }

    public function minimumPasswordLength(): int
    {
        return (int) $this->config->get('security.password_min_length', 10);
    }
}
