<?php

declare(strict_types=1);

namespace App\Ingresso;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use App\Support\Env;

/**
 * Calculo puro de status de lote/ingresso (sem DB).
 * Isolado para que possa ser testado sem mock.
 */
final class BatchStatus
{
    public const STATUSES = ['ativo', 'em_breve', 'esgotado'];

    public static function batch(?string $startAt, ?string $endAt, ?DateTimeImmutable $now = null): string
    {
        try {
            $tzName = Env::string('APP_TIMEZONE', 'America/Porto_Velho');
            $tz = new DateTimeZone($tzName);
            $now = $now ?? new DateTimeImmutable('now', $tz);
            $start = $startAt ? new DateTimeImmutable($startAt, $tz) : null;
            $end = $endAt ? new DateTimeImmutable($endAt, $tz) : null;

            if ($end && $now > $end) {
                return 'esgotado';
            }
            if ($start && $now < $start) {
                return 'em_breve';
            }
        } catch (Throwable $e) {
            // fallback no padrao
        }

        return 'ativo';
    }

    public static function ticket(array $batch, array $ticket, ?DateTimeImmutable $now = null): string
    {
        $batchStatus = self::batch($batch['start_at'] ?? null, $batch['end_at'] ?? null, $now);
        $available = max(0, (int) ($ticket['available'] ?? 0));
        $progress = max(0, min(100, (int) ($ticket['progress'] ?? 0)));

        if ($batchStatus === 'em_breve') {
            return 'em_breve';
        }
        if ($batchStatus === 'esgotado' || $available <= 0 || $progress >= 100) {
            return 'esgotado';
        }

        return 'ativo';
    }
}
