<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método não permitido'], 405);
}

start_secure_session();
validate_csrf_or_fail($_POST['csrf_token'] ?? null);

$payload = require_post_fields(['email', 'cpf', 'code'], $_POST);
$email = normalize_email($payload['email']);
$cpf = normalize_cpf($payload['cpf']);
$code = trim($payload['code']);
$remember = !empty($_POST['remember_me']);

if (!is_valid_email($email) || !is_valid_cpf($cpf)) {
    json_response(['error' => 'Dados inválidos.'], 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND cpf = :cpf LIMIT 1');
$stmt->execute(['email' => $email, 'cpf' => $cpf]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['error' => 'Usuário não encontrado.'], 404);
}

if ($user['email_verified_at'] !== null) {
    json_response(['error' => 'E-mail já verificado. Faça login.'], 409);
}

assert_valid_code($pdo, (int) $user['id'], 'register', $code);

$stmt = $pdo->prepare('UPDATE users SET email_verified_at = NOW(), updated_at = NOW() WHERE id = :id');
$stmt->execute(['id' => $user['id']]);

create_user_session($user);
$userId = (int) $user['id'];
if ($remember) {
    issue_remember_token($pdo, $userId);
}

json_response(['success' => true, 'message' => 'Contato verificado com sucesso.']);
