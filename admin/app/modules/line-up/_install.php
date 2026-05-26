<?php
/**
 * =============================================================================
 * INSTALADOR — Módulo line-up
 * =============================================================================
 * Execute UMA única vez para registrar o módulo e criar a tabela.
 * Após executar, REMOVA ou proteja este arquivo.
 * =============================================================================
 */

$appPath = dirname(__DIR__, 2);
require_once $appPath . '/core/config.php';

$resultados = [];

try {
    // ── 1. Cria a tabela lineup_atracoes ─────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lineup_atracoes (
            id          INT UNSIGNED      NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nome        VARCHAR(255)      NOT NULL,
            nivel       TINYINT UNSIGNED  NOT NULL DEFAULT 3
                            COMMENT '1=Headliner | 2=Sub-headliner | 3=Suporte | 4=Abertura | 5=Extra',
            descricao   TEXT              NULL,
            ordem       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            ativo       TINYINT(1)        NOT NULL DEFAULT 1,
            criado_por  INT UNSIGNED      NULL,
            created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at  DATETIME          NULL,
            INDEX idx_nivel   (nivel),
            INDEX idx_ativo   (ativo),
            INDEX idx_deleted (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $resultados[] = ['ok' => true, 'msg' => 'Tabela lineup_atracoes OK.'];

    // ── 2. Verifica se o módulo já existe ────────────────────────────────────
    $stmt = $pdo->prepare("SELECT id FROM modulos WHERE slug = ? LIMIT 1");
    $stmt->execute(['line-up']);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        $resultados[] = ['ok' => true, 'msg' => 'Módulo já cadastrado (id: ' . $existente['id'] . ').'];
    } else {
        // Maior ordem atual
        $maxOrdem = (int)$pdo->query("SELECT COALESCE(MAX(ordem),0) FROM modulos WHERE id_pai IS NULL")->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO modulos (nome, slug, descricao, icone, ordem, id_pai, ativo, ver_menu)
            VALUES (?, ?, ?, ?, ?, NULL, 1, 1)
        ");
        $stmt->execute([
            'Line-up',
            'line-up',
            'Gerenciamento das atrações e artistas do evento',
            'las la-music',
            $maxOrdem + 1,
        ]);

        $moduloId = (int)$pdo->lastInsertId();
        $resultados[] = ['ok' => true, 'msg' => "Módulo inserido com id: {$moduloId}"];

        // ── 3. Permissões para todos os níveis de acesso ──────────────────────
        $niveis = $pdo->query("SELECT id FROM niveis_acesso")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($niveis as $nivelId) {
            $chk = $pdo->prepare("SELECT id FROM permissoes WHERE id_nivel=? AND id_modulo=? LIMIT 1");
            $chk->execute([$nivelId, $moduloId]);
            if (!$chk->fetch()) {
                $ins = $pdo->prepare("
                    INSERT INTO permissoes (id_nivel, id_modulo, pode_visualizar, pode_criar, pode_editar, pode_excluir)
                    VALUES (?, ?, 1, 1, 1, 1)
                ");
                $ins->execute([$nivelId, $moduloId]);
                $resultados[] = ['ok' => true, 'msg' => "Permissão criada para nível id={$nivelId}"];
            }
        }
    }

} catch (Throwable $e) {
    $resultados[] = ['ok' => false, 'msg' => 'ERRO: ' . $e->getMessage()];
}

header('Content-Type: text/html; charset=utf-8');
echo '<style>body{font-family:monospace;padding:20px}.ok{color:green}.er{color:red}pre{background:#f8f8f8;padding:12px;border-radius:6px}</style>';
echo '<h2>Instalação — Módulo <em>line-up</em></h2><pre>';
foreach ($resultados as $r) {
    $cls = $r['ok'] ? 'ok' : 'er';
    $ico = $r['ok'] ? '✔' : '✖';
    echo "<span class=\"{$cls}\">{$ico} {$r['msg']}</span>\n";
}
echo '</pre><p><strong>Pronto! Remova este arquivo após a instalação.</strong></p>';
