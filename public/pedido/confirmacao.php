<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

require_auth_or_redirect('login');

$uid = trim((string) ($_GET['uid'] ?? ''));
if ($uid === '') {
    header('Location: ' . url('carrinho'));
    exit;
}

render('confirmacao', [
    'title'      => 'Confirmação | Pulse Festival',
    'activeNav'  => 'cart',
    'showTopNav' => true,
    'scripts'    => ['js/confirmacao.js'],
    'pedidoUid'  => $uid,
]);
