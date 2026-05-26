<?php
/**
 * ============================================================
 * INGRESSOS CONTROLLER
 * ============================================================
 * Gerencia lotes (ingresso_batches) e ingressos (ingresso_tickets).
 *
 * Ações:
 *   GET  → get_lote, get_ingresso
 *   POST → save_lote, delete_lote, toggle_visivel_lote
 *          save_ingresso, delete_ingresso
 * ============================================================
 */

ob_start();

$appPath = dirname(__DIR__, 2);
require_once $appPath . '/core/config.php';
require_once $appPath . '/core/auth.php';
require_once $appPath . '/core/permissions.php';

// Inclui helpers de ingresso do projeto raiz (slug, money, datetime, uid)
$_rootHelper = dirname(ROOT_PATH) . '/app/ingressos.php';
if (file_exists($_rootHelper)) {
    require_once $_rootHelper;
}
unset($_rootHelper);

// ── Verifica sessão ──────────────────────────────────────────────────────────
if (!isLoggedIn()) {
    jsonResponse(false, 'Sessão expirada.');
}

$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);

// ── jsonResponse ─────────────────────────────────────────────────────────────
function jsonResponse(bool $success, $messageOrData = null, array $extra = []): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    $payload = ['success' => $success];

    if (is_array($messageOrData)) {
        $payload = array_merge($payload, $messageOrData);
    } elseif ($messageOrData !== null) {
        $payload['message'] = $messageOrData;
    }

    if ($extra) {
        $payload = array_merge($payload, $extra);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Conexão ──────────────────────────────────────────────────────────────────
if (!isset($pdo)) {
    jsonResponse(false, 'Conexão com o banco indisponível.');
}

// ── CSRF helper ──────────────────────────────────────────────────────────────
if (!function_exists('safeHashEquals')) {
    function safeHashEquals(string $known, string $user): bool
    {
        if (function_exists('hash_equals')) {
            return hash_equals($known, $user);
        }
        if (strlen($known) !== strlen($user)) {
            return false;
        }
        $res = 0;
        for ($i = 0, $len = strlen($known); $i < $len; $i++) {
            $res |= ord($known[$i]) ^ ord($user[$i]);
        }
        return $res === 0;
    }
}

// ── Permissão helper ─────────────────────────────────────────────────────────
function ensurePermission(string $tipo): void
{
    global $usuarioId;
    if (!userHasPermission($usuarioId, 'ingressos', $tipo)) {
        jsonResponse(false, 'Você não tem permissão para executar esta ação.');
    }
}

// ── Helpers de normalização ──────────────────────────────────────────────────
function _normSlug(string $v): string
{
    if (function_exists('ingresso_normalize_slug')) {
        return ingresso_normalize_slug($v);
    }
    $v = strtolower(trim($v));
    if (function_exists('iconv')) {
        $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
    }
    $v = preg_replace('/[^a-z0-9_\-]+/', '-', (string)$v);
    return trim($v, '-');
}

function _parseMoney($v): float
{
    if (function_exists('ingresso_parse_money')) {
        return ingresso_parse_money($v);
    }
    if (is_numeric($v)) return round((float)$v, 2);
    $c = preg_replace('/[^0-9,.\-]/', '', (string)$v);
    $c = str_replace('.', '', $c);
    $c = str_replace(',', '.', $c);
    return round((float)$c, 2);
}

function _parseDt(?string $v): ?string
{
    if (function_exists('ingresso_parse_datetime')) {
        return ingresso_parse_datetime($v);
    }
    if (!$v || trim($v) === '') return null;
    $ts = strtotime($v);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function _genUid(): string
{
    if (function_exists('generate_ingresso_uid')) {
        return generate_ingresso_uid();
    }
    return bin2hex(random_bytes(8));
}

/**
 * Calcula o status do ingresso automaticamente:
 * 1. Datas têm prioridade (em_breve / esgotado por data)
 * 2. Dentro do período → ativo se available > 0, esgotado caso contrário
 */
function _computeIngressoStatus(?string $start_at, ?string $end_at, int $available): string
{
    $now = time();
    if ($start_at) {
        $ts = strtotime($start_at);
        if ($ts && $now < $ts) return 'em_breve';
    }
    if ($end_at) {
        $ts = strtotime($end_at);
        if ($ts && $now > $ts) return 'esgotado';
    }
    return $available > 0 ? 'ativo' : 'esgotado';
}

function _genRandSlug(PDO $pdo, string $table): string
{
    for ($i = 0; $i < 10; $i++) {
        $candidate = bin2hex(random_bytes(6)); // 12 chars hex
        $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE slug = ? LIMIT 1");
        $stmt->execute([$candidate]);
        if (!$stmt->fetch()) {
            return $candidate;
        }
    }
    return bin2hex(random_bytes(12)); // fallback improvável
}

// ── Auto-migration: garante colunas deleted_at (soft delete) ─────────────────
if (empty($_SESSION['_ing_softdelete_migrated'])) {
    try {
        $pdo->exec("ALTER TABLE ingresso_batches ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE ingresso_tickets  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL");
        $_SESSION['_ing_softdelete_migrated'] = true;
    } catch (Throwable $_migErr) {
        try {
            $stmtChk = $pdo->prepare("
                SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'deleted_at'
            ");
            $stmtChk->execute(['ingresso_batches']);
            if (!(int)$stmtChk->fetchColumn()) {
                $pdo->exec("ALTER TABLE ingresso_batches ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
            }
            $stmtChk->execute(['ingresso_tickets']);
            if (!(int)$stmtChk->fetchColumn()) {
                $pdo->exec("ALTER TABLE ingresso_tickets ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
            }
            $_SESSION['_ing_softdelete_migrated'] = true;
        } catch (Throwable $_) { /* silencioso */ }
    }
}

// ── Detecta ação ─────────────────────────────────────────────────────────────
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

$allowedActions = [
    'get_lote', 'get_ingresso',
    'save_lote', 'delete_lote', 'restore_lote', 'toggle_visivel_lote', 'update_ordem_lotes',
    'save_ingresso', 'delete_ingresso', 'restore_ingresso', 'update_ordem_ingressos',
];

if (!in_array($action, $allowedActions, true)) {
    jsonResponse(false, 'Ação inválida.');
}

// ── Valida CSRF nas ações que alteram estado ─────────────────────────────────
$requiresCSRF = ['save_lote', 'delete_lote', 'restore_lote', 'toggle_visivel_lote', 'update_ordem_lotes', 'save_ingresso', 'delete_ingresso', 'restore_ingresso', 'update_ordem_ingressos'];
if (in_array($action, $requiresCSRF, true)) {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !safeHashEquals($_SESSION['csrf_token'], $token)) {
        jsonResponse(false, 'Falha de segurança. Recarregue a página.');
    }
}

// ════════════════════════════════════════════════════════════════════════════
// GET_LOTE — retorna dados de um lote para preencher o modal de edição
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'get_lote') {
    ensurePermission('pode_visualizar');

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        jsonResponse(false, 'ID inválido.');
    }

    $stmt = $pdo->prepare("SELECT * FROM ingresso_batches WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $lote = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lote) {
        jsonResponse(false, 'Lote não encontrado.');
    }

    $lote['visible'] = (int)$lote['visible'];
    $lote['ordem']   = (int)$lote['ordem'];

    jsonResponse(true, ['lote' => $lote]);
}

// ════════════════════════════════════════════════════════════════════════════
// SAVE_LOTE — cria ou atualiza um lote (detecta pelo campo lote_id)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'save_lote') {
    $id       = filter_input(INPUT_POST, 'lote_id', FILTER_VALIDATE_INT);
    $isUpdate = (bool)$id;

    ensurePermission($isUpdate ? 'pode_editar' : 'pode_criar');

    $nome     = trim($_POST['nome'] ?? '');
    $status   = trim($_POST['status'] ?? 'ativo');
    $start_at = _parseDt($_POST['start_at'] ?? null);
    $end_at   = _parseDt($_POST['end_at'] ?? null);
    $visible  = isset($_POST['visible']) ? (int)(bool)$_POST['visible'] : 1;

    if ($nome === '') {
        jsonResponse(false, 'Informe o nome do lote.');
    }
    if (!in_array($status, ['ativo', 'em_breve', 'esgotado'], true)) {
        $status = 'ativo';
    }

    if ($isUpdate) {
        // Preserva slug e ordem (ordem gerenciada pelo drag-and-drop)
        $stmtExisting = $pdo->prepare("SELECT slug, ordem FROM ingresso_batches WHERE id = ? LIMIT 1");
        $stmtExisting->execute([$id]);
        $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            jsonResponse(false, 'Lote não encontrado.');
        }
        $slug  = $existing['slug'] ?: _genRandSlug($pdo, 'ingresso_batches');
        $ordem = (int)$existing['ordem'];

        try {
            $stmt = $pdo->prepare("
                UPDATE ingresso_batches
                SET nome = ?, slug = ?, status = ?, start_at = ?, end_at = ?,
                    visible = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$nome, $slug, $status, $start_at, $end_at, $visible, $id]);
        } catch (Throwable $e) {
            jsonResponse(false, 'Não foi possível atualizar o lote.');
        }
    } else {
        $slug = _genRandSlug($pdo, 'ingresso_batches');

        // Ordem = próximo ao final
        $stmtOrdem = $pdo->prepare("SELECT COALESCE(MAX(ordem), -1) + 1 FROM ingresso_batches");
        $stmtOrdem->execute();
        $ordem = (int)$stmtOrdem->fetchColumn();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO ingresso_batches (slug, nome, status, start_at, end_at, visible, ordem)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$slug, $nome, $status, $start_at, $end_at, $visible, $ordem]);
            $id = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            jsonResponse(false, 'Não foi possível criar o lote.');
        }
    }

    jsonResponse(true, $isUpdate ? 'Lote atualizado com sucesso.' : 'Lote criado com sucesso.', ['id' => $id]);
}

