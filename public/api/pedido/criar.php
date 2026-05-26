<?php

declare(strict_types=1);

/**
 * POST /api/pedido/criar
 *
 * Recebe o carrinho e os dados dos titulares do front-end,
 * valida tudo e grava o pedido + itens no banco de dados.
 *
 * Retorna: { success, pedido_uid, total }
 */

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/ingressos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método não permitido'], 405);
}

// ── Autenticação ─────────────────────────────────────────────────────────────
start_secure_session();
if (empty($_SESSION['user_id'])) {
    json_response(['error' => 'É necessário estar logado para comprar.'], 401);
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];
validate_csrf_or_fail($body['csrf_token'] ?? null);

// ── Payload ───────────────────────────────────────────────────────────────────
$items     = $body['items']     ?? [];   // [{ id, name, qty, price }]
$attendees = $body['attendees'] ?? [];   // { ticketId: [{ name, cpf }] }

if (!is_array($items) || empty($items)) {
    json_response(['error' => 'Carrinho vazio.'], 422);
}

// ── Banco ─────────────────────────────────────────────────────────────────────
$pdo = db();
ensure_ingresso_tables($pdo);

// Carrega catálogo de tickets para validar preços e disponibilidade
$ticketsFlat = fetch_ingresso_tickets_flat($pdo);
$ticketByUid = [];
foreach ($ticketsFlat as $t) {
    $ticketByUid[$t['uid']] = $t;
}

// ── Validação dos itens ───────────────────────────────────────────────────────
$processedItems = [];
$total = 0.0;

foreach ($items as $item) {
    $uid  = trim((string) ($item['id'] ?? ''));
    $qty  = max(1, (int) ($item['qty'] ?? 1));

    if ($uid === 'credit') {
        // Crédito de consumo: valor informado pelo front (sem ticket no banco)
        $price = max(0.0, (float) ($item['price'] ?? 0));
        $total += $price * $qty;
        $processedItems[] = [
            'type'  => 'credit',
            'name'  => 'Crédito de consumo',
            'qty'   => $qty,
            'price' => $price,
        ];
        continue;
    }

    if (!isset($ticketByUid[$uid])) {
        json_response(['error' => "Ingresso '{$uid}' não encontrado."], 422);
    }

    $ticket = $ticketByUid[$uid];

    if ($ticket['status'] !== 'ativo') {
        json_response(['error' => "Ingresso '{$ticket['name']}' não está disponível."], 422);
    }

    if ($ticket['available'] < $qty) {
        json_response(['error' => "Sem estoque suficiente para '{$ticket['name']}'."], 422);
    }

    $unitPrice = (float) $ticket['basePrice'] + (float) $ticket['fee'];
    $total    += $unitPrice * $qty;

    $processedItems[] = [
        'type'       => 'ticket',
        'ticket_id'  => $ticket['id'],
        'ticket_uid' => $ticket['uid'],
        'name'       => $ticket['name'],
        'batch_name' => $ticket['batch_name'],
        'qty'        => $qty,
        'unit_price' => (float) $ticket['basePrice'],
        'fee'        => (float) $ticket['fee'],
    ];
}

if ($total <= 0) {
    json_response(['error' => 'Total inválido.'], 422);
}

// ── Validação dos titulares ───────────────────────────────────────────────────
// Cada ticket precisa de um array de titulares com nome e CPF válidos.
foreach ($processedItems as $item) {
    if ($item['type'] !== 'ticket') {
        continue;
    }

    $uid = $item['ticket_uid'];
    $qty = $item['qty'];
    $list = $attendees[$uid] ?? [];

    if (count($list) < $qty) {
        json_response(['error' => "Preencha todos os titulares de '{$item['name']}'."], 422);
    }

    for ($i = 0; $i < $qty; $i++) {
        $a = $list[$i] ?? [];
        $name = trim((string) ($a['name'] ?? ''));
        $cpf  = normalize_cpf((string) ($a['cpf'] ?? ''));

        if ($name === '') {
            json_response(['error' => "Nome do titular {$i + 1} de '{$item['name']}' é obrigatório."], 422);
        }

        if (!is_valid_cpf($cpf)) {
            json_response(['error' => "CPF do titular {$i + 1} de '{$item['name']}' é inválido."], 422);
        }
    }
}

