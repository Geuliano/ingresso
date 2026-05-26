<?php

declare(strict_types=1);

function asset(string $path): string
{
    $cleanPath = ltrim($path, '/');
    $url = site_base_path() . '/assets/' . $cleanPath;

    // Cache busting baseado em filemtime do arquivo fisico, quando existir.
    $fsPath = PUBLIC_PATH . '/assets/' . $cleanPath;
    if (is_file($fsPath)) {
        $url .= '?v=' . filemtime($fsPath);
    }

    return $url;
}

/**
 * Escapa string para uso seguro em HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalize_icon_value(?string $value): string
{
    $clean = strtolower(trim((string)($value ?: 'help')));
    $clean = preg_replace('/[^a-z0-9_\-\s]/', '', $clean);
    $clean = preg_replace('/\s+/', ' ', $clean);

    if ($clean !== '' && !str_contains($clean, ' ')) {
        $prefixes = [
            'fa-duotone', 'fa-regular', 'fa-brands', 'fa-solid',
            'fa-light', 'fa-thin', 'fas', 'far', 'fab', 'fal', 'fad',
            'las', 'lar', 'lab',
        ];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($clean, $prefix)) {
                $rest = substr($clean, strlen($prefix));
                if (preg_match('/^(fa|la)-[a-z0-9_-]+$/', $rest)) {
                    $clean = $prefix . ' ' . $rest;
                    break;
                }
            }
        }
    }

    return $clean !== '' ? mb_substr($clean, 0, 120) : 'help';
}

function icon_html(?string $value, string $class = '', int $size = 18): string
{
    $icon = normalize_icon_value($value);
    $safeIcon = e($icon);
    $safeClass = trim($class) !== '' ? ' ' . e($class) : '';
    $safeSize = max(1, $size);

    if (str_contains($icon, ' ')) {
        return '<i class="' . $safeIcon . $safeClass . '" style="font-size:' . $safeSize . 'px"></i>';
    }

    return '<span class="material-symbols-outlined' . $safeClass . '" style="font-size:' . $safeSize . 'px">' . $safeIcon . '</span>';
}

/**
 * Envia cabecalhos HTTP de seguranca. Deve ser chamado antes de qualquer
 * saida (no layout base ou em endpoints JSON).
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    $httpsHeader = $_SERVER['HTTPS'] ?? '';
    $forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    if ($httpsHeader === 'on' || $forwardedProto === 'https') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // CSP relaxada para acomodar Google Fonts/Material Symbols e Mercado Pago SDK.
    // Em uma proxima iteracao, fazer self-host das fontes para remover 'unsafe-inline'.
    $csp = "default-src 'self'; "
        . "script-src 'self' https://sdk.mercadopago.com; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data: blob: https:; "
        . "connect-src 'self' https://api.mercadopago.com https://*.mercadopago.com; "
        . "frame-src 'self' https://*.mercadopago.com https://*.mercadolibre.com; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "form-action 'self'";
    header("Content-Security-Policy: $csp");
    header_remove('X-Powered-By');
}

function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    return $value !== null ? $value : $default;
}

function url(string $path = ''): string
{
    $cleanPath = ltrim($path, '/');
    // remover .php para URLs amigaveis
    $cleanPath = $cleanPath === '' ? '' : preg_replace('/\\.php$/i', '', $cleanPath);

    $base = site_base_path();

    if ($cleanPath === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return rtrim($base, '/') . '/' . $cleanPath;
}

function base_path(): string
{
    // Mantem o caminho com /public (base fisica)
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    if ($base === '/' || $base === '\\' || $base === '.') {
        return '';
    }

    return $base;
}

function site_base_path(): string
{
    // Remove /public do base path para gerar URLs amigaveis (/ingresso)
    $base = base_path();
    $clean = preg_replace('~/public$~i', '', $base);
    return ($clean === '/' || $clean === '\\') ? '' : rtrim($clean, '/');
}

function render(string $view, array $data = []): void
{
    $viewFile = VIEWS_PATH . '/pages/' . $view . '.php';

    if (!file_exists($viewFile)) {
        http_response_code(404);
        echo 'Pagina nao encontrada.';
        return;
    }

    extract($data);

    ob_start();
    include $viewFile;
    $content = ob_get_clean();

    include VIEWS_PATH . '/layouts/base.php';
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_post_fields(array $fields, array $source): array
{
    $payload = [];
    foreach ($fields as $field) {
        if (!isset($source[$field])) {
            json_response(['error' => 'Campo obrigatorio ausente: ' . $field], 422);
        }
        $payload[$field] = trim((string) $source[$field]);
    }
    return $payload;
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_or_fail(?string $token): void
{
    start_secure_session();
    if (empty($_SESSION['csrf_token']) || !$token || !hash_equals($_SESSION['csrf_token'], $token)) {
        json_response(['error' => 'Token invalido. Atualize a pagina.'], 419);
    }
}
