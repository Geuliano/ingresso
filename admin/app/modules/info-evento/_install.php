<?php
/**
 * Script de instalação do módulo info-evento
 * Execute uma única vez via navegador ou CLI para registrar o módulo no banco.
 * Após executar, REMOVA ou proteja este arquivo.
 */

$appPath = dirname(__DIR__, 2);
require_once $appPath . '/core/config.php';

$resultados = [];

try {
    // 1. Verifica se o módulo já existe
    $stmt = $pdo->prepare("SELECT id FROM modulos WHERE slug = ? LIMIT 1");
    $stmt->execute(['info-evento']);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        $resultados[] = ['ok' => true, 'msg' => 'Módulo já cadastrado (id: ' . $existente['id'] . ').'];
    } else {
        // Obtém a maior ordem atual
        $maxOrdem = (int)$pdo->query("SELECT COALESCE(MAX(ordem),0) FROM modulos WHERE id_pai IS NULL")->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO modulos (nome, slug, descricao, icone, ordem, id_pai, ativo, ver_menu)
            VALUES (?, ?, ?, ?, ?, NULL, 1, 1)
        ");
        $stmt->execute([
            'Info do Evento',
            'info-evento',
            'Informações gerais do evento e dados do produtor',
            'las la-calendar-check',
            $maxOrdem + 1,
        ]);

        $moduloId = (int)$pdo->lastInsertId();
        $resultados[] = ['ok' => true, 'msg' => "Módulo inserido com id: {$moduloId}"];

        // 2. Concede permissão total a TODOS os níveis de acesso existentes
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

    // 3. Cria a tabela info_evento
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS info_evento (
            id                    INT UNSIGNED      NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nome_evento           VARCHAR(255)      NOT NULL DEFAULT '',
            data_inicio           DATETIME          NULL,
            data_fim              DATETIME          NULL,
            local_nome            VARCHAR(255)      NOT NULL DEFAULT '',
            local_cidade          VARCHAR(120)      NOT NULL DEFAULT '',
            local_estado          CHAR(2)           NOT NULL DEFAULT '',
            produtor_nome         VARCHAR(255)      NOT NULL DEFAULT '',
            produtor_cpf_cnpj     VARCHAR(20)       NOT NULL DEFAULT '',
            produtor_endereco     TEXT              NULL,
            produtor_email        VARCHAR(255)      NOT NULL DEFAULT '',
            produtor_telefone1    VARCHAR(30)       NOT NULL DEFAULT '',
            produtor_telefone2    VARCHAR(30)       NOT NULL DEFAULT '',
            atualizado_por        INT UNSIGNED      NULL,
            created_at            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_atualizado_por (atualizado_por)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $resultados[] = ['ok' => true, 'msg' => 'Tabela info_evento OK.'];

    // Garante registro base
    $pdo->exec("INSERT IGNORE INTO info_evento (id) VALUES (1)");
    $resultados[] = ['ok' => true, 'msg' => 'Registro base (id=1) garantido.'];

} catch (Throwable $e) {
    $resultados[] = ['ok' => false, 'msg' => 'ERRO: ' . $e->getMessage()];
}

header('Content-Type: text/html; charset=utf-8');
echo '<style>body{font-family:monospace;padding:20px}
.ok{color:green}.er{color:red}
pre{background:#f8f8f8;padding:12px;border-radius:6px}</style>';
echo '<h2>Instalação — Módulo <em>info-evento</em></h2><pre>';
foreach ($resultados as $r) {
    $cls = $r['ok'] ? 'ok' : 'er';
    $ico = $r['ok'] ? '✔' : '✖';
    echo "<span class=\"{$cls}\">{$ico} {$r['msg']}</span>\n";
}
echo '</pre><p><strong>Pronto! Remova este arquivo após a instalação.</strong></p>';
