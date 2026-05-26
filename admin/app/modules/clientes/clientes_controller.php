<?php

// ============================================================================
// CLIENTES CONTROLLER — Gestão de usuários do site principal
// ============================================================================
// ✔ Permissões por ação (módulo 'clientes')
// ✔ Proteção CSRF em todas as mutações
// ✔ Validação e sanitização rigorosa de inputs
// ✔ Soft-delete (deleted_at) — sem apagamento físico imediato
// ✔ Bloqueio / desbloqueio de conta
// ✔ Verificação manual de e-mail
// ✔ Revogação de sessões "lembre-me"
// ✔ Exportação CSV segura
// ✔ JSON limpo sempre (ob_start + ob_clean)
// ============================================================================

ob_start();

$appPath = dirname(__DIR__, 2);
require_once $appPath . '/core/config.php';
require_once $appPath . '/core/auth.php';
require_once $appPath . '/core/permissions.php';

// ---------------------------------------------------------------------------
// Helper: resposta JSON sem ruído de output
// ---------------------------------------------------------------------------
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
    if (!empty($extra)) {
        $payload = array_merge($payload, $extra);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------------
// Garante login
// ---------------------------------------------------------------------------
if (function_exists('verificarLogin')) {
    verificarLogin();
} elseif (empty($_SESSION['usuario_id'])) {
    jsonResponse(false, 'Acesso não autorizado.');
}

if (!isset($pdo)) {
    jsonResponse(false, 'Conexão com o banco indisponível.');
}

$operadorId = $_SESSION['usuario_id'] ?? 0;

// ---------------------------------------------------------------------------
// Auto-migration: adiciona colunas extras na tabela users (idempotente)
// ---------------------------------------------------------------------------
function ensureClientesColumns(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $cols = ['blocked_at' => 'DATETIME NULL', 'admin_notes' => 'TEXT NULL', 'deleted_at' => 'DATETIME NULL'];
    foreach ($cols as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$def} DEFAULT NULL");
        } catch (Throwable $e) {
            // já existe — ok
        }
    }
}

ensureClientesColumns($pdo);

// ---------------------------------------------------------------------------
// Ação
// ---------------------------------------------------------------------------
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

$allowedActions = [
    'list', 'get', 'update', 'verify_email',
    'toggle_block', 'revoke_sessions', 'delete',
    'restore', 'export_csv', 'get_activity',
];

if (!in_array($action, $allowedActions, true)) {
    jsonResponse(false, 'Ação inválida.');
}

// ---------------------------------------------------------------------------
// Mapa de permissões
// ---------------------------------------------------------------------------
$mapaPermissao = [
    'list'            => 'listar',
    'get'             => 'listar',
    'get_activity'    => 'listar',
    'export_csv'      => 'listar',
    'update'          => 'editar',
    'verify_email'    => 'editar',
    'toggle_block'    => 'editar',
    'revoke_sessions' => 'editar',
    'delete'          => 'excluir',
    'restore'         => 'editar',
];

$modulo = 'clientes';
$permissaoNecessaria = $mapaPermissao[$action];

if (!checkActionPermission($operadorId, $modulo, $permissaoNecessaria)) {
    jsonResponse(false, 'Você não tem permissão para executar esta ação.');
}

