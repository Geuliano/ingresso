<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método não permitido'], 405);
}

start_secure_session();
validate_csrf_or_fail($_POST['csrf_token'] ?? null);

[$name, $email, $cpf, $whatsapp, $deliveryMethod] = (function () {
    $payload = require_post_fields(['name', 'email', 'cpf', 'whatsapp', 'delivery_method'], $_POST);
    return [
        trim($payload['name']),
        normalize_email($payload['email']),
        normalize_cpf($payload['cpf']),
        normalize_br_phone($payload['whatsapp']),
        strtolower(trim($payload['delivery_method'])),
    ];
})();

if ($name === '' || mb_strlen($name) < 2) {
    json_response(['error' => 'Informe um nome válido.'], 422);
}

if (!is_valid_email($email)) {
    json_response(['error' => 'E-mail inválido.'], 422);
}

if (!is_valid_cpf($cpf)) {
    json_response(['error' => 'CPF inválido.'], 422);
}

$channel = in_array($deliveryMethod, ['email', 'whatsapp'], true) ? $deliveryMethod : 'email';

if (!is_valid_br_phone($whatsapp)) {
    json_response(['error' => 'WhatsApp invalido. Use DDD + numero.'], 422);
}

$pdo = db();
ensure_ip_not_rate_limited($pdo, 'register');

// Resposta generica para evitar enumeracao: tanto quando os dados
// pertencem a um usuario ja verificado quanto quando ja existe registro
// nao verificado, devolvemos a mesma mensagem.
$genericResponse = ['success' => true, 'message' => 'Verifique o canal selecionado para confirmar o cadastro.'];

$pdo->beginTransaction();

// Procura separadamente por email e por cpf para evitar cruzar identidades.
$byEmail = $pdo->prepare('SELECT id, email, cpf, whatsapp_number, email_verified_at, verification_channel FROM users WHERE email = :email LIMIT 1');
$byEmail->execute(['email' => $email]);
$userByEmail = $byEmail->fetch() ?: null;

$byCpf = $pdo->prepare('SELECT id, email, cpf, whatsapp_number, email_verified_at, verification_channel FROM users WHERE cpf = :cpf LIMIT 1');
$byCpf->execute(['cpf' => $cpf]);
$userByCpf = $byCpf->fetch() ?: null;

// Conflito: email/cpf pertencem a usuarios diferentes ja verificados.
if ($userByEmail && $userByCpf && $userByEmail['id'] !== $userByCpf['id']) {
    $pdo->rollBack();
    json_response(['error' => 'Ja existe uma conta com este e-mail ou CPF. Faca login para continuar.'], 409);
}

// Se ja existe usuario verificado para este email OU cpf, redirecionar para login.
if (($userByEmail && $userByEmail['email_verified_at'] !== null)
    || ($userByCpf && $userByCpf['email_verified_at'] !== null)) {
    $pdo->rollBack();
    json_response(['error' => 'Ja existe uma conta com este e-mail ou CPF. Faca login para continuar.'], 409);
}

$existing = $userByEmail ?: $userByCpf;

if ($existing) {
    // Verificacao chave: nao sobrescrever dados de quem nao confirmou.
    // Para evitar account takeover, exigimos que email E cpf do payload
    // batam com o registro existente. Se nao baterem, devolve resposta
    // generica e dispara codigo para os contatos JA cadastrados.
    $sameEmail = strcasecmp((string)$existing['email'], $email) === 0;
    $sameCpf = (string)$existing['cpf'] === $cpf;

    if (!$sameEmail || !$sameCpf) {
        // Identidade nao confere: re-emitimos codigo para o usuario
        // original sem aceitar nenhum dado novo. Atacante nao consegue
        // sequestrar a conta nem descobrir que ela existe.
        $userId = (int) $existing['id'];

        try {
            ensure_not_on_cooldown($pdo, $userId, 'register');
        } catch (Throwable $e) {
            $pdo->rollBack();
            json_response($genericResponse);
        }

        $codeData = create_verification_code($pdo, $userId, 'register');
        $originalChannel = $existing['verification_channel'] ?? 'email';
        $dispatchContext = [
            'user_id' => $userId,
            'email' => $existing['email'],
            'whatsapp' => $existing['whatsapp_number'],
            'expires_at' => $codeData['expires_at'],
            'delivery_method' => $originalChannel,
        ];

        if ($originalChannel === 'whatsapp') {
            send_verification_whatsapp((string)$existing['whatsapp_number'], $codeData['code'], 'registro', $dispatchContext);
        } else {
            send_verification_email((string)$existing['email'], $codeData['code'], 'registro', $dispatchContext);
        }

        $pdo->commit();
        json_response($genericResponse);
    }

    // Mesmo email + mesmo cpf -> reaproveita registro nao verificado,
    // permitindo apenas atualizar telefone/canal/nome (sem trocar identidade).
    $stmt = $pdo->prepare('UPDATE users SET name = :name, whatsapp_number = :whatsapp, verification_channel = :channel, updated_at = NOW() WHERE id = :id');
    $stmt->execute([
        'name' => $name,
        'whatsapp' => $whatsapp,
        'channel' => $channel,
        'id' => $existing['id'],
    ]);
    $userId = (int) $existing['id'];
} else {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, cpf, whatsapp_number, verification_channel, created_at, updated_at) VALUES (:name, :email, :cpf, :whatsapp, :channel, NOW(), NOW())');
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'cpf' => $cpf,
        'whatsapp' => $whatsapp,
        'channel' => $channel,
    ]);
    $userId = (int) $pdo->lastInsertId();
}

try {
    ensure_not_on_cooldown($pdo, $userId, 'register');
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response($genericResponse);
}

$codeData = create_verification_code($pdo, $userId, 'register');
$dispatchContext = [
    'user_id' => $userId,
    'name' => $name,
    'email' => $email,
    'whatsapp' => $whatsapp,
    'expires_at' => $codeData['expires_at'],
    'delivery_method' => $channel,
];

$sent = $channel === 'whatsapp'
    ? send_verification_whatsapp($whatsapp, $codeData['code'], 'registro', $dispatchContext)
    : send_verification_email($email, $codeData['code'], 'registro', $dispatchContext);

if (!$sent) {
    $pdo->rollBack();
    $target = $channel === 'whatsapp' ? 'WhatsApp' : 'e-mail';
    json_response(['error' => 'Nao foi possivel enviar o codigo por ' . $target . '.'], 500);
}

$pdo->commit();

json_response($genericResponse);
