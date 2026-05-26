<?php
// ============================================================================
// FAQ CONTROLLER — Gerenciamento de perguntas e respostas (FAQ)
// ============================================================================
// ✔ Proteção CSRF em todas as mutações
// ✔ Permissões por ação (módulo 'faq')
// ✔ Soft delete via deleted_at
// ✔ Auto-migration idempotente
// ✔ Reordenação via drag-and-drop
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
function faqJsonResp(bool $success, $messageOrData = null, array $extra = []): void
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
    faqJsonResp(false, 'Acesso não autorizado.');
}

if (!isset($pdo)) faqJsonResp(false, 'Conexão com o banco indisponível.');

$operadorId = (int)($_SESSION['usuario_id'] ?? 0);
$modulo     = 'faq';

// ---------------------------------------------------------------------------
// Auto-migration idempotente
// ---------------------------------------------------------------------------
(function (PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS faq_perguntas (
            id          INT UNSIGNED      NOT NULL AUTO_INCREMENT PRIMARY KEY,
            icone       VARCHAR(120)      NOT NULL DEFAULT 'help',
            pergunta    VARCHAR(500)      NOT NULL,
            resposta    TEXT              NOT NULL,
            ordem       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            ativo       TINYINT(1)        NOT NULL DEFAULT 1,
            criado_por  INT UNSIGNED      NULL,
            created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at  DATETIME          NULL,
            INDEX idx_ativo   (ativo),
            INDEX idx_ordem   (ordem),
            INDEX idx_deleted (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
})($pdo);

// ---------------------------------------------------------------------------
// Rota
// ---------------------------------------------------------------------------
$action = trim($_GET['action'] ?? $_POST['action'] ?? 'listar');

$allowedActions = ['listar', 'salvar', 'excluir', 'reordenar'];
if (!in_array($action, $allowedActions, true)) {
    faqJsonResp(false, 'Ação inválida.');
}

// ---------------------------------------------------------------------------
// Mapa de permissão por ação
// ---------------------------------------------------------------------------
$mapaPermissao = [
    'listar'    => 'pode_visualizar',
    'salvar'    => null, // verificado internamente (criar vs editar)
    'excluir'   => 'pode_excluir',
    'reordenar' => 'pode_editar',
];

if ($mapaPermissao[$action] !== null) {
    if (!userHasPermission($operadorId, $modulo, $mapaPermissao[$action])) {
        faqJsonResp(false, 'Sem permissão para executar esta ação.');
    }
}

// ---------------------------------------------------------------------------
// CSRF — exigido em todas as mutações
// ---------------------------------------------------------------------------
$requiresCSRF = ['salvar', 'excluir', 'reordenar'];
if (in_array($action, $requiresCSRF, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        faqJsonResp(false, 'Método não permitido.');
    }
    $csrfEnviado = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfEnviado)) {
        faqJsonResp(false, 'Token de segurança inválido. Recarregue a página.');
    }
}

// ---------------------------------------------------------------------------
// Helpers de sanitização
// ---------------------------------------------------------------------------
function faqSanitize(string $val, int $max = 255): string
{
    return mb_substr(trim($val), 0, $max);
}

function faqSanitizeIcone(string $val): string
{
    // Aceita nomes Material Symbols e listas de classes CSS de icones.
    $clean = strtolower(trim($val));
    $clean = preg_replace('/[^a-z0-9_\-\s]/', '', $clean);
    $clean = preg_replace('/\s+/', ' ', $clean);

    // Corrige registros/entradas salvas pelo sanitizador antigo:
    // "fa-brandsfa-amazon" -> "fa-brands fa-amazon".
    if (!str_contains($clean, ' ')) {
        $prefixes = [
            'fa-duotone', 'fa-regular', 'fa-brands', 'fa-solid',
            'fa-light', 'fa-thin', 'fas', 'far', 'fab', 'fal', 'fad',
            'las', 'lar', 'lab',
        ];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($clean, $prefix)) {
                $rest = substr($clean, strlen($prefix));
                if (preg_match('/^(fa|la)-[a-z0-9_-]+$/', $rest)) {
                    $clean = $prefix . ' ' . $rest;
                    break;
                }
            }
        }
    }

    return mb_substr($clean, 0, 120);
}

