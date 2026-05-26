<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_auth_or_redirect('login');

$pdo = db();

$uid = trim((string) ($_GET['uid'] ?? ''));
if ($uid === '') {
    header('Location: ' . url('carrinho'));
    exit;
}

// Carrega o pedido
$stmt = $pdo->prepare('
    SELECT * FROM pedidos
    WHERE uid = ? AND user_id = ? AND deleted_at IS NULL
    LIMIT 1
');
$stmt->execute([$uid, (int) $_SESSION['user_id']]);
$pedido = $stmt->fetch();

if (!$pedido) {
    header('Location: ' . url('carrinho'));
    exit;
}

// Permite reabrir pedidos rejeitados para nova tentativa
if (!in_array($pedido['status'], ['pendente', 'processando', 'pendente_pix', 'rejeitado'], true)) {
    header('Location: ' . url('pedido/confirmacao') . '?uid=' . urlencode($uid));
    exit;
}

// Carrega os itens do pedido
$stmtItems = $pdo->prepare('
    SELECT ticket_nome, batch_nome, titular_nome, unit_price, fee, subtotal
    FROM pedido_items
    WHERE pedido_id = ?
    ORDER BY id ASC
');
$stmtItems->execute([(int) $pedido['id']]);
$items = $stmtItems->fetchAll();

// Credenciais MP para injetar na view
$config  = app_config();
$mpCfg   = $config['mercadopago'] ?? [];
$mpPublicKey = $mpCfg['public_key'] ?? '';

render('checkout', [
    'title'       => 'Pagamento | Pulse Festival',
    'activeNav'   => 'cart',
    'showTopNav'  => true,
    'scripts'     => ['js/checkout.js'],
    'pedido'      => $pedido,
    'items'       => $items,
    'mpPublicKey' => $mpPublicKey,
]);
