<?php

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use App\Support\Env;

/**
 * Geracao, validacao e rate-limit de codigos de verificacao.
 */
final class VerificationService
{
    private int $codeLength;
    private int $expiresIn;
    private int $cooldown;
    private int $maxAttempts;

    public function __construct(private PDO $pdo)
    {
        $this->codeLength = Env::int('VERIFICATION_CODE_LENGTH', 6);
        $this->expiresIn = Env::int('VERIFICATION_EXPIRES_IN', 600);
        $this->cooldown = Env::int('VERIFICATION_COOLDOWN', 60);
        $this->maxAttempts = Env::int('VERIFICATION_MAX_ATTEMPTS', 3);
    }

    public function generateNumericCode(): string
    {
        $code = '';
        for ($i = 0; $i < $this->codeLength; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }

    /**
     * HMAC-SHA256 do codigo com a APP_KEY.
     */
    public function hashCode(string $code): string
    {
        $appKey = Env::string('APP_KEY', '');
        if ($appKey === '' || $appKey === 'change-me-please-32chars-minimum' || strlen($appKey) < 32) {
            throw new RuntimeException('APP_KEY nao configurada. Defina um segredo de 32+ caracteres.');
        }
        return hash_hmac('sha256', $code, $appKey);
    }

    public function latestCode(int $userId, string $purpose): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM verification_codes WHERE user_id = :uid AND purpose = :purpose ORDER BY id DESC LIMIT 1');
        $stmt->execute(['uid' => $userId, 'purpose' => $purpose]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array{code: string, expires_at: string}
     */
    public function create(int $userId, string $purpose): array
    {
        $code = $this->generateNumericCode();
        $expiresAt = (new DateTimeImmutable("+{$this->expiresIn} seconds"))->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('INSERT INTO verification_codes (user_id, purpose, code_hash, expires_at, attempts_left, created_ip, created_ua) VALUES (:uid, :purpose, :hash, :expires, :attempts, :ip, :ua)');
        $stmt->execute([
            'uid' => $userId,
            'purpose' => $purpose,
            'hash' => $this->hashCode($code),
            'expires' => $expiresAt,
            'attempts' => $this->maxAttempts,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return ['code' => $code, 'expires_at' => $expiresAt];
    }

    public function isOnCooldown(int $userId, string $purpose): bool
    {
        $stmt = $this->pdo->prepare('SELECT created_at FROM verification_codes WHERE user_id = :uid AND purpose = :purpose ORDER BY id DESC LIMIT 1');
        $stmt->execute(['uid' => $userId, 'purpose' => $purpose]);
        $row = $stmt->fetch();
        if (!$row) return false;
        $lastTime = strtotime($row['created_at']);
        return $lastTime !== false && (time() - $lastTime) < $this->cooldown;
    }

    public function countIpAttempts(string $purpose, string $ip, int $windowSeconds = 300): int
    {
        if ($ip === '') return 0;
        $since = date('Y-m-d H:i:s', time() - $windowSeconds);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS attempts FROM verification_codes WHERE created_ip = :ip AND purpose = :purpose AND created_at >= :since');
        $stmt->execute(['ip' => $ip, 'purpose' => $purpose, 'since' => $since]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Resultado: ['ok' => bool, 'error' => string|null, 'attempts_left' => int|null, 'row' => array|null].
     */
    public function validate(int $userId, string $purpose, string $code): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM verification_codes WHERE user_id = :uid AND purpose = :purpose AND consumed_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute(['uid' => $userId, 'purpose' => $purpose]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['ok' => false, 'error' => 'Codigo nao encontrado ou expirado.', 'attempts_left' => null, 'row' => null, 'status' => 400];
        }

        if ((int) $row['attempts_left'] <= 0) {
            return ['ok' => false, 'error' => 'Limite de tentativas excedido. Solicite um novo codigo.', 'attempts_left' => 0, 'row' => null, 'status' => 429];
        }

        if ($this->hashCode($code) !== $row['code_hash']) {
            $upd = $this->pdo->prepare('UPDATE verification_codes SET attempts_left = attempts_left - 1 WHERE id = :id');
            $upd->execute(['id' => $row['id']]);
            $remaining = max(0, (int) $row['attempts_left'] - 1);
            return ['ok' => false, 'error' => 'Codigo invalido.', 'attempts_left' => $remaining, 'row' => null, 'status' => 400];
        }

        $upd = $this->pdo->prepare('UPDATE verification_codes SET consumed_at = NOW() WHERE id = :id');
        $upd->execute(['id' => $row['id']]);

        return ['ok' => true, 'error' => null, 'attempts_left' => (int) $row['attempts_left'], 'row' => $row, 'status' => 200];
    }
}