// ════════════════════════════════════════════════════════════════════════════
// DELETE_LOTE — soft delete: marca lote e seus ingressos como excluídos
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'delete_lote') {
    ensurePermission('pode_excluir');

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        jsonResponse(false, 'ID inválido.');
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM ingresso_batches WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmtCheck->execute([$id]);
    if (!$stmtCheck->fetchColumn()) {
        jsonResponse(false, 'Lote não encontrado.');
    }

    try {
        $pdo->beginTransaction();
        // Cascata: marca ingressos ainda ativos deste lote como excluídos
        $pdo->prepare("UPDATE ingresso_tickets SET deleted_at = NOW() WHERE batch_id = ? AND deleted_at IS NULL")
            ->execute([$id]);
        $pdo->prepare("UPDATE ingresso_batches SET deleted_at = NOW() WHERE id = ?")
            ->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'Não foi possível excluir o lote.');
    }

    jsonResponse(true, 'Lote excluído.');
}

// ════════════════════════════════════════════════════════════════════════════
// RESTORE_LOTE — restaura lote e todos os seus ingressos
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'restore_lote') {
    ensurePermission('pode_editar');

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        jsonResponse(false, 'ID inválido.');
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM ingresso_batches WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1");
    $stmtCheck->execute([$id]);
    if (!$stmtCheck->fetchColumn()) {
        jsonResponse(false, 'Lote não encontrado ou já está ativo.');
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE ingresso_tickets SET deleted_at = NULL WHERE batch_id = ?")
            ->execute([$id]);
        $pdo->prepare("UPDATE ingresso_batches SET deleted_at = NULL WHERE id = ?")
            ->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'Não foi possível restaurar o lote.');
    }

    jsonResponse(true, 'Lote restaurado com sucesso.');
}

