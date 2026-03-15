<?php

declare(strict_types=1);

$requiredExtensions = ['pdo_sqlite', 'sqlite3'];
$missingExtensions = array_values(array_filter(
    $requiredExtensions,
    static fn (string $extension): bool => !extension_loaded($extension)
));

$dbPath = __DIR__ . '/../storage/movievault.sqlite';

if ($missingExtensions !== []) {
    http_response_code(503);
    require __DIR__ . '/requirements.php';
    exit;
}

if (!is_file($dbPath)) {
    require __DIR__ . '/setup.php';
    exit;
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->run();