// ---------------------------------------------------------------------------
// CSRF — apenas ações que alteram estado
// ---------------------------------------------------------------------------
$requiresCSRF = ['update', 'verify_email', 'toggle_block', 'revoke_sessions', 'delete', 'restore'];
if (in_array($action, $requiresCSRF, true)) {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        jsonResponse(false, 'Falha de segurança: token inválido. Recarregue a página.');
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function formatarCPF(?string $cpf): string
{
    if (!$cpf) return '--';
    $c = preg_replace('/\D/', '', $cpf);
    if (strlen($c) !== 11) return $cpf;
    return substr($c,0,3).'.'.substr($c,3,3).'.'.substr($c,6,3).'-'.substr($c,9,2);
}

function formatarWhatsApp(?string $num): string
{
    if (!$num) return '--';
    $d = preg_replace('/\D/', '', $num);
    if (strpos($d, '55') === 0) $d = substr($d, 2);
    if (strlen($d) === 11) return sprintf('(%s) %s %s-%s', substr($d,0,2), substr($d,2,1), substr($d,3,4), substr($d,7));
    if (strlen($d) === 10) return sprintf('(%s) %s-%s', substr($d,0,2), substr($d,2,4), substr($d,6));
    return $num;
}

function normalizarCPF(string $cpf): string
{
    return preg_replace('/\D/', '', $cpf) ?? '';
}

function normalizarWhatsApp(string $num): string
{
    $d = preg_replace('/\D/', '', $num);
    if ($d === '') return '';
    if (strpos($d, '55') !== 0) $d = '55' . $d;
    return $d;
}

function validarCPF(string $cpf): bool
{
    $c = preg_replace('/\D/', '', $cpf);
    if (strlen($c) !== 11) return false;
    if (preg_match('/^(\d)\1{10}$/', $c)) return false;
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) $soma += $c[$i] * ($t + 1 - $i);
        $d = ((10 * $soma) % 11) % 10;
        if ((int)$c[$t] !== $d) return false;
    }
    return true;
}

function hidratarCliente(array $u): array
{
    $u['cpf_formatado']       = formatarCPF($u['cpf'] ?? null);
    $u['whatsapp_formatado']  = formatarWhatsApp($u['whatsapp_number'] ?? null);
    $u['email_verificado']    = !empty($u['email_verified_at']);
    $u['bloqueado']           = !empty($u['blocked_at']);
    $u['excluido']            = !empty($u['deleted_at']);
    $u['status_label']        = !empty($u['deleted_at'])
        ? 'Excluído'
        : (!empty($u['blocked_at']) ? 'Bloqueado' : 'Ativo');
    return $u;
}


