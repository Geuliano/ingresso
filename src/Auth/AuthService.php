<?php

declare(strict_types=1);

namespace App\Auth;

use PDO;

/**
 * Servico de sessao do usuario autenticado.
 *
 * Mantido fino: o objetivo desta classe e centralizar a logica de
 * "quem esta logado" para permitir testes e migracoes futuras.
 */
final class AuthService
{
    public function __construct(private PDO $pdo) {}

    public function fetchUserById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, cpf, whatsapp_number, verification_channel, email_verified_at, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function setSessionUser(array $user): void
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'] ?? null;
    }

    public function currentUserId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return $id ? (int) $id : null;
    }
}
