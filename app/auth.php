<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function start_secure_session(): void
{
    $alreadyStarted = session_status() === PHP_SESSION_ACTIVE;

    if (!$alreadyStarted) {
        $httpsHeader = $_SERVER['HTTPS'] ?? '';
        $forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        $isSecure = ($httpsHeader === 'on') || ($forwardedProto === 'https');

        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookieParams['path'] ?? '/',
            'domain' => $cookieParams['domain'] ?? '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        try_login_from_remember();
    }
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function normalize_cpf(string $cpf): string
{
    return preg_replace('/\D+/', '', $cpf) ?? '';
}

function normalize_br_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if (strlen($digits) === 13 && str_starts_with($digits, '55')) {
        return $digits;
    }

    if (strlen($digits) === 11) {
        return '55' . $digits;
    }

    if (strlen($digits) === 13) {
        return '55' . substr($digits, -11);
    }

    return $digits;
}

function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function is_valid_cpf(string $cpf): bool
{
    $cpf = normalize_cpf($cpf);
    if (strlen($cpf) !== 11) {
        return false;
    }
    if (preg_match('/^(\\d)\\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($c = 0; $c < $t; $c++) {
            $sum += $cpf[$c] * (($t + 1) - $c);
        }
        $digit = ((10 * $sum) % 11) % 10;
        if ((int) $cpf[$t] !== $digit) {
            return false;
        }
    }

    return true;
}

function is_valid_br_phone(string $phone): bool
{
    $normalized = normalize_br_phone($phone);
    if (strlen($normalized) !== 13 || strpos($normalized, '55') !== 0) {
        return false;
    }

    $ddd = (int) substr($normalized, 2, 2);
    $firstLocalDigit = (int) substr($normalized, 4, 1);

    return $ddd >= 11 && $ddd <= 99 && $firstLocalDigit >= 2;
}

function generate_numeric_code(int $length): string
{
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= random_int(0, 9);
    }
    return $code;
}

function hash_code(string $code): string
{
    $config = app_config();
    if (empty($config['app_key']) || $config['app_key'] === 'change-me-please-32chars-minimum') {
        throw new RuntimeException('APP_KEY is not configured. Set a strong secret.');
    }
    return hash_hmac('sha256', $code, $config['app_key']);
}

function send_verification_email(string $to, string $code, string $purpose, array $context = []): bool
{
    $context = array_merge(['email' => $to], $context);
    $sent = dispatch_code_to_n8n('email', $to, $code, $purpose, $context);
    $config = app_config();

    if ($sent) {
        return true;
    }

    if (!($config['debug']['send_emails'] ?? false)) {
        return false;
    }

    $subject = 'Seu codigo de ' . $purpose;
    $body = "Ola,\n\nAqui esta o seu codigo de validacao: {$code}\nEle expira em 10 minutos.\n\nSe voce nao solicitou, ignore este e-mail.";
    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";

    return mail($to, $subject, $body, $headers);
}

function send_verification_whatsapp(string $toNumber, string $code, string $purpose, array $context = []): bool
{
    $context = array_merge(['whatsapp' => $toNumber], $context);
    $sent = dispatch_code_to_n8n('whatsapp', $toNumber, $code, $purpose, $context);

    if ($sent) {
        return true;
    }

    $config = app_config();
    if (!($config['debug']['send_whatsapp'] ?? false)) {
        return false;
    }

    error_log(sprintf('WhatsApp %s para %s: codigo %s', $purpose, $toNumber, $code));
    return true;
}

function remember_config(): array
{
    $config = app_config();
    return $config['remember'] ?? ['cookie_name' => 'remember_token', 'expires_in' => 60 * 60 * 24 * 30];
}

function remember_cookie_name(): string
{
    $remember = remember_config();
    return $remember['cookie_name'] ?? 'remember_token';
}

function remember_expiration(): DateTimeImmutable
{
    $remember = remember_config();
    $seconds = (int) ($remember['expires_in'] ?? 60 * 60 * 24 * 30);
    return new DateTimeImmutable("+{$seconds} seconds");
}

