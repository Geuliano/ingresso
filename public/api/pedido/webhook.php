<?php

declare(strict_types=1);

/**
 * POST /api/pedido/webhook
 *
 * Endpoint de notificação do Mercado Pago (Webhooks).
 * Configure a URL no painel do MP em:
 *   Suas integrações → Webhooks → Adicionar → payment
 *
 * Documentação: https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks
 */

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/n8n.php';

use App\Pagamento\MercadoPagoService;

// Webhook sempre responde 200 rapidamente; processamento ocorre depois.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(200);
    exit;
}

// ── Lê o body bruto ───────────────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody, true) ?? [];

// ── Verifica assinatura ───────────────────────────────────────────────────────
$config  = app_config();
$mpCfg   = $config['mercadopago'] ?? [];

$mp = new MercadoPagoService(
    $mpCfg['access_token']   ?? '',
    $mpCfg['public_key']     ?? '',
    $mpCfg['webhook_secret'] ?? '',
);

$xSignature = $_SERVER['HTTP_X_SIGNATURE']  ?? '';
$xRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';

// O campo data.id vem no body ou na query string
$dataId = (string) ($body['data']['id'] ?? $_GET['data_id'] ?? '');

if ($xSignature !== '' && !$mp->verifyWebhookSignature($xSignature, $xRequestId, $dataId)) {
    error_log('[webhook] Assinatura inválida. Ignorando.');
    http_response_code(200);
    exit;
}

// ── Processa apenas notificações de pagamento ─────────────────────────────────
$type   = $body['type']   ?? $body['topic'] ?? '';
$action = $body['action'] ?? '';

if ($type !== 'payment' && $type !== 'topic_payment_updated') {
    http_response_code(200);
    exit;
}

$mpPaymentId = (int) $dataId;
if ($mpPaymentId === 0) {
    http_response_code(200);
    exit;
}

// ── Consulta o status real do pagamento no MP ─────────────────────────────────
$result = $mp->getPayment($mpPaymentId);
if ($result['code'] !== 200) {
    error_log('[webhook] Não foi possível consultar pagamento ' . $mpPaymentId);
    http_response_code(200);
    exit;
}

$mpBody          = $result['body'];
$mpStatus        = strtolower($mpBody['status'] ?? '');
$externalRef     = $mpBody['external_reference'] ?? ''; // nosso pedido_uid
$mpMethod        = strtolower($mpBody['payment_method_id'] ?? '');

if ($externalRef === '') {
    http_response_code(200);
    exit;
}

// ── Atualiza o pedido ─────────────────────────────────────────────────────────
$pdo = db();

$stmtPedido = $pdo->prepare('
    SELECT * FROM pedidos
    WHERE uid = ? AND deleted_at IS NULL
    LIMIT 1
');
$stmtPedido->execute([$externalRef]);
$pedido = $stmtPedido->fetch();

if (!$pedido) {
    error_log('[webhook] Pedido não encontrado: ' . $externalRef);
    http_response_code(200);
    exit;
}

// Evita reprocessar pedidos já aprovados
if ($pedido['status'] === 'aprovado' || $pedido['status'] === 'reembolsado') {
    http_response_code(200);
    exit;
}

$internalStatus = match ($mpStatus) {
    'approved'   => 'aprovado',
    'pending'    => $mpMethod === 'pix' ? 'pendente_pix' : 'processando',
    'in_process' => 'processando',
    'refunded', 'charged_back' => 'reembolsado',
    'cancelled'  => 'cancelado',
    default      => 'rejeitado',
};

$paidAt = ($internalStatus === 'aprovado') ? date('Y-m-d H:i:s') : null;

$pdo->prepare('
    UPDATE pedidos
    SET status = ?,
        mp_payment_id = ?,
        mp_payment_status = ?,
        mp_payment_method = ?,
        paid_at = COALESCE(paid_at, ?),
        updated_at = NOW()
    WHERE id = ?
')->execute([
    $internalStatus,
    $mpPaymentId,
    $mpStatus,
    $mpMethod,
    $paidAt,
    (int) $pedido['id'],
]);

// ── Se aprovado: emite ingressos e notifica ───────────────────────────────────
if ($internalStatus === 'aprovado') {
    emitir_e_notificar($pdo, (int) $pedido['id'], $pedido);
}

http_response_code(200);
exit;

// ── Funções ───────────────────────────────────────────────────────────────────

function emitir_e_notificar(PDO $pdo, int $pedidoId, array $pedido): void
{
    try {
        // Emite ingressos não emitidos
        $stmt = $pdo->prepare('
            SELECT * FROM pedido_items
            WHERE pedido_id = ? AND ingresso_uid IS NULL
        ');
        $stmt->execute([$pedidoId]);
        $items = $stmt->fetchAll();

        $upd  = $pdo->prepare('UPDATE pedido_items SET ingresso_uid = ?, emitido_at = NOW() WHERE id = ?');
        $decr = $pdo->prepare('
            UPDATE ingresso_tickets
            SET available = GREATEST(0, available - 1),
                progress  = LEAST(100, progress + 1)
            WHERE id = ?
        ');

        $ingressosEmitidos = [];
        foreach ($items as $item) {
            $uid = bin2hex(random_bytes(8));
            $upd->execute([$uid, $item['id']]);
            $decr->execute([$item['ticket_id']]);
            $ingressosEmitidos[] = [
                'uid'          => $uid,
                'titular_nome' => $item['titular_nome'],
                'ticket_nome'  => $item['ticket_nome'],
                'batch_nome'   => $item['batch_nome'],
            ];
        }

        // Busca e-mail do usuário
        $stmtUser = $pdo->prepare('SELECT email, name FROM users WHERE id = ? LIMIT 1');
        $stmtUser->execute([$pedido['user_id']]);
        $user = $stmtUser->fetch() ?: [];

        // Notifica via n8n (se configurado)
        $config = app_config();
        if (!empty($config['n8n']['enabled'])) {
            $n8nPayload = [
                'event'     => 'pagamento_aprovado',
                'pedido_uid'=> $pedido['uid'],
                'total'     => $pedido['total'],
                'payer'     => [
                    'email' => $user['email'] ?? $pedido['payer_email'],
                    'name'  => $user['name']  ?? $pedido['payer_name'],
                ],
                'ingressos' => $ingressosEmitidos,
            ];
            dispatch_to_n8n('pagamento_aprovado', $n8nPayload);
        }
    } catch (\Throwable $e) {
        error_log('[webhook/emitir] ' . $e->getMessage());
    }
}

/**
 * Envia payload genérico ao n8n para ações pós-pagamento.
 */
function dispatch_to_n8n(string $event, array $payload): void
{
    $config  = app_config();
    $n8nCfg  = $config['n8n'] ?? [];
    $url     = $n8nCfg['webhook_url'] ?? '';
    $token   = $n8nCfg['auth_token']  ?? '';
    $timeout = (int) ($n8nCfg['timeout'] ?? 5);

    if ($url === '') {
        return;
    }

    $body = json_encode(array_merge(['event' => $event], $payload), JSON_UNESCAPED_UNICODE);

    $headers = ['Content-Type: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
