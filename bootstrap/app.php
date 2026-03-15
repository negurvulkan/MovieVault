<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/../vendor/smarty-src/smarty-5.6.0/libs/Smarty.class.php';

$config = require __DIR__ . '/../config/app.php';
$localConfigPath = __DIR__ . '/../config/app.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    $config = array_replace_recursive($config, is_array($localConfig) ? $localConfig : []);
}

date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

return new App\Core\Application($config);
