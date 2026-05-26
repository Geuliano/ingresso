<?php
// ============================================================================
// LINE-UP CONTROLLER — Gerenciamento de atrações do evento
// ============================================================================
// ✔ Proteção CSRF em todas as mutações
// ✔ Permissões por ação (módulo 'line-up')
// ✔ Soft delete via deleted_at
// ✔ Auto-migration idempotente
// ✔ JSON limpo sempre (ob_start + ob_clean)
// ============================================================================

ob_start();

$appPath = dirname(__DIR__, 2);
require_once $appPath . '/core/config.php';
require_once $appPath . '/core/auth.php';
require_once $appPath . '/core/permissions.php';

// ---------------------------------------------------------------------------
// Helper JSON
// ---------------------------------------------------------------------------
function jsonResp(bool $success, $messageOrData = null, array $extra = []): void
{
    if (ob_get_length()) ob_clean();
    $payload = ['success' => $success];
    if (is_array($messageOrData)) {
        $payload = array_merge($payload, $messageOrData);
    } elseif ($messageOrData !== null) {
        $payload['message'] = $messageOrData;
    }
    if (!empty($extra)) $payload = array_merge($payload, $extra);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------
if (function_exists('verificarLogin')) {
    verificarLogin();
} elseif (empty($_SESSION['usuario_id'])) {
    jsonResp(false, 'Acesso não autorizado.');
}

if (!isset($pdo)) jsonResp(false, 'Conexão com o banco indisponível.');

$operadorId = (int)($_SESSION['usuario_id'] ?? 0);
$modulo     = 'line-up';

// ---------------------------------------------------------------------------
// Auto-migration
// ---------------------------------------------------------------------------
(function(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;
    // Garante coluna exibir_intro na tabela de configuração do evento
    try { $pdo->exec("ALTER TABLE info_evento ADD COLUMN exibir_intro TINYINT(1) NOT NULL DEFAULT 1 AFTER exibir_card_info"); } catch (Throwable $e) {}

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lineup_atracoes (
            id          INT UNSIGNED      NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nome        VARCHAR(255)      NOT NULL,
            nivel       TINYINT UNSIGNED  NOT NULL DEFAULT 3,
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
})($pdo);

// ---------------------------------------------------------------------------
// Rota
// ---------------------------------------------------------------------------
$action = $_GET['action'] ?? $_POST['action'] ?? 'listar';

// ── GET: listar ──────────────────────────────────────────────────────────────
if ($action === 'listar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!userHasPermission($operadorId, $modulo, 'pode_visualizar')) {
        jsonResp(false, 'Sem permissão para visualizar.');
    }

    $stmt = $pdo->prepare("
        SELECT id, nome, nivel, descricao, ordem, ativo, created_at
        FROM lineup_atracoes
        WHERE deleted_at IS NULL
        ORDER BY nivel ASC, ordem ASC, nome ASC
    ");
    $stmt->execute();
    $atracoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // cast de tipos
    foreach ($atracoes as &$a) {
        $a['id']    = (int)$a['id'];
        $a['nivel'] = (int)$a['nivel'];
        $a['ordem'] = (int)$a['ordem'];
        $a['ativo'] = (int)$a['ativo'];
    }
    unset($a);

    jsonResp(true, ['atracoes' => $atracoes]);
}

// ── POST: salvar (criar ou editar) ───────────────────────────────────────────
if ($action === 'salvar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $csrfEnviado = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfEnviado)) {
        jsonResp(false, 'Token de segurança inválido. Recarregue a página.');
    }

    $id    = (int)($_POST['id'] ?? 0);
    $isNew = $id === 0;

    if ($isNew && !userHasPermission($operadorId, $modulo, 'pode_criar')) {
        jsonResp(false, 'Sem permissão para criar.');
    }
    if (!$isNew && !userHasPermission($operadorId, $modulo, 'pode_editar')) {
        jsonResp(false, 'Sem permissão para editar.');
    }

    // Validação
    $nome  = trim($_POST['nome'] ?? '');
    $nivel = (int)($_POST['nivel'] ?? 3);
    $desc  = trim($_POST['descricao'] ?? '') ?: null;
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if ($nome === '') jsonResp(false, 'O nome da atração é obrigatório.');
    if (mb_strlen($nome) > 255) jsonResp(false, 'O nome deve ter no máximo 255 caracteres.');
    if ($nivel < 1 || $nivel > 4) $nivel = 3;

    if ($isNew) {
        // ordem = próximo disponível nesse nível
        $maxOrdem = (int)$pdo->prepare("
            SELECT COALESCE(MAX(ordem), 0) FROM lineup_atracoes WHERE nivel = ? AND deleted_at IS NULL
        ")->execute([$nivel]) ? $pdo->query("SELECT COALESCE(MAX(ordem),0) FROM lineup_atracoes WHERE nivel = {$nivel} AND deleted_at IS NULL")->fetchColumn() : 0;

        $stmt = $pdo->prepare("
            INSERT INTO lineup_atracoes (nome, nivel, descricao, ordem, ativo, criado_por)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nome, $nivel, $desc, (int)$maxOrdem + 1, $ativo, $operadorId]);
        $novoId = (int)$pdo->lastInsertId();
        jsonResp(true, ['message' => 'Atração cadastrada com sucesso!', 'id' => $novoId]);
    } else {
        // Verifica se existe
        $chk = $pdo->prepare("SELECT id FROM lineup_atracoes WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $chk->execute([$id]);
        if (!$chk->fetch()) jsonResp(false, 'Atração não encontrada.');

        $stmt = $pdo->prepare("
            UPDATE lineup_atracoes
            SET nome = ?, nivel = ?, descricao = ?, ativo = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$nome, $nivel, $desc, $ativo, $id]);
        jsonResp(true, 'Atração atualizada com sucesso!');
    }
}

// ── POST: excluir (soft delete) ──────────────────────────────────────────────
if ($action === 'excluir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfEnviado = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfEnviado)) {
        jsonResp(false, 'Token de segurança inválido.');
    }

    if (!userHasPermission($operadorId, $modulo, 'pode_excluir')) {
        jsonResp(false, 'Sem permissão para excluir.');
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResp(false, 'ID inválido.');

    $stmt = $pdo->prepare("
        UPDATE lineup_atracoes SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) jsonResp(false, 'Atração não encontrada ou já removida.');
    jsonResp(true, 'Atração removida com sucesso!');
}

// ── POST: reordenar ──────────────────────────────────────────────────────────
if ($action === 'reordenar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfEnviado = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfEnviado)) {
        jsonResp(false, 'Token de segurança inválido.');
    }
    if (!userHasPermission($operadorId, $modulo, 'pode_editar')) {
        jsonResp(false, 'Sem permissão para reordenar.');
    }

    $ids = json_decode($_POST['ids'] ?? '[]', true);
    if (!is_array($ids)) jsonResp(false, 'Dados inválidos.');

    $upd = $pdo->prepare("UPDATE lineup_atracoes SET ordem = ? WHERE id = ? AND deleted_at IS NULL");
    foreach ($ids as $pos => $aId) {
        $upd->execute([(int)$pos + 1, (int)$aId]);
    }
    jsonResp(true, 'Ordem atualizada!');
}

