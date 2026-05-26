<?php

declare(strict_types=1);

// Configure according to your environment (.env or hosting panel).
$envBool = static function (string $key, bool $default = false): bool {
    return filter_var(env($key, $default), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
};

$envInt = static function (string $key, int $default): int {
    $value = env($key, $default);
    return is_numeric($value) ? (int) $value : $default;
};

return [
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_NAME', 'ingresso'),
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],
    // Change this to a strong, secret key (32+ random chars). Do not commit real secrets.
    'app_key' => env('APP_KEY', ''),
    // Rate limits and expirations (seconds).
    'verification' => [
        'code_length' => $envInt('VERIFICATION_CODE_LENGTH', 6),
        'expires_in' => $envInt('VERIFICATION_EXPIRES_IN', 600), // 10 minutes
        'cooldown' => $envInt('VERIFICATION_COOLDOWN', 60),      // 1 minute between sends
        'max_attempts' => $envInt('VERIFICATION_MAX_ATTEMPTS', 3),
    ],
    'n8n' => [
        'enabled' => $envBool('N8N_ENABLED', false),
        'webhook_url' => env('N8N_WEBHOOK_URL', 'https://n8n.notbad.com.br/webhook/7c8c5898-ebf5-46e3-900d-ea21e001a224'),
        'auth_token' => env('N8N_AUTH_TOKEN', ''),
        'signing_secret' => env('N8N_SIGNING_SECRET', ''),
        'timeout' => $envInt('N8N_TIMEOUT', 5),
    ],
    'remember' => [
        'cookie_name' => 'remember_token',
        'expires_in' => 60 * 60 * 24 * 30, // 30 days
    ],
    'debug' => [
        // Em hospedagem real, defina como false e configure SMTP.
        'send_emails' => $envBool('DEBUG_SEND_EMAILS', false),
        // Para testes locais enquanto o e-mail não está integrado.
        'expose_codes' => $envBool('DEBUG_EXPOSE_CODES', false),
        // Para quando o gateway de WhatsApp estiver integrado.
        'send_whatsapp' => $envBool('DEBUG_SEND_WHATSAPP', false),
    ],
    // -------------------------------------------------------------------------
    // Mercado Pago
    // -------------------------------------------------------------------------
    // Credenciais em: https://www.mercadopago.com.br/developers/panel/app
    // Use credenciais de TESTE (TEST-...) em dev e de PRODUÇÃO em produção.
    'mercadopago' => [
        'public_key'     => env('MP_PUBLIC_KEY', ''),
        'access_token'   => env('MP_ACCESS_TOKEN', ''),
        'webhook_secret' => env('MP_WEBHOOK_SECRET', ''),
    ],
];