// ============================================================================
// ACTION: listar
// ============================================================================
if ($action === 'listar' && $_SERVER['REQUEST_METHOD'] === 'GET') {

    $stmt = $pdo->prepare("
        SELECT id, icone, pergunta, resposta, ordem, ativo, created_at
        FROM faq_perguntas
        WHERE deleted_at IS NULL
        ORDER BY ordem ASC, id ASC
    ");
    $stmt->execute();
    $perguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($perguntas as &$p) {
        $p['id']    = (int)$p['id'];
        $p['ordem'] = (int)$p['ordem'];
        $p['ativo'] = (int)$p['ativo'];
    }
    unset($p);

    faqJsonResp(true, ['perguntas' => $perguntas]);
}

// ============================================================================
// ACTION: salvar (criar ou editar)
// ============================================================================
if ($action === 'salvar') {

    $id    = (int)($_POST['id'] ?? 0);
    $isNew = $id === 0;

    // Verificação de permissão granular
    if ($isNew && !userHasPermission($operadorId, $modulo, 'pode_criar')) {
        faqJsonResp(false, 'Sem permissão para criar perguntas.');
    }
    if (!$isNew && !userHasPermission($operadorId, $modulo, 'pode_editar')) {
        faqJsonResp(false, 'Sem permissão para editar perguntas.');
    }

    // Sanitização
    $icone    = faqSanitizeIcone($_POST['icone']    ?? 'help');
    $pergunta = faqSanitize($_POST['pergunta'] ?? '', 500);
    $resposta = faqSanitize($_POST['resposta'] ?? '', 3000);
    $ativo    = isset($_POST['ativo']) ? 1 : 0;

    if ($icone === '') $icone = 'help';

    // Validação
    $erros = [];
    if ($pergunta === '') $erros[] = 'A pergunta é obrigatória.';
    if (mb_strlen($pergunta) > 500) $erros[] = 'A pergunta deve ter no máximo 500 caracteres.';
    if ($resposta === '') $erros[] = 'A resposta é obrigatória.';
    if (mb_strlen($resposta) > 3000) $erros[] = 'A resposta deve ter no máximo 3000 caracteres.';

    if (!empty($erros)) {
        faqJsonResp(false, implode(' ', $erros));
    }

    if ($isNew) {
        // Próxima posição na ordem
        $maxOrdem = (int)$pdo->query(
            "SELECT COALESCE(MAX(ordem), 0) FROM faq_perguntas WHERE deleted_at IS NULL"
        )->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO faq_perguntas (icone, pergunta, resposta, ordem, ativo, criado_por)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$icone, $pergunta, $resposta, $maxOrdem + 1, $ativo, $operadorId]);
        $novoId = (int)$pdo->lastInsertId();
        faqJsonResp(true, ['message' => 'Pergunta cadastrada com sucesso!', 'id' => $novoId]);
    } else {
        // Verifica existência
        $chk = $pdo->prepare("SELECT id FROM faq_perguntas WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $chk->execute([$id]);
        if (!$chk->fetch()) faqJsonResp(false, 'Pergunta não encontrada.');

        $stmt = $pdo->prepare("
            UPDATE faq_perguntas
            SET icone = ?, pergunta = ?, resposta = ?, ativo = ?, updated_at = NOW()
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$icone, $pergunta, $resposta, $ativo, $id]);
        faqJsonResp(true, 'Pergunta atualizada com sucesso!');
    }
}

// ============================================================================
// ACTION: excluir (soft delete)
// ============================================================================
if ($action === 'excluir') {

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) faqJsonResp(false, 'ID inválido.');

    $stmt = $pdo->prepare("
        UPDATE faq_perguntas SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) faqJsonResp(false, 'Pergunta não encontrada ou já removida.');
    faqJsonResp(true, 'Pergunta removida com sucesso!');
}

// ============================================================================
// ACTION: reordenar
// ============================================================================
if ($action === 'reordenar') {

    $ids = json_decode($_POST['ids'] ?? '[]', true);
    if (!is_array($ids) || empty($ids)) faqJsonResp(false, 'Dados de ordenação inválidos.');

    $upd = $pdo->prepare("
        UPDATE faq_perguntas SET ordem = ? WHERE id = ? AND deleted_at IS NULL
    ");

    $pdo->beginTransaction();
    try {
        foreach ($ids as $pos => $faqId) {
            $upd->execute([(int)$pos + 1, (int)$faqId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        faqJsonResp(false, 'Erro ao reordenar. Tente novamente.');
    }

    faqJsonResp(true, 'Ordem atualizada com sucesso!');
}

faqJsonResp(false, 'Ação não reconhecida.');
