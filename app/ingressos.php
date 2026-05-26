<?php

declare(strict_types=1);

const INGRESSO_STATUSES = ['ativo', 'em_breve', 'esgotado'];

function ingresso_calculate_batch_status(?string $startAt, ?string $endAt): string
{
    try {
        $tz = new DateTimeZone('America/Porto_Velho');
        $now = new DateTimeImmutable('now', $tz);
        $start = $startAt ? new DateTimeImmutable($startAt, $tz) : null;
        $end = $endAt ? new DateTimeImmutable($endAt, $tz) : null;

        if ($end && $now > $end) {
            return 'esgotado';
        }
        if ($start && $now < $start) {
            return 'em_breve';
        }
    } catch (Throwable $e) {
        // em caso de erro, cai no status ativo padrao
    }

    return 'ativo';
}

function ingresso_calculate_ticket_status(array $batch, array $ticket): string
{
    $batchStatus = ingresso_calculate_batch_status($batch['start_at'] ?? null, $batch['end_at'] ?? null);
    $available = max(0, (int)($ticket['available'] ?? 0));
    $progress = max(0, min(100, (int)($ticket['progress'] ?? 0)));

    if ($batchStatus === 'em_breve') {
        return 'em_breve';
    }

    if ($batchStatus === 'esgotado' || $available <= 0 || $progress >= 100) {
        return 'esgotado';
    }

    return 'ativo';
}

function ingresso_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
              AND table_name = ? 
              AND column_name = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function generate_ingresso_uid(): string
{
    try {
        return bin2hex(random_bytes(8)); // 16 chars
    } catch (Throwable $e) {
        return bin2hex(openssl_random_pseudo_bytes(8));
    }
}

function ingresso_normalize_slug(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    }

    $value = preg_replace('/[^a-z0-9_\-]+/', '-', $value);
    $value = trim((string)$value, '-');

    return $value;
}

function ingresso_parse_money($value): float
{
    if ($value === null) {
        return 0.0;
    }

    if (is_numeric($value)) {
        return (float)$value;
    }

    $clean = preg_replace('/[^0-9,.\-]/', '', (string)$value);
    $clean = str_replace('.', '', $clean);
    $clean = str_replace(',', '.', $clean);

    return (float)round((float)$clean, 2);
}

function ingresso_parse_datetime(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $ts);
}

function ingresso_format_datetime(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    try {
        $tz = new DateTimeZone('America/Porto_Velho');
        $dt = new DateTime($value, $tz);
        $dt->setTimezone($tz);
        return $dt->format(DateTime::ATOM);
    } catch (Throwable $e) {
        return null;
    }
}

