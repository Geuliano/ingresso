<?php

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;
use PDO;
use App\Support\Env;

/**
 * Gerencia tokens persistentes (remember-me) com selector/validator.
 */
final class RememberTokenService
{
    private string $cookieName;
    private int $expiresIn;

    public function __construct(private PDO $pdo)
    {
        $this->cookieName = Env::string('REMEMBER_COOKIE_NAME', 'remember_token');
        $this->expiresIn = Env::int('REMEMBER_EXPIRES_IN', 60 * 60 * 24 * 30);
    }

    public function cookieName(): string
    {
        return $this->cookieName;
    }

    public function expiration(): DateTimeImmutable
    {
        return new DateTimeImmutable("+{$this->expiresIn} seconds");
    }

    public function issue(int $userId): void
    {
        $selector = bin2hex(random_bytes(10));
        $validator = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $validator);
        $expiresAt = $this->expiration()->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, created_ip, created_ua) VALUES (:uid, :selector, :hash, :expires, :ip, :ua)');
        $stmt->execute([
            'uid' => $userId,
            'selector' => $selector,
            'hash' => $tokenHash,
            'expires' => $expiresAt,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        $this->setCookie($selector, $validator, new DateTimeImmutable($expiresAt));
    }

    public function deleteBySelector(string $selector): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $stmt->execute(['selector' => $selector]);
    }

    public function deleteByUserId(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
    }

    public function findValid(string $selector): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM remember_tokens WHERE selector = :selector AND expires_at >= NOW() LIMIT 1');
        $stmt->execute(['selector' => $selector]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function setCookie(string $selector, string $validator, DateTimeImmutable $expiresAt): void
    {
        $isSecure = $this->isHttps();
        setcookie($this->cookieName, $selector . ':' . $validator, [
            'expires' => $expiresAt->getTimestamp(),
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public function clearCookie(): void
    {
        $isSecure = $this->isHttps();
        setcookie($this->cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private function isHttps(): bool
    {
        $httpsHeader = $_SERVER['HTTPS'] ?? '';
        $forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        return $httpsHeader === 'on' || $forwardedProto === 'https';
    }
}
