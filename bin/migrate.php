<?php

declare(strict_types=1);

use App\Repositories\UserRepository;

$app = require __DIR__ . '/../bootstrap/app.php';

$migrationFiles = glob(__DIR__ . '/../database/migrations/*.sql') ?: [];
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    $sql = file_get_contents($file);
    if (is_string($sql) && trim($sql) !== '') {
        $app->db()->pdo()->exec($sql);
        echo 'Ausgefuehrt: ' . basename($file) . PHP_EOL;
    }
}

/** @var UserRepository $users */
$users = $app->make(UserRepository::class);
$permissions = require __DIR__ . '/../config/permissions.php';
$users->seedPermissions($permissions);
$users->ensureSystemRoles();

echo "Migrationen und Rechte wurden aktualisiert.\n";
