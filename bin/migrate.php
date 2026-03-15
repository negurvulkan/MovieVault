<?php

declare(strict_types=1);

use App\Services\MigrationService;

$app = require __DIR__ . '/../bootstrap/app.php';

/** @var MigrationService $migrations */
$migrations = $app->make(MigrationService::class);

if (!$migrations->databaseExists()) {
    fwrite(STDERR, "Keine bestehende Datenbank gefunden. Fuer eine Neuinstallation bitte php bin/install.php nutzen.\n");
    exit(1);
}

$applied = $migrations->installSchema();

foreach ($applied as $migration) {
    echo 'Ausgefuehrt: ' . $migration . PHP_EOL;
}

echo "Migrationen und Rechte wurden aktualisiert.\n";
