<?php

declare(strict_types=1);

// Bootstrap minimal de testes: registra o autoloader PSR-4 sem precisar do Composer.
// Quando rodando "composer test" com vendor instalado, o autoload do Composer e
// usado em vez deste fallback.

spl_autoload_register(static function (string $class): void {
    $maps = [
        'App\\' => __DIR__ . '/../src/',
        'Tests\\' => __DIR__ . '/',
    ];

    foreach ($maps as $prefix => $base) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }
        $relative = substr($class, strlen($prefix));
        $file = $base . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