// ── GET: get_intro ────────────────────────────────────────────────────────────
if ($action === 'get_intro' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!userHasPermission($operadorId, $modulo, 'pode_visualizar')) {
        jsonResp(false, 'Sem permissão.');
    }
    $row = $pdo->query("SELECT exibir_intro FROM info_evento WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    jsonResp(true, ['exibir_intro' => $row ? (int)$row['exibir_intro'] : 1]);
}

// ── POST: toggle_intro ──────────────────────────────────────────────────────
if ($action === 'toggle_intro' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfEnviado = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfEnviado)) {
        jsonResp(false, 'Token de segurança inválido.');
    }
    if (!userHasPermission($operadorId, $modulo, 'pode_editar')) {
        jsonResp(false, 'Sem permissão para alterar.');
    }

    $row = $pdo->query("SELECT exibir_intro FROM info_evento WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$row) jsonResp(false, 'Configuração não encontrada.');

    $novoValor = ((int)$row['exibir_intro'] === 1) ? 0 : 1;
    $pdo->prepare("UPDATE info_evento SET exibir_intro = ?, updated_at = NOW() WHERE id = 1")->execute([$novoValor]);

    jsonResp(true, ['message' => $novoValor ? 'Line-up visível no site.' : 'Line-up ocultado do site.', 'exibir_intro' => $novoValor]);
}

jsonResp(false, 'Ação não reconhecida.');
