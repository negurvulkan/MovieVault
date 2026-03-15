<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;

final class InstallerService
{
    public function __construct(
        private readonly Config $config,
        private readonly MigrationService $migrations,
        private readonly UserRepository $users,
        private readonly SettingRepository $settings
    ) {
    }

    public function install(string $adminEmail, string $baseUrl): array
    {
        $this->migrations->installSchema();
        $roles = $this->users->ensureSystemRoles();

        $this->settings->upsertMany([
            'app_name' => 'MovieVault',
            'default_recommendation_filter' => 'unwatched',
            'invite_ttl_days' => (string) $this->config->get('security.invite_ttl_days', 14),
        ]);

        $invitation = $this->users->createInvitation(
            $adminEmail,
            null,
            [$roles['administrator']],
            (int) $this->config->get('security.invite_ttl_days', 14)
        );

        return [
            'invite_url' => rtrim($baseUrl, '/') . ($this->config->get('app.base_path') ?: '') . '/invite/' . $invitation['token'],
            'expires_at' => $invitation['expires_at'],
        ];
    }
}