// ── Grava pedido no banco ─────────────────────────────────────────────────────
$pedidoUid = bin2hex(random_bytes(12)); // 24 chars
$userId    = (int) $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Garante que as tabelas de pedidos existem
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pedidos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uid VARCHAR(32) NOT NULL UNIQUE,
            user_id INT UNSIGNED NULL,
            status ENUM('pendente','processando','aprovado','pendente_pix','rejeitado','cancelado','reembolsado') NOT NULL DEFAULT 'pendente',
            total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payer_email VARCHAR(255) NULL,
            payer_name VARCHAR(255) NULL,
            payer_cpf VARCHAR(20) NULL,
            mp_payment_id BIGINT NULL,
            mp_payment_status VARCHAR(60) NULL,
            mp_payment_method VARCHAR(60) NULL,
            mp_idempotency_key VARCHAR(80) NULL,
            pix_qr_code TEXT NULL,
            pix_qr_code_base64 TEXT NULL,
            pix_expiration_date DATETIME NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_pedidos_uid (uid),
            KEY idx_pedidos_status (status),
            KEY idx_pedidos_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pedido_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pedido_id BIGINT UNSIGNED NOT NULL,
            ticket_id INT UNSIGNED NOT NULL,
            ticket_uid VARCHAR(32) NOT NULL,
            ticket_nome VARCHAR(160) NOT NULL,
            batch_nome VARCHAR(160) NOT NULL DEFAULT '',
            titular_nome VARCHAR(255) NOT NULL,
            titular_cpf VARCHAR(20) NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            subtotal DECIMAL(10,2) NOT NULL,
            ingresso_uid VARCHAR(32) NULL,
            emitido_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pedido_items_pedido (pedido_id),
            CONSTRAINT fk_pedido_items_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Busca dados do usuário para pré-preencher pagador
    $stmtUser = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch() ?: [];

    $stmtPedido = $pdo->prepare('
        INSERT INTO pedidos (uid, user_id, status, total, payer_email, payer_name)
        VALUES (?, ?, \'pendente\', ?, ?, ?)
    ');
    $stmtPedido->execute([
        $pedidoUid,
        $userId,
        round($total, 2),
        $user['email'] ?? null,
        $user['name']  ?? null,
    ]);
    $pedidoId = (int) $pdo->lastInsertId();

    $stmtItem = $pdo->prepare('
        INSERT INTO pedido_items
            (pedido_id, ticket_id, ticket_uid, ticket_nome, batch_nome, titular_nome, titular_cpf, unit_price, fee, subtotal)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    foreach ($processedItems as $item) {
        if ($item['type'] !== 'ticket') {
            continue;
        }

        $uid = $item['ticket_uid'];
        $qty = $item['qty'];
        $list = $attendees[$uid];

        for ($i = 0; $i < $qty; $i++) {
            $a    = $list[$i];
            $name = trim((string) $a['name']);
            $cpf  = normalize_cpf((string) $a['cpf']);
            $subtotal = round(($item['unit_price'] + $item['fee']), 2);

            $stmtItem->execute([
                $pedidoId,
                $item['ticket_id'],
                $item['ticket_uid'],
                $item['name'],
                $item['batch_name'],
                $name,
                $cpf,
                $item['unit_price'],
                $item['fee'],
                $subtotal,
            ]);
        }
    }

    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[pedido/criar] ' . $e->getMessage());
    json_response(['error' => 'Erro interno ao criar pedido. Tente novamente.'], 500);
}

json_response([
    'success'    => true,
    'pedido_uid' => $pedidoUid,
    'total'      => round($total, 2),
]);
