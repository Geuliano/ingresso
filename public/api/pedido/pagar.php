<?php

declare(strict_types=1);

/**
 * POST /api/pedido/pagar
 *
 * Recebe o formData do Payment Brick (Mercado Pago) e o UID do pedido,
 * processa o pagamento via API do MP e retorna o resultado.
 *
 * Para PIX: retorna { success, payment_method: 'pix', pix: { qr_code, qr_code_base64, expiration } }
 * Para Cartão: retorna { success, payment_method: 'card', status: 'approved'|'rejected', message }
 */

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

use App\Pagamento\MercadoPagoService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método não permitido'], 405);
}

// ── Autenticação ─────────────────────────────────────────────────────────────
start_secure_session();
if (empty($_SESSION['user_id'])) {
    json_response(['error' => 'Não autorizado.'], 401);
}

// ── Payload ───────────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$pedidoUid = trim((string) ($body['pedido_uid'] ?? ''));
$formData  = $body['form_data'] ?? [];   // dados do Brick
$payerCpf  = trim((string) ($body['payer_cpf'] ?? ''));

if ($pedidoUid === '' || !is_array($formData)) {
    json_response(['error' => 'Dados inválidos.'], 422);
}

$payerCpf = normalize_cpf($payerCpf);

// ── Carrega pedido ────────────────────────────────────────────────────────────
$pdo = db();
$stmt = $pdo->prepare('
    SELECT * FROM pedidos
    WHERE uid = ? AND user_id = ? AND deleted_at IS NULL
    LIMIT 1
');
$stmt->execute([$pedidoUid, (int) $_SESSION['user_id']]);
$pedido = $stmt->fetch();

if (!$pedido) {
    json_response(['error' => 'Pedido não encontrado.'], 404);
}

if (!in_array($pedido['status'], ['pendente', 'rejeitado'], true)) {
    json_response(['error' => 'Este pedido não pode ser pago no momento.'], 409);
}

// ── Instancia o serviço do MP ─────────────────────────────────────────────────
$config = app_config();
$mpCfg  = $config['mercadopago'] ?? [];

if (empty($mpCfg['access_token'])) {
    error_log('[pedido/pagar] MP_ACCESS_TOKEN não configurado.');
    json_response(['error' => 'Pagamento indisponível no momento.'], 503);
}

$mp = new MercadoPagoService(
    $mpCfg['access_token'],
    $mpCfg['public_key'] ?? '',
    $mpCfg['webhook_secret'] ?? '',
);

// ── Determina método de pagamento ─────────────────────────────────────────────
$paymentMethodId = strtolower(trim((string) ($formData['payment_method_id'] ?? '')));
$isPix = $paymentMethodId === 'pix';

$payerEmail = $pedido['payer_email'] ?? ($_SESSION['user_email'] ?? '');
$payerName  = $pedido['payer_name']  ?? '';
$total      = (float) $pedido['total'];

// Chave de idempotência: uid do pedido + método (evita cobrança dupla)
$idempotencyKey = $pedidoUid . '-' . ($isPix ? 'pix' : 'card');

// ── Monta payload ─────────────────────────────────────────────────────────────
if ($isPix) {
    if ($payerCpf === '' || !is_valid_cpf($payerCpf)) {
        json_response(['error' => 'CPF inválido para pagamento PIX.'], 422);
    }
    $payload = $mp->buildPixPayload($total, $pedidoUid, $payerEmail, $payerCpf, $payerName);
} else {
    // Cartão
    if (empty($formData['token'])) {
        json_response(['error' => 'Token de cartão ausente.'], 422);
    }
    if ($payerCpf === '' || !is_valid_cpf($payerCpf)) {
        json_response(['error' => 'CPF inválido para pagamento com cartão.'], 422);
    }
    $payload = $mp->buildCardPayload($formData, $total, $pedidoUid, $payerEmail, $payerCpf, $payerName);
}

// ── Marca como processando ────────────────────────────────────────────────────
$pdo->prepare('
    UPDATE pedidos SET status = \'processando\', payer_cpf = ?, mp_idempotency_key = ?, updated_at = NOW()
    WHERE id = ?
')->execute([$payerCpf, $idempotencyKey, (int) $pedido['id']]);

// ── Chama a API do MP ─────────────────────────────────────────────────────────
$result = $mp->createPayment($payload, $idempotencyKey);
$mpCode = $result['code'];
$mpBody = $result['body'];

error_log('[pedido/pagar] MP response code=' . $mpCode . ' body=' . json_encode($mpBody));

// ── Trata resposta ────────────────────────────────────────────────────────────
$mpStatus    = strtolower($mpBody['status'] ?? '');
$mpPaymentId = (int) ($mpBody['id'] ?? 0);
$mpMethod    = strtolower($mpBody['payment_method_id'] ?? $paymentMethodId);

if ($mpCode < 200 || $mpCode >= 300 || $mpPaymentId === 0) {
    // Falha na chamada
    $errMsg = $mpBody['message'] ?? 'Erro ao processar pagamento.';
    $pdo->prepare('UPDATE pedidos SET status = \'rejeitado\', mp_payment_status = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$errMsg, (int) $pedido['id']]);
    json_response(['error' => $errMsg], 422);
}

// ── PIX ───────────────────────────────────────────────────────────────────────
if ($isPix || $mpMethod === 'pix') {
    $pixData   = $mp->extractPixData($mpBody);
    $pixExpiry = $pixData['expiration']
        ? (new \DateTimeImmutable($pixData['expiration']))->format('Y-m-d H:i:s')
        : null;

    $pdo->prepare('
        UPDATE pedidos
        SET status = \'pendente_pix\',
            mp_payment_id = ?,
            mp_payment_status = ?,
            mp_payment_method = \'pix\',
            pix_qr_code = ?,
            pix_qr_code_base64 = ?,
            pix_expiration_date = ?,
            updated_at = NOW()
        WHERE id = ?
    ')->execute([
        $mpPaymentId,
        $mpStatus,
        $pixData['qr_code'],
        $pixData['qr_code_base64'],
        $pixExpiry,
        (int) $pedido['id'],
    ]);

    json_response([
        'success'        => true,
        'payment_method' => 'pix',
        'pix'            => [
            'qr_code'        => $pixData['qr_code'],
            'qr_code_base64' => $pixData['qr_code_base64'],
            'expiration'     => $pixExpiry,
        ],
    ]);
}

// ── Cartão ────────────────────────────────────────────────────────────────────
$internalStatus = match ($mpStatus) {
    'approved'                              => 'aprovado',
    'authorized', 'in_process', 'pending'   => 'processando',
    default                                 => 'rejeitado',
};

$updateStmt = $pdo->prepare('
    UPDATE pedidos
    SET status = ?,
        mp_payment_id = ?,
        mp_payment_status = ?,
        mp_payment_method = ?,
        paid_at = ?,
        updated_at = NOW()
    WHERE id = ?
');
$updateStmt->execute([
    $internalStatus,
    $mpPaymentId,
    $mpStatus,
    $mpMethod,
    $internalStatus === 'aprovado' ? date('Y-m-d H:i:s') : null,
    (int) $pedido['id'],
]);

// Se aprovado, emite os ingressos
if ($internalStatus === 'aprovado') {
    emitir_ingressos_pedido($pdo, (int) $pedido['id']);
}

$mensagens = [
    'aprovado'    => 'Pagamento aprovado! Seus ingressos foram emitidos.',
    'processando' => 'Pagamento em análise. Você será notificado em breve.',
    'rejeitado'   => 'Pagamento recusado. Verifique os dados e tente novamente.',
];

json_response([
    'success'        => $internalStatus === 'aprovado',
    'payment_method' => 'card',
    'status'         => $internalStatus,
    'pedido_uid'     => $pedidoUid,
    'message'        => $mensagens[$internalStatus] ?? 'Status desconhecido.',
]);

// ── Funções auxiliares ────────────────────────────────────────────────────────

function emitir_ingressos_pedido(PDO $pdo, int $pedidoId): void
{
    try {
        $stmt = $pdo->prepare('
            SELECT * FROM pedido_items
            WHERE pedido_id = ? AND ingresso_uid IS NULL
        ');
        $stmt->execute([$pedidoId]);
        $items = $stmt->fetchAll();

        $upd = $pdo->prepare('
            UPDATE pedido_items
            SET ingresso_uid = ?, emitido_at = NOW()
            WHERE id = ?
        ');

        // Decrementa estoque
        $decr = $pdo->prepare('
            UPDATE ingresso_tickets
            SET available = GREATEST(0, available - 1),
                progress  = LEAST(100, progress + 1)
            WHERE id = ?
        ');

        foreach ($items as $item) {
            $uid = bin2hex(random_bytes(8)); // 16 chars
            $upd->execute([$uid, $item['id']]);
            $decr->execute([$item['ticket_id']]);
        }
    } catch (\Throwable $e) {
        error_log('[emitir_ingressos] ' . $e->getMessage());
    }
}