// ════════════════════════════════════════════════════════════════════════════
// TOGGLE_VISIVEL_LOTE — alterna visibilidade no site
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'toggle_visivel_lote') {
    ensurePermission('pode_editar');

    $id      = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $visible = (int)((int)($_POST['visible'] ?? 0) !== 0);

    if (!$id) {
        jsonResponse(false, 'ID inválido.');
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM ingresso_batches WHERE id = ? LIMIT 1");
    $stmtCheck->execute([$id]);
    if (!$stmtCheck->fetchColumn()) {
        jsonResponse(false, 'Lote não encontrado.');
    }

    try {
        $pdo->prepare("UPDATE ingresso_batches SET visible = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$visible, $id]);
    } catch (Throwable $e) {
        jsonResponse(false, 'Não foi possível atualizar a visibilidade.');
    }

    $msg = $visible ? 'Lote exibido no site.' : 'Lote ocultado do site.';
    jsonResponse(true, $msg);
}

// ════════════════════════════════════════════════════════════════════════════
// GET_INGRESSO — retorna dados de um ingresso para o modal de edição
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'get_ingresso') {
    ensurePermission('pode_visualizar');

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        jsonResponse(false, 'ID inválido.');
    }

    $stmt = $pdo->prepare("
        SELECT t.*, b.nome AS lote_nome
        FROM ingresso_tickets t
        INNER JOIN ingresso_batches b ON b.id = t.batch_id
        WHERE t.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $ingresso = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ingresso) {
        jsonResponse(false, 'Ingresso não encontrado.');
    }

    $ingresso['base_price']     = (float)$ingresso['base_price'];
    $ingresso['fee']            = (float)$ingresso['fee'];
    $ingresso['available']      = (int)$ingresso['available'];
    $ingresso['progress']       = (int)$ingresso['progress'];
    $ingresso['show_highlight'] = (int)$ingresso['show_highlight'];
    $ingresso['ordem']          = (int)$ingresso['ordem'];
    $ingresso['batch_id']       = (int)$ingresso['batch_id'];

    jsonResponse(true, ['ingresso' => $ingresso]);
}

