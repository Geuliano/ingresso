<?php

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');
define('CORE_PATH', APP_PATH . '/core');
define('MODULES_PATH', APP_PATH . '/modules');
define('PAGES_PATH', APP_PATH . '/pages');
define('PUBLIC_PATH', ROOT_PATH . '/public');

date_default_timezone_set('America/Porto_Velho');

// ======= Conexão com BD (via .htaccess SetEnv) =======
if (!function_exists('mulherEnv')) {
    function mulherEnv(string $name, ?string $default = null): ?string
    {
        if (array_key_exists($name, $_SERVER)) {
            return (string)$_SERVER[$name];
        }
        if (array_key_exists($name, $_ENV)) {
            return (string)$_ENV[$name];
        }
        $valor = getenv($name);
        return $valor === false ? $default : (string)$valor;
    }
}

$host    = mulherEnv('MULHER_DB_HOST', '127.0.0.1');
$db      = mulherEnv('MULHER_DB_NAME', 'ingresso');
$user    = mulherEnv('MULHER_DB_USER', 'root');
$pass    = mulherEnv('MULHER_DB_PASS', '');
$charset = mulherEnv('MULHER_DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-04:00'",
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Throwable $e) {
    die('Erro na conexão ao banco.');
}

// ===== Sessão segura =====
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $isHttps,
    'samesite' => 'Strict',
]);

session_start();

// ===== BASE_URL =====
$documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'])), '/')
    : null;
$rootRealpath = rtrim(str_replace('\\', '/', (string)realpath(ROOT_PATH)), '/');
$basePath = '';

// Compatível com PHP 7.x (sem str_starts_with)
if ($documentRoot && $rootRealpath && substr($rootRealpath, 0, strlen($documentRoot)) === $documentRoot) {
    $relative = trim(substr($rootRealpath, strlen($documentRoot)), '/');
    $basePath = $relative === '' ? '' : '/' . $relative;
}

// Fallback para o comportamento anterior
if ($basePath === '') {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath  = preg_replace('#/public$#', '', rtrim($scriptDir, '/'));
}

define('BASE_URL', ($basePath === '' ? '/' : $basePath . '/'));
