<?php

declare(strict_types=1);

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$basePath = '';

if (isset($_SERVER['SCRIPT_NAME'])) {
    $basePath = str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME']));
    $basePath = $basePath === '/' ? '' : rtrim($basePath, '/');
}

if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
    $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
}

if ($requestPath !== '/' && servePublicAsset($requestPath)) {
    return;
}

require __DIR__ . '/public/index.php';

function servePublicAsset(string $requestPath): bool
{
    $extension = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));
    $allowedMimeTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
    ];

    if (!array_key_exists($extension, $allowedMimeTypes)) {
        return false;
    }

    $publicRoot = realpath(__DIR__ . '/public');
    $candidate = realpath(__DIR__ . '/public/' . ltrim($requestPath, '/'));

    if (!$publicRoot || !$candidate || !is_file($candidate)) {
        return false;
    }

    $normalizedPublicRoot = str_replace('\\', '/', $publicRoot);
    $normalizedCandidate = str_replace('\\', '/', $candidate);

    if (!str_starts_with($normalizedCandidate, $normalizedPublicRoot . '/')) {
        return false;
    }

    header('Content-Type: ' . $allowedMimeTypes[$extension]);
    header('Content-Length: ' . (string) filesize($candidate));
    header('Cache-Control: public, max-age=' . (in_array($extension, ['css', 'js', 'map', 'json'], true) ? '86400' : '604800'));

    readfile($candidate);

    return true;
}
