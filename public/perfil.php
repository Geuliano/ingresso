<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_auth_or_redirect('login');

$user = null;
start_secure_session();
if (!empty($_SESSION['user_id'])) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT name, email, cpf, email_verified_at, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
}

render('profile', [
    'title' => 'Perfil | Pulse Festival',
    'activeNav' => 'profile',
    'showTopNav' => true,
    'scripts' => [],
    'user' => $user,
]);
