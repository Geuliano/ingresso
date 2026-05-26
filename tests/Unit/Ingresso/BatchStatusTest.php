<?php

declare(strict_types=1);

namespace Tests\Unit\Ingresso;

use App\Ingresso\BatchStatus;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class BatchStatusTest extends TestCase
{
    private function tz(): DateTimeZone
    {
        return new DateTimeZone('America/Porto_Velho');
    }

    public function testBatchActiveWhenInsideWindow(): void
    {
        $now = new DateTimeImmutable('2026-02-10 12:00:00', $this->tz());
        $status = BatchStatus::batch('2026-02-01 00:00:00', '2026-02-20 23:59:59', $now);
        self::assertSame('ativo', $status);
    }

    public function testBatchEmBreveBeforeStart(): void
    {
        $now = new DateTimeImmutable('2026-01-15 10:00:00', $this->tz());
        $status = BatchStatus::batch('2026-02-01 00:00:00', '2026-02-20 23:59:59', $now);
        self::assertSame('em_breve', $status);
    }

    public function testBatchEsgotadoAfterEnd(): void
    {
        $now = new DateTimeImmutable('2026-03-01 00:00:00', $this->tz());
        $status = BatchStatus::batch('2026-02-01 00:00:00', '2026-02-20 23:59:59', $now);
        self::assertSame('esgotado', $status);
    }

    public function testBatchActiveWhenNoDates(): void
    {
        self::assertSame('ativo', BatchStatus::batch(null, null));
    }

    public function testTicketEsgotadoWhenAvailableZero(): void
    {
        $now = new DateTimeImmutable('2026-02-10 12:00:00', $this->tz());
        $batch = ['start_at' => '2026-02-01 00:00:00', 'end_at' => '2026-02-20 23:59:59'];
        $ticket = ['available' => 0, 'progress' => 50];
        self::assertSame('esgotado', BatchStatus::ticket($batch, $ticket, $now));
    }

    public function testTicketEsgotadoWhenProgress100(): void
    {
        $now = new DateTimeImmutable('2026-02-10 12:00:00', $this->tz());
        $batch = ['start_at' => '2026-02-01 00:00:00', 'end_at' => '2026-02-20 23:59:59'];
        $ticket = ['available' => 5, 'progress' => 100];
        self::assertSame('esgotado', BatchStatus::ticket($batch, $ticket, $now));
    }

    public function testTicketAtivoWhenInWindowAndAvailable(): void
    {
        $now = new DateTimeImmutable('2026-02-10 12:00:00', $this->tz());
        $batch = ['start_at' => '2026-02-01 00:00:00', 'end_at' => '2026-02-20 23:59:59'];
        $ticket = ['available' => 100, 'progress' => 30];
        self::assertSame('ativo', BatchStatus::ticket($batch, $ticket, $now));
    }

    public function testTicketInheritsEmBreveFromBatch(): void
    {
        $now = new DateTimeImmutable('2026-01-15 12:00:00', $this->tz());
        $batch = ['start_at' => '2026-02-01 00:00:00', 'end_at' => '2026-02-20 23:59:59'];
        $ticket = ['available' => 100, 'progress' => 0];
        self::assertSame('em_breve', BatchStatus::ticket($batch, $ticket, $now));
    }
}
