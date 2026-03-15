<?php

declare(strict_types=1);

use App\Services\MigrationService;

$requiredExtensions = ['pdo_sqlite', 'sqlite3'];
$missingExtensions = array_values(array_filter(
    $requiredExtensions,
    static fn (string $extension): bool => !extension_loaded($extension)
));

if ($missingExtensions !== []) {
    http_response_code(503);
    require __DIR__ . '/requirements.php';
    exit;
}

$app = require __DIR__ . '/../bootstrap/app.php';
/** @var MigrationService $migrations */
$migrations = $app->make(MigrationService::class);

if (!$migrations->isInstalledDatabase()) {
    require __DIR__ . '/setup.php';
    exit;
}

$app->run();
