<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

start_secure_session();
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . url('perfil'));
    exit;
}

render('login', [
    'title' => 'Login | Pulse Festival',
    'activeNav' => 'profile',
    'showTopNav' => true,
    'scripts' => ['js/auth.js'],
]);
