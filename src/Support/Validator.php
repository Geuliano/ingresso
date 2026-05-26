<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validadores puros (sem dependencia de DB) reutilizados em multiplos
 * pontos da aplicacao. Mantidos como metodos estaticos para facilitar testes.
 */
final class Validator
{
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function normalizeCpf(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf) ?? '';
    }

    public static function normalizeBrPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 13 && str_starts_with($digits, '55')) {
            return $digits;
        }

        if (strlen($digits) === 11) {
            return '55' . $digits;
        }

        if (strlen($digits) === 13) {
            return '55' . substr($digits, -11);
        }

        return $digits;
    }

    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isValidCpf(string $cpf): bool
    {
        $cpf = self::normalizeCpf($cpf);
        if (strlen($cpf) !== 11) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($c = 0; $c < $t; $c++) {
                $sum += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function isValidBrPhone(string $phone): bool
    {
        $normalized = self::normalizeBrPhone($phone);
        if (strlen($normalized) !== 13 || strpos($normalized, '55') !== 0) {
            return false;
        }

        $ddd = (int) substr($normalized, 2, 2);
        $firstLocalDigit = (int) substr($normalized, 4, 1);

        return $ddd >= 11 && $ddd <= 99 && $firstLocalDigit >= 2;
    }
}
