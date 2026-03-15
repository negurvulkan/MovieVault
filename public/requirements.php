<?php

declare(strict_types=1);

$missingExtensions = $missingExtensions ?? [];
$dbPath = $dbPath ?? '';
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MovieVault einrichten</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="setup-body">
<main class="container py-5">
    <div class="setup-card mx-auto shadow-lg">
        <p class="eyebrow mb-2">MovieVault</p>
        <h1 class="display-6 mb-3">App noch nicht betriebsbereit</h1>
        <?php if ($missingExtensions !== []): ?>
            <div class="alert alert-warning">
                <strong>Fehlende PHP-Erweiterungen:</strong>
                <?= htmlspecialchars(implode(', ', $missingExtensions), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if (!is_file($dbPath)): ?>
            <div class="alert alert-info">
                Die SQLite-Datenbank wurde noch nicht angelegt. Sobald die SQLite-Erweiterungen aktiv sind, startet automatisch der Web-Installer.
            </div>
        <?php endif; ?>
        <p class="mb-0 text-secondary">Empfohlen sind zusaetzlich <code>openssl</code>, <code>curl</code>, <code>fileinfo</code> und <code>mbstring</code> fuer Metadatenabrufe und Bild-Cache.</p>
    </div>
</main>
</body>
</html>
