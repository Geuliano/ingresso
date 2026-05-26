<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../app/bootstrap.php';

$config = app_config();
$key = $config['app_key'] ?? '';
$isConfigured = !empty($key) && $key !== 'change-me-please-32chars-minimum';
$keyLength = strlen((string) $key);

json_response([
    'ok' => true,
    'app_key_configured' => $isConfigured,
    'app_key_length' => $keyLength,
    'message' => $isConfigured && $keyLength >= 32
        ? 'APP_KEY carregada e com tamanho adequado.'
        : 'Defina APP_KEY (32+ chars) no ambiente para completar a configuração.',
]);