// ════════════════════════════════════════════════════════════════════════════
// SAVE_INGRESSO — cria ou atualiza um ingresso (detecta pelo campo ingresso_id)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'save_ingresso') {
    $id       = filter_input(INPUT_POST, 'ingresso_id', FILTER_VALIDATE_INT);
    $isUpdate = (bool)$id;

    ensurePermission($isUpdate ? 'pode_editar' : 'pode_criar');

    $batchId       = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
    $nome          = trim($_POST['nome'] ?? '');
    $base_price    = _parseMoney($_POST['base_price'] ?? 0);
    $fee           = _parseMoney($_POST['fee'] ?? 0);
    $start_at      = _parseDt($_POST['start_at'] ?? null);
    $end_at        = _parseDt($_POST['end_at'] ?? null);
    $highlight     = trim($_POST['highlight'] ?? '') ?: null;
    $showHighlight = (int)(($_POST['show_highlight'] ?? '0') === '1');

    if (!$batchId) {
        jsonResponse(false, 'Selecione um lote válido.');
    }
    if ($nome === '') {
        jsonResponse(false, 'Informe o nome do ingresso.');
    }

    // Verifica lote e busca datas do lote como fallback para o status
    $stmtBatch = $pdo->prepare("SELECT id, start_at, end_at FROM ingresso_batches WHERE id = ? LIMIT 1");
    $stmtBatch->execute([$batchId]);
    $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);
    if (!$batch) {
        jsonResponse(false, 'Lote não encontrado.');
    }

    // Datas efetivas para cálculo de status (ticket tem prioridade, cai no lote se vazio)
    $effectiveStart = $start_at ?: $batch['start_at'];
    $effectiveEnd   = $end_at   ?: $batch['end_at'];

    if ($isUpdate) {
        // Busca dados imutáveis do registro atual
        $stmtExisting = $pdo->prepare("SELECT slug, available, progress, ordem FROM ingresso_tickets WHERE id = ? LIMIT 1");
        $stmtExisting->execute([$id]);
        $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            jsonResponse(false, 'Ingresso não encontrado.');
        }

        $slug      = $existing['slug'] ?: _genRandSlug($pdo, 'ingresso_tickets');
        $available = (int)$existing['available']; // imutável via form
        $progress  = (int)$existing['progress'];  // gerenciado pelas vendas
        $ordem     = (int)$existing['ordem'];     // gerenciado pelo drag-and-drop

        $status = _computeIngressoStatus($effectiveStart, $effectiveEnd, $available);

        try {
            $stmt = $pdo->prepare("
                UPDATE ingresso_tickets
                SET batch_id = ?, slug = ?, nome = ?, base_price = ?, fee = ?,
                    status = ?, start_at = ?, end_at = ?,
                    highlight = ?, show_highlight = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $batchId, $slug, $nome, $base_price, $fee,
                $status, $start_at, $end_at,
                $highlight, $showHighlight,
                $id
            ]);
        } catch (Throwable $e) {
            jsonResponse(false, 'Não foi possível atualizar o ingresso.');
        }
    } else {
        $available = max(0, (int)($_POST['available'] ?? 0));
        $status    = _computeIngressoStatus($effectiveStart, $effectiveEnd, $available);
        $slug      = _genRandSlug($pdo, 'ingresso_tickets');

        // Ordem = próximo ao final dentro do lote
        $stmtOrdem = $pdo->prepare("SELECT COALESCE(MAX(ordem), -1) + 1 FROM ingresso_tickets WHERE batch_id = ?");
        $stmtOrdem->execute([$batchId]);
        $ordem = (int)$stmtOrdem->fetchColumn();

        // Compatibilidade: verifica se a coluna uid existe
        $hasUidCol = false;
        try {
            $pdo->query("SELECT uid FROM ingresso_tickets LIMIT 0");
            $hasUidCol = true;
        } catch (Throwable $_) {}

        try {
            if ($hasUidCol) {
                $uid  = _genUid();
                $stmt = $pdo->prepare("
                    INSERT INTO ingresso_tickets
                        (batch_id, slug, uid, nome, base_price, fee, available, status,
                         progress, start_at, end_at, highlight, show_highlight, ordem)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $batchId, $slug, $uid, $nome, $base_price, $fee, $available,
                    $status, $start_at, $end_at, $highlight, $showHighlight, $ordem
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ingresso_tickets
                        (batch_id, slug, nome, base_price, fee, available, status,
                         progress, start_at, end_at, highlight, show_highlight, ordem)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $batchId, $slug, $nome, $base_price, $fee, $available,
                    $status, $start_at, $end_at, $highlight, $showHighlight, $ordem
                ]);
            }
            $id = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            jsonResponse(false, 'Não foi possível criar o ingresso.');
        }
    }

    jsonResponse(true, $isUpdate ? 'Ingresso atualizado com sucesso.' : 'Ingresso criado com sucesso.', ['id' => $id]);
}