// ============================================================================
// ACTION: list
// ============================================================================
if ($action === 'list') {
    $incluirExcluidos = (int)($_GET['incluir_excluidos'] ?? 0);

    $where = $incluirExcluidos ? '1=1' : 'u.deleted_at IS NULL';

    $filtroStatus = trim($_GET['status'] ?? '');
    if ($filtroStatus === 'bloqueado') {
        $where .= ' AND u.blocked_at IS NOT NULL AND u.deleted_at IS NULL';
    } elseif ($filtroStatus === 'nao_verificado') {
        $where .= ' AND u.email_verified_at IS NULL AND u.deleted_at IS NULL';
    } elseif ($filtroStatus === 'verificado') {
        $where .= ' AND u.email_verified_at IS NOT NULL AND u.deleted_at IS NULL';
    }

    $stmt = $pdo->query("
        SELECT
            u.id,
            u.name,
            u.email,
            u.cpf,
            u.whatsapp_number,
            u.verification_channel,
            u.email_verified_at,
            u.blocked_at,
            u.deleted_at,
            u.admin_notes,
            u.created_at,
            u.updated_at,
            (SELECT COUNT(*) FROM remember_tokens rt WHERE rt.user_id = u.id AND rt.expires_at >= NOW()) AS sessoes_ativas,
            (SELECT COUNT(*) FROM verification_codes vc WHERE vc.user_id = u.id) AS total_codigos
        FROM users u
        WHERE {$where}
        ORDER BY u.id DESC
    ");

    $clientes = array_map('hidratarCliente', $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Estatísticas rápidas
    $stats = [];
    try {
        $stats['total']         = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
        $stats['ativos']        = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND blocked_at IS NULL")->fetchColumn();
        $stats['bloqueados']    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND blocked_at IS NOT NULL")->fetchColumn();
        $stats['verificados']   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND email_verified_at IS NOT NULL")->fetchColumn();
        $stats['nao_verificados'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND email_verified_at IS NULL")->fetchColumn();
        $stats['excluidos']     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NOT NULL")->fetchColumn();
        $stats['novos_30d']     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    } catch (Throwable $e) {
        $stats = [];
    }

    jsonResponse(true, ['clientes' => $clientes, 'stats' => $stats]);
}


// ============================================================================
// ACTION: get
// ============================================================================
if ($action === 'get') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
        ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id) jsonResponse(false, 'ID inválido.');

    $stmt = $pdo->prepare("
        SELECT
            u.id, u.name, u.email, u.cpf, u.whatsapp_number,
            u.verification_channel, u.email_verified_at,
            u.blocked_at, u.deleted_at, u.admin_notes,
            u.created_at, u.updated_at,
            (SELECT COUNT(*) FROM remember_tokens rt WHERE rt.user_id = u.id AND rt.expires_at >= NOW()) AS sessoes_ativas,
            (SELECT COUNT(*) FROM verification_codes vc WHERE vc.user_id = u.id) AS total_codigos,
            (SELECT MAX(vc2.created_at) FROM verification_codes vc2 WHERE vc2.user_id = u.id) AS ultimo_codigo_em
        FROM users u
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) jsonResponse(false, 'Cliente não encontrado.');

    jsonResponse(true, ['cliente' => hidratarCliente($u)]);
}


// ============================================================================
// ACTION: get_activity — últimos códigos de verificação e tokens ativos
// ============================================================================
if ($action === 'get_activity') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) jsonResponse(false, 'ID inválido.');

    $codigos = $pdo->prepare("
        SELECT purpose, created_at, consumed_at, expires_at, attempts_left, created_ip
        FROM verification_codes
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 20
    ");
    $codigos->execute([$id]);

    $tokens = $pdo->prepare("
        SELECT selector, expires_at, created_at, created_ip, created_ua
        FROM remember_tokens
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 10
    ");
    $tokens->execute([$id]);

    jsonResponse(true, [
        'codigos' => $codigos->fetchAll(PDO::FETCH_ASSOC),
        'tokens'  => $tokens->fetchAll(PDO::FETCH_ASSOC),
    ]);
}


// ============================================================================
// ACTION: update — editar dados do cliente
// ============================================================================
if ($action === 'update') {
    $id    = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf   = normalizarCPF($_POST['cpf'] ?? '');
    $whatsapp = normalizarWhatsApp($_POST['whatsapp_number'] ?? '');
    $channel  = trim($_POST['verification_channel'] ?? 'email');
    $notes    = trim($_POST['admin_notes'] ?? '');

    if (!$id)      jsonResponse(false, 'ID inválido.');
    if ($name === '') jsonResponse(false, 'O nome é obrigatório.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Email inválido.');
    if ($cpf !== '' && !validarCPF($cpf)) jsonResponse(false, 'CPF inválido.');
    if (!in_array($channel, ['email', 'whatsapp'], true)) jsonResponse(false, 'Canal de verificação inválido.');

    // Checa se cliente existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) jsonResponse(false, 'Cliente não encontrado.');

    // Email duplicado
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) jsonResponse(false, 'Já existe outro cliente com este e-mail.');

    // CPF duplicado
    if ($cpf !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE cpf = ? AND id <> ? LIMIT 1");
        $stmt->execute([$cpf, $id]);
        if ($stmt->fetch()) jsonResponse(false, 'Já existe outro cliente com este CPF.');
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE users
            SET name = ?, email = ?, cpf = ?, whatsapp_number = ?,
                verification_channel = ?, admin_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $name,
            $email,
            $cpf !== '' ? $cpf : null,
            $whatsapp !== '' ? $whatsapp : null,
            $channel,
            $notes !== '' ? $notes : null,
            $id
        ]);
    } catch (Throwable $e) {
        jsonResponse(false, 'Erro ao atualizar o cliente.');
    }

    jsonResponse(true, 'Cliente atualizado com sucesso.');
}