function set_remember_cookie(string $selector, string $validator, DateTimeImmutable $expiresAt): void
{
    $httpsHeader = $_SERVER['HTTPS'] ?? '';
    $forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    $isSecure = ($httpsHeader === 'on') || ($forwardedProto === 'https');

    setcookie(remember_cookie_name(), $selector . ':' . $validator, [
        'expires' => $expiresAt->getTimestamp(),
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function clear_remember_cookie(): void
{
    $httpsHeader = $_SERVER['HTTPS'] ?? '';
    $forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    $isSecure = ($httpsHeader === 'on') || ($forwardedProto === 'https');

    setcookie(remember_cookie_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function fetch_user_by_id(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function issue_remember_token(PDO $pdo, int $userId): void
{
    $selector = bin2hex(random_bytes(10));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $validator);
    $expiresAt = remember_expiration()->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, created_ip, created_ua) VALUES (:uid, :selector, :hash, :expires, :ip, :ua)');
    $stmt->execute([
        'uid' => $userId,
        'selector' => $selector,
        'hash' => $tokenHash,
        'expires' => $expiresAt,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    set_remember_cookie($selector, $validator, new DateTimeImmutable($expiresAt));
}

function delete_remember_token_by_selector(PDO $pdo, string $selector): void
{
    $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
    $stmt->execute(['selector' => $selector]);
}

function delete_user_remember_tokens(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :uid');
    $stmt->execute(['uid' => $userId]);
}

function try_login_from_remember(): void
{
    if (!empty($_SESSION['user_id'])) {
        return;
    }

    $cookie = $_COOKIE[remember_cookie_name()] ?? '';
    if (!$cookie || strpos($cookie, ':') === false) {
        return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if ($selector === '' || $validator === '') {
        clear_remember_cookie();
        return;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM remember_tokens WHERE selector = :selector AND expires_at >= NOW() LIMIT 1');
    $stmt->execute(['selector' => $selector]);
    $token = $stmt->fetch();

    if (!$token) {
        clear_remember_cookie();
        return;
    }

    $expected = $token['token_hash'];
    $provided = hash('sha256', $validator);
    if (!hash_equals($expected, $provided)) {
        delete_remember_token_by_selector($pdo, $selector);
        clear_remember_cookie();
        return;
    }

    $user = fetch_user_by_id($pdo, (int) $token['user_id']);
    if (!$user) {
        delete_remember_token_by_selector($pdo, $selector);
        clear_remember_cookie();
        return;
    }

    session_regenerate_id(true);
    set_session_user($user);

    delete_remember_token_by_selector($pdo, $selector);
    issue_remember_token($pdo, (int) $user['id']);
}
function latest_code(PDO $pdo, int $userId, string $purpose): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM verification_codes WHERE user_id = :uid AND purpose = :purpose ORDER BY id DESC LIMIT 1');
    $stmt->execute(['uid' => $userId, 'purpose' => $purpose]);
    $code = $stmt->fetch();
    return $code ?: null;
}

function create_verification_code(PDO $pdo, int $userId, string $purpose): array
{
    $config = app_config();
    $verif = $config['verification'];
    $code = generate_numeric_code($verif['code_length']);
    $expiresAt = (new DateTimeImmutable("+{$verif['expires_in']} seconds"))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare('INSERT INTO verification_codes (user_id, purpose, code_hash, expires_at, attempts_left, created_ip, created_ua) VALUES (:uid, :purpose, :hash, :expires, :attempts, :ip, :ua)');
    $stmt->execute([
        'uid' => $userId,
        'purpose' => $purpose,
        'hash' => hash_code($code),
        'expires' => $expiresAt,
        'attempts' => $verif['max_attempts'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    return ['code' => $code, 'expires_at' => $expiresAt];
}

function ensure_not_on_cooldown(PDO $pdo, int $userId, string $purpose): void
{
    $config = app_config();
    $cooldown = $config['verification']['cooldown'];
    $stmt = $pdo->prepare('SELECT created_at FROM verification_codes WHERE user_id = :uid AND purpose = :purpose ORDER BY id DESC LIMIT 1');
    $stmt->execute(['uid' => $userId, 'purpose' => $purpose]);
    $row = $stmt->fetch();
    if ($row) {
        $lastTime = strtotime($row['created_at']);
        if ($lastTime !== false && (time() - $lastTime) < $cooldown) {
            json_response(['error' => 'Aguarde antes de solicitar um novo codigo.'], 429);
        }
    }
}

function ensure_ip_not_rate_limited(PDO $pdo, string $purpose, int $limit = 8, int $windowSeconds = 300): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') {
        return;
    }

    $since = date('Y-m-d H:i:s', time() - $windowSeconds);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS attempts FROM verification_codes WHERE created_ip = :ip AND purpose = :purpose AND created_at >= :since');
    $stmt->execute([
        'ip' => $ip,
        'purpose' => $purpose,
        'since' => $since,
    ]);
    $attempts = (int) ($stmt->fetchColumn() ?: 0);
    if ($attempts >= $limit) {
        json_response(['error' => 'Limite de tentativas excedido. Tente novamente mais tarde.'], 429);
    }
}

function assert_valid_code(PDO $pdo, int $userId, string $purpose, string $code): array
{
    $config = app_config();
    $verif = $config['verification'];

    $stmt = $pdo->prepare('SELECT * FROM verification_codes WHERE user_id = :uid AND purpose = :purpose AND consumed_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute(['uid' => $userId, 'purpose' => $purpose]);
    $row = $stmt->fetch();

    if (!$row) {
        json_response(['error' => 'Codigo nao encontrado ou expirado.'], 400);
    }

    if ((int) $row['attempts_left'] <= 0) {
        json_response(['error' => 'Limite de tentativas excedido. Solicite um novo codigo.'], 429);
    }

    if (hash_code($code) !== $row['code_hash']) {
        $stmt = $pdo->prepare('UPDATE verification_codes SET attempts_left = attempts_left - 1 WHERE id = :id');
        $stmt->execute(['id' => $row['id']]);
        $remaining = max(0, (int) $row['attempts_left'] - 1);
        json_response(['error' => 'Codigo invalido.', 'attempts_left' => $remaining], 400);
    }

    $stmt = $pdo->prepare('UPDATE verification_codes SET consumed_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $row['id']]);

    return $row;
}

function set_session_user(array $user): void
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'] ?? null;
}

function create_user_session(array $user): void
{
    start_secure_session();
    session_regenerate_id(true);
    set_session_user($user);
}

function require_auth_or_redirect(string $redirectTo = 'login'): void
{
    start_secure_session();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . url($redirectTo));
        exit;
    }
}
