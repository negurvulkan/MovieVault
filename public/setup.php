<?php

declare(strict_types=1);

use App\Services\InstallerService;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('movievault_setup');
    session_start();
}

$basePath = '';
if (isset($_SERVER['SCRIPT_NAME'])) {
    $basePath = str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME']));
    $basePath = $basePath === '/' ? '' : rtrim($basePath, '/');
}

$dbPath = __DIR__ . '/../storage/movievault.sqlite';
$missingExtensions = array_values(array_filter(
    ['pdo_sqlite', 'sqlite3'],
    static fn (string $extension): bool => !extension_loaded($extension)
));

if ($missingExtensions !== []) {
    http_response_code(503);
    require __DIR__ . '/requirements.php';
    exit;
}

if (is_file($dbPath)) {
    header('Location: ' . ($basePath !== '' ? $basePath . '/' : '/'));
    exit;
}

$setupToken = $_SESSION['setup_token'] ??= bin2hex(random_bytes(32));
$errors = [];
$installResult = null;
$configWriteResult = null;

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
$suggestedBaseUrl = $scheme . '://' . $host . $basePath;
$adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
$baseUrl = trim((string) ($_POST['base_url'] ?? $suggestedBaseUrl));
$tmdbApiKey = trim((string) ($_POST['tmdb_api_key'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string) ($_POST['setup_token'] ?? '');

    if (!hash_equals($setupToken, $postedToken)) {
        $errors[] = 'Die Setup-Sitzung ist abgelaufen. Bitte die Seite neu laden.';
    }

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte eine gueltige Admin-E-Mail eingeben.';
    }

    if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Bitte eine vollstaendige Basis-URL eingeben.';
    }

    if ($errors === []) {
        $app = require __DIR__ . '/../bootstrap/app.php';
        /** @var InstallerService $installer */
        $installer = $app->make(InstallerService::class);
        $installResult = $installer->install($adminEmail, rtrim($baseUrl, '/'));
        $configWriteResult = writeLocalConfig(rtrim($baseUrl, '/'), $tmdbApiKey);
        unset($_SESSION['setup_token']);
    }
}

function writeLocalConfig(string $baseUrl, string $tmdbApiKey): array
{
    $configPath = __DIR__ . '/../config/app.local.php';
    $config = [
        'app' => ['base_url' => $baseUrl],
        'metadata' => ['tmdb_api_key' => $tmdbApiKey],
    ];

    $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";

    if (!is_dir(dirname($configPath)) || !is_writable(dirname($configPath))) {
        return [
            'written' => false,
            'message' => 'Konfiguration konnte nicht automatisch gespeichert werden. Bitte config/app.local.php manuell anlegen.',
        ];
    }

    $written = file_put_contents($configPath, $content);

    if ($written === false) {
        return [
            'written' => false,
            'message' => 'config/app.local.php konnte nicht geschrieben werden.',
        ];
    }

    return [
        'written' => true,
        'message' => 'config/app.local.php wurde fuer Base-URL und optionalen TMDb-Key angelegt.',
    ];
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MovieVault Einrichtung</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(($basePath ?: '') . '/vendor/bootstrap/css/bootstrap.min.css', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(($basePath ?: '') . '/assets/css/app.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="setup-body">
<main class="container py-5">
    <div class="setup-card mx-auto shadow-lg">
        <p class="eyebrow mb-2">MovieVault Setup</p>
        <?php if ($installResult !== null): ?>
            <h1 class="display-6 mb-3">Installation abgeschlossen</h1>
            <div class="alert alert-success">
                Die Datenbank wurde erzeugt und die erste Admin-Einladung erstellt.
            </div>
            <p><strong>Admin-Einladung:</strong><br><a href="<?= htmlspecialchars($installResult['invite_url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($installResult['invite_url'], ENT_QUOTES, 'UTF-8') ?></a></p>
            <p><strong>Gueltig bis:</strong> <?= htmlspecialchars($installResult['expires_at'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($configWriteResult !== null): ?>
                <div class="alert alert-<?= $configWriteResult['written'] ? 'info' : 'warning' ?>">
                    <?= htmlspecialchars($configWriteResult['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <a class="btn btn-primary" href="<?= htmlspecialchars(($basePath ?: '') . '/', ENT_QUOTES, 'UTF-8') ?>">Zur App</a>
        <?php else: ?>
            <h1 class="display-6 mb-3">Web-Installer fuer Shared Hosting</h1>
            <p class="text-secondary">Wenn `pdo_sqlite` und `sqlite3` verfuegbar sind, kannst du MovieVault direkt im Browser initial einrichten. Danach reicht der normale Aufruf der App.</p>

            <?php if ($errors !== []): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="vstack gap-3">
                <input type="hidden" name="setup_token" value="<?= htmlspecialchars($setupToken, ENT_QUOTES, 'UTF-8') ?>">
                <div>
                    <label class="form-label">Admin-E-Mail</label>
                    <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div>
                    <label class="form-label">Basis-URL</label>
                    <input type="url" name="base_url" class="form-control" value="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" required>
                    <div class="form-text">Wird fuer erzeugte Einladungslinks genutzt.</div>
                </div>
                <div>
                    <label class="form-label">TMDb API-Key (optional)</label>
                    <input type="text" name="tmdb_api_key" class="form-control" value="<?= htmlspecialchars($tmdbApiKey, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-text">Kann spaeter auch manuell in config/app.local.php hinterlegt werden.</div>
                </div>
                <button type="submit" class="btn btn-primary">Installation starten</button>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
