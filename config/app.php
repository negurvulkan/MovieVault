<?php

declare(strict_types=1);

$basePath = '';
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_NAME'])) {
    $basePath = str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME']));
    $basePath = $basePath === '/' ? '' : rtrim($basePath, '/');
}

return [
    'app' => [
        'name' => 'MovieVault',
        'env' => getenv('MOVIEVAULT_ENV') ?: 'local',
        'debug' => getenv('MOVIEVAULT_DEBUG') !== '0',
        'base_path' => getenv('MOVIEVAULT_BASE_PATH') ?: $basePath,
        'base_url' => getenv('MOVIEVAULT_BASE_URL') ?: 'http://localhost',
        'timezone' => 'Europe/Berlin',
        'locale' => 'de_DE',
    ],
    'database' => [
        'path' => __DIR__ . '/../storage/movievault.sqlite',
    ],
    'session' => [
        'name' => 'movievault_session',
        'lifetime' => 60 * 60 * 8,
    ],
    'security' => [
        'password_min_length' => 10,
        'invite_ttl_days' => 14,
        'csrf_key' => '_csrf_token',
    ],
    'metadata' => [
        'tmdb_api_key' => getenv('MOVIEVAULT_TMDB_API_KEY') ?: '',
        'tmdb_base_url' => 'https://api.themoviedb.org/3',
        'tmdb_image_url' => 'https://image.tmdb.org/t/p/w500',
        'wikidata_api_url' => 'https://www.wikidata.org/w/api.php',
        'wikidata_entity_url' => 'https://www.wikidata.org/wiki/Special:EntityData/%s.json',
        'http_timeout' => 15,
        'user_agent' => 'MovieVault/1.0',
    ],
    'paths' => [
        'templates' => __DIR__ . '/../templates',
        'smarty_compile' => __DIR__ . '/../storage/cache/smarty/compile',
        'smarty_cache' => __DIR__ . '/../storage/cache/smarty/cache',
        'imports' => __DIR__ . '/../storage/imports',
        'posters' => __DIR__ . '/../public/media/posters',
        'logs' => __DIR__ . '/../storage/logs',
    ],
];
