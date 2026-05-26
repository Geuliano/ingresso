<?php

declare(strict_types=1);

/**
 * Migrator minimal idempotente.
 *
 * - Le arquivos .sql em database/migrations em ordem alfabetica.
 * - Mantem controle em uma tabela schema_migrations.
 * - Tolera CREATE INDEX duplicado (codigo 1061 do MySQL).
 *
 * Uso:
 *   php database/migrate.php
 *   php database/migrate.php --status      (apenas mostra o que aplicaria)
 */

require_once __DIR__ . '/../app/bootstrap.php';

$status = in_array('--status', $argv ?? [], true);

$pdo = db();
$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        name VARCHAR(255) PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$dir = __DIR__ . '/migrations';
if (!is_dir($dir)) {
    fwrite(STDERR, "Diretorio nao encontrado: {$dir}\n");
    exit(1);
}

$files = glob($dir . '/*.sql') ?: [];
sort($files);

if (!$files) {
    echo "Nenhuma migration encontrada.\n";
    exit(0);
}

$applied = [];
foreach ($pdo->query('SELECT name FROM schema_migrations') as $row) {
    $applied[$row['name']] = true;
}

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        echo "[ok ] $name (ja aplicada)\n";
        continue;
    }

    if ($status) {
        echo "[ -- ] $name (pendente)\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Falha ao ler $file\n");
        exit(1);
    }

    // Divide por ; respeitando comentarios e linhas vazias.
    $statements = array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql) ?: []), static fn($s) => $s !== '');

    try {
        $pdo->beginTransaction();
        foreach ($statements as $stmt) {
            if ($stmt === '') continue;
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                // Indice duplicado (1061) ou coluna duplicada (1060) sao tolerados
                // para que migrations sejam idempotentes em bases pre-existentes.
                $code = $e->errorInfo[1] ?? null;
                if (in_array($code, [1061, 1060, 1050], true)) {
                    continue;
                }
                throw $e;
            }
        }
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (:n)');
        $stmt->execute(['n' => $name]);
        $pdo->commit();
        $count++;
        echo "[+++] $name aplicada\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "Erro em $name: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Done. Migrations aplicadas: {$count}.\n";
