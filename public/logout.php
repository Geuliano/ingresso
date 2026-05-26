<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('login'));
    exit;
}

start_secure_session();
validate_csrf_or_fail($_POST['csrf_token'] ?? null);

// remove tokens persistentes antes de destruir a sess�o
$currentUserId = $_SESSION['user_id'] ?? null;
if ($currentUserId) {
    $pdo = db();
    delete_user_remember_tokens($pdo, (int) $currentUserId);
}
clear_remember_cookie();

$_SESSION = [];
if (session_id() !== '') {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        true
    );
}
session_destroy();

header('Location: ' . url('login'));
exit;
