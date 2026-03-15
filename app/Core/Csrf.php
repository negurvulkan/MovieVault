<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public function __construct(
        private readonly Session $session,
        private readonly string $sessionKey
    ) {
    }

    public function token(): string
    {
        $token = $this->session->get($this->sessionKey);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->put($this->sessionKey, $token);
        }

        return $token;
    }

    public function validate(?string $token): bool
    {
        return is_string($token) && hash_equals($this->token(), $token);
    }
}
