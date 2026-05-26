<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__ . '/..');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('VIEWS_PATH', BASE_PATH . '/app/views');
define('SRC_PATH', BASE_PATH . '/src');

// Carrega .env se existir (variaveis ja definidas em $_SERVER/$_ENV tem precedencia).
require_once __DIR__ . '/env_loader.php';

// Autoload PSR-4 do Composer quando disponivel, caso contrario fallback manual.
$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = SRC_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

// Timezone padrao (configuravel via APP_TIMEZONE).
$tz = $_SERVER['APP_TIMEZONE'] ?? $_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'America/Porto_Velho';
date_default_timezone_set(is_string($tz) && $tz !== '' ? $tz : 'America/Porto_Velho');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/n8n.php';
require_once __DIR__ . '/evento.php';
