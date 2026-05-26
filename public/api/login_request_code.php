<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Metodo nao permitido'], 405);
}

start_secure_session();
validate_csrf_or_fail($_POST['csrf_token'] ?? null);

$payload = require_post_fields(['identifier'], $_POST);
$identifier = trim($payload['identifier']);
$deliveryMethod = strtolower(trim($_POST['delivery_method'] ?? ''));
$email = '';
$cpf = '';

if (is_valid_email($identifier)) {
    $email = normalize_email($identifier);
} else {
    $cpfCandidate = normalize_cpf($identifier);
    if (is_valid_cpf($cpfCandidate)) {
        $cpf = $cpfCandidate;
    } else {
        json_response(['error' => 'E-mail ou CPF invalido.'], 422);
    }
}

$pdo = db();
ensure_ip_not_rate_limited($pdo, 'login');

$genericResponse = ['success' => true, 'message' => 'Se os dados estiverem corretos, enviaremos um codigo para o contato cadastrado.'];
if ($email !== '') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE cpf = :cpf LIMIT 1');
    $stmt->execute(['cpf' => $cpf]);
}
$user = $stmt->fetch();

if (!$user || $user['email_verified_at'] === null) {
    json_response($genericResponse);
}

$channel = in_array($deliveryMethod, ['email', 'whatsapp'], true)
    ? $deliveryMethod
    : ($user['verification_channel'] ?? 'email');

$targetEmail = $user['email'] ?? '';
$targetWhatsapp = $user['whatsapp_number'] ?? '';

ensure_not_on_cooldown($pdo, (int) $user['id'], 'login');
$codeData = create_verification_code($pdo, (int) $user['id'], 'login');
$dispatchContext = [
    'user_id' => (int) $user['id'],
    'email' => $targetEmail,
    'whatsapp' => $targetWhatsapp,
    'expires_at' => $codeData['expires_at'],
    'delivery_method' => $channel,
];

if ($channel === 'whatsapp') {
    if (!is_valid_br_phone($targetWhatsapp)) {
        json_response(['error' => 'Nao encontramos um WhatsApp cadastrado para esta conta.'], 400);
    }
    $sent = send_verification_whatsapp($targetWhatsapp, $codeData['code'], 'login', $dispatchContext);
    $responseMessage = 'Codigo enviado via WhatsApp.';
} else {
    $sent = send_verification_email($targetEmail, $codeData['code'], 'login', $dispatchContext);
    $responseMessage = 'Codigo enviado por e-mail.';
}

if (!$sent) {
    json_response(['error' => 'Nao foi possivel enviar o codigo.'], 500);
}

$response = ['success' => true, 'message' => $responseMessage];

json_response($response);