// ============================================================================
// ACTION: verify_email — verificar e-mail manualmente
// ============================================================================
if ($action === 'verify_email') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) jsonResponse(false, 'ID inválido.');

    $stmt = $pdo->prepare("SELECT email_verified_at, deleted_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) jsonResponse(false, 'Cliente não encontrado.');
    if ($u['deleted_at']) jsonResponse(false, 'Não é possível verificar um cliente excluído.');
    if ($u['email_verified_at']) jsonResponse(false, 'E-mail já está verificado.');

    $pdo->prepare("UPDATE users SET email_verified_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$id]);

    jsonResponse(true, 'E-mail verificado com sucesso.');
}


// ============================================================================
// ACTION: toggle_block — bloquear / desbloquear cliente
// ============================================================================
if ($action === 'toggle_block') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) jsonResponse(false, 'ID inválido.');

    $stmt = $pdo->prepare("SELECT blocked_at, deleted_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) jsonResponse(false, 'Cliente não encontrado.');
    if ($u['deleted_at']) jsonResponse(false, 'Não é possível alterar um cliente excluído.');

    if ($u['blocked_at']) {
        // Desbloquear
        $pdo->prepare("UPDATE users SET blocked_at = NULL, updated_at = NOW() WHERE id = ?")->execute([$id]);
        jsonResponse(true, 'Cliente desbloqueado com sucesso.');
    } else {
        // Bloquear + revogar sessões ativas
        $pdo->prepare("UPDATE users SET blocked_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$id]);
        jsonResponse(true, 'Cliente bloqueado e sessões revogadas com sucesso.');
    }
}


// ============================================================================
// ACTION: revoke_sessions — revogar tokens "lembre-me" ativos
// ============================================================================
if ($action === 'revoke_sessions') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) jsonResponse(false, 'ID inválido.');

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) jsonResponse(false, 'Cliente não encontrado.');

    $del = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
    $del->execute([$id]);
    $revogados = $del->rowCount();

    jsonResponse(true, $revogados > 0
        ? "Sessões revogadas ({$revogados} token(s) removido(s))."
        : 'Nenhuma sessão ativa encontrada.');
}


// ============================================================================
// ACTION: delete — soft-delete do cliente
// ============================================================================
if ($action === 'delete') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) jsonResponse(false, 'ID inválido.');

    $stmt = $pdo->prepare("SELECT deleted_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) jsonResponse(false, 'Cliente não encontrado.');
    if ($u['deleted_at']) jsonResponse(false, 'Cliente já está excluído.');

    try {
        $pdo->beginTransaction();
        // Soft delete
        $pdo->prepare("UPDATE users SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$id]);
        // Revoga sessões
        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'Erro ao excluir o cliente.');
    }

    jsonResponse(true, 'Cliente excluído (soft-delete). Pode ser restaurado se necessário.');
}


// ============================================================================
// ACTION: restore — restaurar soft-delete
// ============================================================================
if ($action === 'restore') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) jsonResponse(false, 'ID inválido.');

    $stmt = $pdo->prepare("SELECT deleted_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) jsonResponse(false, 'Cliente não encontrado.');
    if (!$u['deleted_at']) jsonResponse(false, 'Cliente não está excluído.');

    $pdo->prepare("UPDATE users SET deleted_at = NULL, updated_at = NOW() WHERE id = ?")->execute([$id]);

    jsonResponse(true, 'Cliente restaurado com sucesso.');
}


// ============================================================================
// ACTION: export_csv — exportar lista em CSV
// ============================================================================
if ($action === 'export_csv') {
    $incluirExcluidos = (int)($_GET['incluir_excluidos'] ?? 0);
    $where = $incluirExcluidos ? '1=1' : 'deleted_at IS NULL';

    $stmt = $pdo->query("
        SELECT
            id, name, email, cpf, whatsapp_number, verification_channel,
            email_verified_at, blocked_at, deleted_at, created_at, updated_at
        FROM users
        WHERE {$where}
        ORDER BY id DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (ob_get_length()) ob_clean();

    $filename = 'clientes_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // BOM UTF-8 para Excel reconhecer acentos
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['ID', 'Nome', 'Email', 'CPF', 'WhatsApp', 'Canal', 'Email Verificado', 'Bloqueado em', 'Excluído em', 'Cadastrado em', 'Atualizado em'], ';');

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['name'],
            $r['email'],
            formatarCPF($r['cpf']),
            formatarWhatsApp($r['whatsapp_number']),
            $r['verification_channel'],
            $r['email_verified_at'] ?? '',
            $r['blocked_at'] ?? '',
            $r['deleted_at'] ?? '',
            $r['created_at'],
            $r['updated_at'],
        ], ';');
    }

    fclose($out);
    exit;
}
