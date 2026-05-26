<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método não permitido'], 405);
}

start_secure_session();
validate_csrf_or_fail($_POST['csrf_token'] ?? null);

$payload = require_post_fields(['identifier', 'code'], $_POST);
$identifier = trim($payload['identifier']);
$code = trim($payload['code']);
$remember = !empty($_POST['remember_me']);
$email = '';
$cpf = '';

if (is_valid_email($identifier)) {
    $email = normalize_email($identifier);
} else {
    $cpfCandidate = normalize_cpf($identifier);
    if (is_valid_cpf($cpfCandidate)) {
        $cpf = $cpfCandidate;
    } else {
        json_response(['error' => 'E-mail ou CPF inválido.'], 422);
    }
}

$pdo = db();
if ($email !== '') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE cpf = :cpf LIMIT 1');
    $stmt->execute(['cpf' => $cpf]);
}
$user = $stmt->fetch();

if (!$user) {
    json_response(['error' => 'Dados inválidos.'], 400);
}

if ($user['email_verified_at'] === null) {
    json_response(['error' => 'Dados inválidos.'], 400);
}

assert_valid_code($pdo, (int) $user['id'], 'login', $code);
create_user_session($user);
$userId = (int) $user['id'];
if ($remember) {
    issue_remember_token($pdo, $userId);
}

json_response(['success' => true, 'message' => 'Login realizado com sucesso.']);