function ensure_ingresso_tables(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    // Em producao/staging, NAO executamos DDL em runtime. As tabelas devem
    // ser criadas via "php database/migrate.php". Marcamos como ensured
    // para evitar overhead a cada request.
    $env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
    if ($env !== 'local' && $env !== 'development') {
        $ensured = true;
        return;
    }

    $sqlBatches = "
        CREATE TABLE IF NOT EXISTS ingresso_batches (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(80) NOT NULL UNIQUE,
            nome VARCHAR(160) NOT NULL,
            status ENUM('ativo','em_breve','esgotado') NOT NULL DEFAULT 'ativo',
            start_at DATETIME NULL,
            end_at DATETIME NULL,
            highlight VARCHAR(190) NULL,
            visible TINYINT(1) NOT NULL DEFAULT 1,
            ordem INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ingresso_batches_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $sqlTickets = "
        CREATE TABLE IF NOT EXISTS ingresso_tickets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_id INT UNSIGNED NOT NULL,
            slug VARCHAR(80) NOT NULL UNIQUE,
            uid VARCHAR(32) NOT NULL UNIQUE,
            nome VARCHAR(160) NOT NULL,
            base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            fee DECIMAL(10,2) NOT NULL DEFAULT 0,
            available INT NOT NULL DEFAULT 0,
            status ENUM('ativo','em_breve','esgotado') NOT NULL DEFAULT 'ativo',
            progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
            start_at DATETIME NULL,
            end_at DATETIME NULL,
            highlight VARCHAR(190) NULL,
            show_highlight TINYINT(1) NOT NULL DEFAULT 0,
            ordem INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ingresso_tickets_batch (batch_id),
            KEY idx_ingresso_tickets_ordem (ordem),
            CONSTRAINT fk_ingresso_tickets_batch FOREIGN KEY (batch_id) REFERENCES ingresso_batches(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    try {
        $pdo->exec($sqlBatches);
        $pdo->exec($sqlTickets);
        // Garantir coluna visible em bases existentes
        if (!ingresso_column_exists($pdo, 'ingresso_batches', 'visible')) {
            try {
                $pdo->exec("ALTER TABLE ingresso_batches ADD COLUMN visible TINYINT(1) NOT NULL DEFAULT 1");
            } catch (Throwable $e) {
                // ignora se falhar
            }
        }
        // Garantir coluna show_highlight em bases existentes
        if (!ingresso_column_exists($pdo, 'ingresso_tickets', 'show_highlight')) {
            try {
                $pdo->exec("ALTER TABLE ingresso_tickets ADD COLUMN show_highlight TINYINT(1) NOT NULL DEFAULT 0");
            } catch (Throwable $e) {
                // ignora se falhar
            }
        }
        // Garantir coluna uid em bases existentes
        if (!ingresso_column_exists($pdo, 'ingresso_tickets', 'uid')) {
            try {
                $pdo->exec("ALTER TABLE ingresso_tickets ADD COLUMN uid VARCHAR(32) NOT NULL UNIQUE");
            } catch (Throwable $e) {
                // ignora se falhar
            }
        }

        // Preenche uid faltante
        try {
            $stmt = $pdo->query("SELECT id, uid FROM ingresso_tickets WHERE uid IS NULL OR uid = ''");
            $missing = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($missing) {
                $stmtUpdate = $pdo->prepare("UPDATE ingresso_tickets SET uid = ? WHERE id = ?");
                foreach ($missing as $row) {
                    $stmtUpdate->execute([generate_ingresso_uid(), (int)$row['id']]);
                }
            }
        } catch (Throwable $e) {
            // silencioso
        }
        $ensured = true;
    } catch (Throwable $e) {
        // tenta detectar se as tabelas existem mesmo em caso de erro de collate
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name IN ('ingresso_batches','ingresso_tickets')
            ");
            $stmt->execute();
            $exists = (int)$stmt->fetchColumn() >= 2;
            $ensured = $exists;
        } catch (Throwable $inner) {
            $ensured = false;
        }
    }
}

function seed_default_ingressos(PDO $pdo): void
{
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM ingresso_batches");
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }

    $defaults = [
        [
            'slug' => 'promo',
            'nome' => 'Lote Promocional',
            'status' => 'ativo',
            'start_at' => '2026-02-01 00:00:00',
            'end_at' => '2026-02-15 23:59:59',
            'highlight' => 'Ultimas unidades',
            'ordem' => 1,
            'tickets' => [
                ['slug' => 'promo-pista', 'nome' => 'Pista', 'base_price' => 110, 'fee' => 10, 'available' => 80, 'status' => 'ativo', 'progress' => 70, 'ordem' => 1],
                ['slug' => 'promo-vip', 'nome' => 'VIP Frontstage', 'base_price' => 280, 'fee' => 20, 'available' => 20, 'status' => 'ativo', 'progress' => 40, 'ordem' => 2],
            ],
        ],
        [
            'slug' => 'lote1',
            'nome' => '1o Lote',
            'status' => 'ativo',
            'start_at' => '2026-02-16 00:00:00',
            'end_at' => '2026-03-01 23:59:59',
            'highlight' => 'Melhor custo-beneficio',
            'ordem' => 2,
            'tickets' => [
                ['slug' => 'pista-l1', 'nome' => 'Pista', 'base_price' => 150, 'fee' => 12, 'available' => 240, 'status' => 'ativo', 'progress' => 45, 'ordem' => 1],
                ['slug' => 'vip-l1', 'nome' => 'VIP Frontstage', 'base_price' => 320, 'fee' => 20, 'available' => 35, 'status' => 'ativo', 'progress' => 30, 'ordem' => 2],
            ],
        ],
        [
            'slug' => 'especial',
            'nome' => 'Lote Especial',
            'status' => 'em_breve',
            'start_at' => '2026-03-05 00:00:00',
            'end_at' => '2026-03-15 23:59:59',
            'highlight' => 'Abertura em breve',
            'ordem' => 3,
            'tickets' => [
                ['slug' => 'pista-especial', 'nome' => 'Pista', 'base_price' => 0, 'fee' => 0, 'available' => 0, 'status' => 'em_breve', 'progress' => 0, 'ordem' => 1],
            ],
        ],
    ];

    try {
        $pdo->beginTransaction();

        $stmtBatch = $pdo->prepare("
            INSERT INTO ingresso_batches (slug, nome, status, start_at, end_at, highlight, ordem)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtTicket = $pdo->prepare("
            INSERT INTO ingresso_tickets (batch_id, slug, nome, base_price, fee, available, status, progress, start_at, end_at, highlight, ordem)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($defaults as $batch) {
            $stmtBatch->execute([
                ingresso_normalize_slug($batch['slug']),
                $batch['nome'],
                in_array($batch['status'], INGRESSO_STATUSES, true) ? $batch['status'] : 'ativo',
                ingresso_parse_datetime($batch['start_at']),
                ingresso_parse_datetime($batch['end_at']),
                $batch['highlight'],
                (int)($batch['ordem'] ?? 0),
            ]);

            $batchId = (int)$pdo->lastInsertId();

            foreach ($batch['tickets'] as $ticket) {
                $stmtTicket->execute([
                    $batchId,
                    ingresso_normalize_slug($ticket['slug']),
                    $ticket['nome'],
                    ingresso_parse_money($ticket['base_price']),
                    ingresso_parse_money($ticket['fee']),
                    (int)$ticket['available'],
                    in_array($ticket['status'], INGRESSO_STATUSES, true) ? $ticket['status'] : 'ativo',
                    max(0, min(100, (int)$ticket['progress'])),
                    ingresso_parse_datetime($ticket['start_at'] ?? null),
                    ingresso_parse_datetime($ticket['end_at'] ?? null),
                    $ticket['highlight'] ?? null,
                    (int)($ticket['ordem'] ?? 0),
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

function fetch_ingresso_batches(PDO $pdo, bool $onlyActive = false): array
{
    ensure_ingresso_tables($pdo);

    $where = '';
    $params = [];
    if ($onlyActive) {
        $where = "WHERE deleted_at IS NULL AND status IN ('ativo','em_breve','esgotado') AND visible = 1";
    } else {
        $where = "WHERE deleted_at IS NULL";
    }

    $stmt = $pdo->prepare("
        SELECT id, slug, nome, status, start_at, end_at, highlight, ordem, visible
        FROM ingresso_batches
        $where
        ORDER BY ordem ASC, id ASC
    ");
    $stmt->execute($params);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$batches) {
        return [];
    }

    $batchIds = array_column($batches, 'id');
    $placeholders = implode(',', array_fill(0, count($batchIds), '?'));

    $hasUid = ingresso_column_exists($pdo, 'ingresso_tickets', 'uid');
    $hasShowHighlight = ingresso_column_exists($pdo, 'ingresso_tickets', 'show_highlight');
    $fields = [
        'id', 'batch_id', 'slug', 'nome', 'base_price', 'fee', 'available', 'status', 'progress', 'start_at', 'end_at', 'highlight', 'ordem'
    ];
    if ($hasUid) {
        $fields[] = 'uid';
    }
    if ($hasShowHighlight) {
        $fields[] = 'show_highlight';
    }
    $fieldsSql = implode(', ', $fields);

    try {
        $stmtTickets = $pdo->prepare("
            SELECT $fieldsSql
            FROM ingresso_tickets
            WHERE batch_id IN ($placeholders) AND deleted_at IS NULL
            ORDER BY ordem ASC, id ASC
        ");
        $stmtTickets->execute($batchIds);
        $tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        // fallback sem colunas opcionais
        $stmtTickets = $pdo->prepare("
            SELECT id, batch_id, slug, nome, base_price, fee, available, status, progress, start_at, end_at, highlight, ordem
            FROM ingresso_tickets
            WHERE batch_id IN ($placeholders) AND deleted_at IS NULL
            ORDER BY ordem ASC, id ASC
        ");
        $stmtTickets->execute($batchIds);
        $tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $hasUid = false;
        $hasShowHighlight = false;
    }

    $ticketsByBatch = [];
    foreach ($tickets as $ticket) {
        $ticketsByBatch[(int)$ticket['batch_id']][] = $ticket;
    }

    $result = [];
    foreach ($batches as $batch) {
        $batchId = (int)$batch['id'];
        $batchStart = $batch['start_at'];
        $batchEnd = $batch['end_at'];
        $item = [
            'id' => $batch['slug'],
            'internal_id' => $batchId,
            'name' => $batch['nome'],
            'status' => ingresso_calculate_batch_status($batchStart, $batchEnd),
            'start_at' => ingresso_format_datetime($batchStart),
            'end_at' => ingresso_format_datetime($batchEnd),
            'highlight' => $batch['highlight'],
            'ordem' => (int)$batch['ordem'],
            'visible' => (int)($batch['visible'] ?? 1) === 1,
            'tickets' => [],
        ];

        $group = $ticketsByBatch[$batchId] ?? [];
        foreach ($group as $ticket) {
            $ticketStatus = ingresso_calculate_ticket_status(['start_at' => $batchStart, 'end_at' => $batchEnd], $ticket);
            $item['tickets'][] = [
                'id' => $hasUid ? ($ticket['uid'] ?? '') ?: $ticket['slug'] : $ticket['slug'],
                'name' => $ticket['nome'],
                'status' => $ticketStatus,
                'basePrice' => (float)$ticket['base_price'],
                'fee' => (float)$ticket['fee'],
                'available' => (int)$ticket['available'],
                'progress' => max(0, min(100, (int)$ticket['progress'])),
                'start_at' => ingresso_format_datetime($batchStart),
                'end_at' => ingresso_format_datetime($batchEnd),
                'highlight' => $ticket['highlight'],
                'show_highlight' => $hasShowHighlight ? (int)($ticket['show_highlight'] ?? 0) === 1 : false,
                'ordem' => (int)$ticket['ordem'],
            ];
        }

        $result[] = $item;
    }

    return $result;
}

function fetch_ingresso_tickets_flat(PDO $pdo): array
{
    ensure_ingresso_tables($pdo);

    $hasUid = ingresso_column_exists($pdo, 'ingresso_tickets', 'uid');
    $hasShowHighlight = ingresso_column_exists($pdo, 'ingresso_tickets', 'show_highlight');
    $fields = [
        't.id',
        't.slug',
        't.nome',
        't.base_price',
        't.fee',
        't.available',
        't.status',
        't.progress',
        't.start_at',
        't.end_at',
        't.highlight',
        't.ordem',
    ];
    if ($hasUid) {
        $fields[] = 't.uid';
    }
    if ($hasShowHighlight) {
        $fields[] = 't.show_highlight';
    }
    $fields[] = 'b.nome AS batch_nome';
    $fields[] = 'b.slug AS batch_slug';
    $fields[] = 'b.id AS batch_id';
    $fields[] = 'b.start_at AS batch_start_at';
    $fields[] = 'b.end_at AS batch_end_at';

    $sql = "
        SELECT " . implode(', ', $fields) . "
        FROM ingresso_tickets t
        INNER JOIN ingresso_batches b ON b.id = t.batch_id AND b.deleted_at IS NULL
        WHERE t.deleted_at IS NULL
        ORDER BY t.ordem ASC, t.id ASC
    ";

    try {
        $stmt = $pdo->query($sql);
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (PDOException $e) {
        // fallback sem colunas opcionais
        $stmt = $pdo->query("
            SELECT
                t.id,
                t.slug,
                t.nome,
                t.base_price,
                t.fee,
                t.available,
                t.status,
                t.progress,
                t.start_at,
                t.end_at,
                t.highlight,
                t.ordem,
                b.nome AS batch_nome,
                b.slug AS batch_slug,
                b.id AS batch_id
            FROM ingresso_tickets t
            INNER JOIN ingresso_batches b ON b.id = t.batch_id AND b.deleted_at IS NULL
            WHERE t.deleted_at IS NULL
            ORDER BY t.ordem ASC, t.id ASC
        ");
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $hasUid = false;
        $hasShowHighlight = false;
    }

    $hasUid = ingresso_column_exists($pdo, 'ingresso_tickets', 'uid');
    $hasShowHighlight = ingresso_column_exists($pdo, 'ingresso_tickets', 'show_highlight');

    return array_map(static function ($row) use ($hasUid, $hasShowHighlight) {
        $batchStart = $row['batch_start_at'] ?? null;
        $batchEnd = $row['batch_end_at'] ?? null;
        $ticketStatus = ingresso_calculate_ticket_status(
            ['start_at' => $batchStart, 'end_at' => $batchEnd],
            ['available' => $row['available'], 'progress' => $row['progress']]
        );

        return [
            'id' => (int)$row['id'],
            'slug' => $row['slug'],
            'uid' => $hasUid ? ($row['uid'] ?? '') : '',
            'name' => $row['nome'],
            'basePrice' => (float)$row['base_price'],
            'fee' => (float)$row['fee'],
            'available' => (int)$row['available'],
            'status' => $ticketStatus,
            'progress' => max(0, min(100, (int)$row['progress'])),
            'start_at' => ingresso_format_datetime($batchStart),
            'end_at' => ingresso_format_datetime($batchEnd),
            'highlight' => $row['highlight'],
            'show_highlight' => $hasShowHighlight ? (int)($row['show_highlight'] ?? 0) === 1 : false,
            'ordem' => (int)$row['ordem'],
            'batch_id' => (int)$row['batch_id'],
            'batch_slug' => $row['batch_slug'],
            'batch_name' => $row['batch_nome'],
        ];
    }, $rows);
}
