<?php

declare(strict_types=1);

use App\Services\InstallerService;

$app = require __DIR__ . '/../bootstrap/app.php';

/** @var InstallerService $installer */
$installer = $app->make(InstallerService::class);

$email = $argv[1] ?? 'admin@example.local';
$baseUrl = $app->config()->get('app.base_url', 'http://localhost');
$result = $installer->install((string) $email, (string) $baseUrl);

echo "MovieVault wurde eingerichtet.\n";
echo "Admin-Einladung: " . $result['invite_url'] . "\n";
echo "Gueltig bis: " . $result['expires_at'] . "\n";