// ════════════════════════════════════════════════════════════════════════════
// DELETE_INGRESSO — soft delete: marca o ingresso como excluído
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'delete_ingresso') {
    ensurePermission('pode_excluir');

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        jsonResponse(false, 'ID inválido.');
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM ingresso_tickets WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmtCheck->execute([$id]);
    if (!$stmtCheck->fetchColumn()) {
        jsonResponse(false, 'Ingresso não encontrado.');
    }

    try {
        $pdo->prepare("UPDATE ingresso_tickets SET deleted_at = NOW() WHERE id = ?")
            ->execute([$id]);
    } catch (Throwable $e) {
        jsonResponse(false, 'Não foi possível excluir o ingresso.');
    }

    jsonResponse(true, 'Ingresso excluído.');
}

// ════════════════════════════════════════════════════════════════════════════
// RESTORE_INGRESSO — restaura um ingresso individualmente
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'restore_ingresso') {
    ensurePermission('pode_editar');

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        jsonResponse(false, 'ID inválido.');
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM ingresso_tickets WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1");
    $stmtCheck->execute([$id]);
    if (!$stmtCheck->fetchColumn()) {
        jsonResponse(false, 'Ingresso não encontrado ou já está ativo.');
    }

    try {
        $pdo->prepare("UPDATE ingresso_tickets SET deleted_at = NULL WHERE id = ?")
            ->execute([$id]);
    } catch (Throwable $e) {
        jsonResponse(false, 'Não foi possível restaurar o ingresso.');
    }

    jsonResponse(true, 'Ingresso restaurado com sucesso.');
}

// ════════════════════════════════════════════════════════════════════════════
// UPDATE_ORDEM_INGRESSOS — salva nova ordem após drag-and-drop
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'update_ordem_ingressos') {
    ensurePermission('pode_editar');

    $items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($items) || empty($items)) {
        jsonResponse(false, 'Dados inválidos.');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE ingresso_tickets SET ordem = ?, updated_at = NOW() WHERE id = ?");
        foreach ($items as $item) {
            $ticketId  = (int)($item['id']    ?? 0);
            $novaOrdem = (int)($item['ordem'] ?? 0);
            if ($ticketId > 0) {
                $stmt->execute([$novaOrdem, $ticketId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'Não foi possível salvar a ordem.');
    }

    jsonResponse(true, 'Ordem atualizada.');
}

// ════════════════════════════════════════════════════════════════════════════
// UPDATE_ORDEM_LOTES — salva nova ordem dos lotes após drag-and-drop
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'update_ordem_lotes') {
    ensurePermission('pode_editar');

    $items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($items) || empty($items)) {
        jsonResponse(false, 'Dados inválidos.');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE ingresso_batches SET ordem = ?, updated_at = NOW() WHERE id = ?");
        foreach ($items as $item) {
            $loteId    = (int)($item['id']    ?? 0);
            $novaOrdem = (int)($item['ordem'] ?? 0);
            if ($loteId > 0) {
                $stmt->execute([$novaOrdem, $loteId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'Não foi possível salvar a ordem.');
    }

    jsonResponse(true, 'Ordem atualizada.');
}
