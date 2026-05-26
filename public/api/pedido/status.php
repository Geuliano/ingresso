<?php

declare(strict_types=1);

/**
 * GET /api/pedido/status?uid=<pedido_uid>
 *
 * Retorna o status atual do pedido e os itens emitidos.
 * Usado pela página de confirmação e pelo polling de PIX.
 */

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Método não permitido'], 405);
}

start_secure_session();
if (empty($_SESSION['user_id'])) {
    json_response(['error' => 'Não autorizado.'], 401);
}

$uid = trim((string) ($_GET['uid'] ?? ''));
if ($uid === '') {
    json_response(['error' => 'UID obrigatório.'], 422);
}

$pdo = db();

$stmt = $pdo->prepare('
    SELECT id, uid, status, total, payer_email, payer_name, payer_cpf,
           mp_payment_method, pix_qr_code, pix_qr_code_base64, pix_expiration_date,
           paid_at, created_at
    FROM pedidos
    WHERE uid = ? AND user_id = ? AND deleted_at IS NULL
    LIMIT 1
');
$stmt->execute([$uid, (int) $_SESSION['user_id']]);
$pedido = $stmt->fetch();

if (!$pedido) {
    json_response(['error' => 'Pedido não encontrado.'], 404);
}

// Carrega os itens
$stmtItems = $pdo->prepare('
    SELECT ticket_nome, batch_nome, titular_nome, titular_cpf,
           unit_price, fee, subtotal, ingresso_uid, emitido_at
    FROM pedido_items
    WHERE pedido_id = ?
    ORDER BY id ASC
');
$stmtItems->execute([(int) $pedido['id']]);
$items = $stmtItems->fetchAll();

json_response([
    'success' => true,
    'pedido'  => [
        'uid'           => $pedido['uid'],
        'status'        => $pedido['status'],
        'total'         => (float) $pedido['total'],
        'payment_method'=> $pedido['mp_payment_method'],
        'paid_at'       => $pedido['paid_at'],
        'created_at'    => $pedido['created_at'],
        'pix'           => $pedido['status'] === 'pendente_pix' ? [
            'qr_code'        => $pedido['pix_qr_code'],
            'qr_code_base64' => $pedido['pix_qr_code_base64'],
            'expiration'     => $pedido['pix_expiration_date'],
        ] : null,
    ],
    'items' => array_map(fn($i) => [
        'ticket_nome'  => $i['ticket_nome'],
        'batch_nome'   => $i['batch_nome'],
        'titular_nome' => $i['titular_nome'],
        'subtotal'     => (float) $i['subtotal'],
        'ingresso_uid' => $i['ingresso_uid'],
        'emitido_at'   => $i['emitido_at'],
    ], $items),
]);
