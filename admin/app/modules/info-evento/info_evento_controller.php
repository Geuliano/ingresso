<?php

// ============================================================================
// INFO-EVENTO CONTROLLER — Dados do evento e do produtor
// ============================================================================
// ✔ Permissões por ação (módulo 'info-evento')
// ✔ Proteção CSRF em todas as mutações
// ✔ Validação e sanitização rigorosa de inputs
// ✔ Auto-migration idempotente da tabela info_evento
// ✔ Registro único — sempre atualiza o mesmo registro (id = 1)
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

$operadorId = (int)($_SESSION['usuario_id'] ?? 0);
$modulo     = 'info-evento';

// ---------------------------------------------------------------------------
// Auto-migration idempotente — cria a tabela info_evento se não existir
// ---------------------------------------------------------------------------
function ensureInfoEventoTable(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;

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
            detalhes_evento       TEXT              NULL,
            atualizado_por        INT UNSIGNED      NULL,
            created_at            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_atualizado_por (atualizado_por)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("INSERT IGNORE INTO info_evento (id) VALUES (1)");

    // Adiciona colunas se ainda não existem (bases criadas antes destes campos)
    try { $pdo->exec("ALTER TABLE info_evento ADD COLUMN detalhes_evento TEXT NULL AFTER produtor_telefone2"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE info_evento ADD COLUMN exibir_card_info TINYINT(1) NOT NULL DEFAULT 1 AFTER detalhes_evento"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE info_evento ADD COLUMN capa_evento VARCHAR(500) NOT NULL DEFAULT '' AFTER exibir_card_info"); } catch (Throwable $e) {}
}

ensureInfoEventoTable($pdo);

// ---------------------------------------------------------------------------
// Ação
// ---------------------------------------------------------------------------
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

$allowedActions = ['get', 'save', 'toggle_card', 'upload_capa', 'remove_capa'];

if (!in_array($action, $allowedActions, true)) {
    jsonResponse(false, 'Ação inválida.');
}

// ---------------------------------------------------------------------------
// Mapa de permissões
// ---------------------------------------------------------------------------
$mapaPermissao = [
    'get'          => 'pode_visualizar',
    'save'         => 'pode_editar',
    'toggle_card'  => 'pode_editar',
    'upload_capa'  => 'pode_editar',
    'remove_capa'  => 'pode_editar',
];

$permissaoNecessaria = $mapaPermissao[$action];

if (!userHasPermission($operadorId, $modulo, $permissaoNecessaria)) {
    jsonResponse(false, 'Você não tem permissão para executar esta ação.');
}

// ---------------------------------------------------------------------------
// CSRF — apenas ações que alteram estado
// ---------------------------------------------------------------------------
$requiresCSRF = ['save', 'toggle_card', 'upload_capa', 'remove_capa'];
if (in_array($action, $requiresCSRF, true)) {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        jsonResponse(false, 'Falha de segurança: token inválido. Recarregue a página.');
    }
}

// ---------------------------------------------------------------------------
// Helpers de formatação / validação
// ---------------------------------------------------------------------------

function sanitizeStr(string $val, int $maxLen = 255): string
{
    return mb_substr(trim($val), 0, $maxLen);
}

function validarEmail(string $email): bool
{
    return $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function normalizarCpfCnpj(string $val): string
{
    return preg_replace('/\D/', '', $val) ?? '';
}

function validarCpfCnpj(string $nums): bool
{
    if ($nums === '') return true;
    $len = strlen($nums);

    if ($len === 11) {
        if (preg_match('/^(\d)\1{10}$/', $nums)) return false;
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) $soma += (int)$nums[$i] * ($t + 1 - $i);
            $d = ((10 * $soma) % 11) % 10;
            if ((int)$nums[$t] !== $d) return false;
        }
        return true;
    }

    if ($len === 14) {
        if (preg_match('/^(\d)\1{13}$/', $nums)) return false;
        $pesos1 = [5,4,3,2,9,8,7,6,5,4,3,2];
        $pesos2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
        $s1 = 0; for ($i=0;$i<12;$i++) $s1+=(int)$nums[$i]*$pesos1[$i];
        $d1 = ($s1%11<2)?0:(11-$s1%11);
        if ((int)$nums[12]!==$d1) return false;
        $s2 = 0; for ($i=0;$i<13;$i++) $s2+=(int)$nums[$i]*$pesos2[$i];
        $d2 = ($s2%11<2)?0:(11-$s2%11);
        return (int)$nums[13]===$d2;
    }

    return false;
}

function formatarCpfCnpj(string $nums): string
{
    $len = strlen($nums);
    if ($len === 11) {
        return substr($nums,0,3).'.'.substr($nums,3,3).'.'.substr($nums,6,3).'-'.substr($nums,9,2);
    }
    if ($len === 14) {
        return substr($nums,0,2).'.'.substr($nums,2,3).'.'.substr($nums,5,3).'/'.substr($nums,8,4).'-'.substr($nums,12,2);
    }
    return $nums;
}

function validarDatetime(string $val): bool
{
    if ($val === '') return true;
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $val)
      ?: DateTime::createFromFormat('Y-m-d H:i:s', $val)
      ?: DateTime::createFromFormat('Y-m-d H:i', $val);
    return $d !== false;
}

function normalizeDatetime(string $val): ?string
{
    if ($val === '') return null;
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $val)
      ?: DateTime::createFromFormat('Y-m-d H:i:s', $val)
      ?: DateTime::createFromFormat('Y-m-d H:i', $val);
    return $d ? $d->format('Y-m-d H:i:s') : null;
}

// ============================================================================
// ACTION: get — lê o registro único
// ============================================================================
if ($action === 'get') {
    $stmt = $pdo->query("SELECT * FROM info_evento WHERE id = 1 LIMIT 1");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(false, 'Registro não encontrado.');
    }

    $cpfCnpjNums = preg_replace('/\D/', '', $row['produtor_cpf_cnpj'] ?? '');
    $row['produtor_cpf_cnpj_formatado'] = $cpfCnpjNums ? formatarCpfCnpj($cpfCnpjNums) : '';

    $row['data_inicio_input'] = $row['data_inicio']
        ? (new DateTime($row['data_inicio']))->format('Y-m-d\TH:i') : '';
    $row['data_fim_input'] = $row['data_fim']
        ? (new DateTime($row['data_fim']))->format('Y-m-d\TH:i') : '';

    jsonResponse(true, ['info' => $row]);
}

// ============================================================================
// ACTION: save — salva/atualiza os dados do evento e produtor
// ============================================================================
if ($action === 'save') {

    $nomeEvento  = sanitizeStr($_POST['nome_evento']  ?? '');
    $dataInicio  = sanitizeStr($_POST['data_inicio']  ?? '', 30);
    $dataFim     = sanitizeStr($_POST['data_fim']     ?? '', 30);
    $localNome   = sanitizeStr($_POST['local_nome']   ?? '');
    $localCidade = sanitizeStr($_POST['local_cidade'] ?? '', 120);
    $localEstado = strtoupper(sanitizeStr($_POST['local_estado'] ?? '', 2));

    $produtorNome     = sanitizeStr($_POST['produtor_nome']      ?? '');
    $produtorCpfCnpj  = normalizarCpfCnpj($_POST['produtor_cpf_cnpj'] ?? '');
    $produtorEndereco = sanitizeStr($_POST['produtor_endereco']  ?? '', 1000);
    $produtorEmail    = sanitizeStr($_POST['produtor_email']     ?? '');
    $produtorTel1     = sanitizeStr($_POST['produtor_telefone1'] ?? '', 30);
    $produtorTel2     = sanitizeStr($_POST['produtor_telefone2'] ?? '', 30);
    $detalhesEvento   = sanitizeStr($_POST['detalhes_evento']   ?? '', 3000);

    $erros = [];

    if ($nomeEvento === '') $erros[] = 'O nome do evento é obrigatório.';

    if ($dataInicio !== '' && !validarDatetime($dataInicio))
        $erros[] = 'Data/hora de início inválida.';
    if ($dataFim !== '' && !validarDatetime($dataFim))
        $erros[] = 'Data/hora de término inválida.';

    if ($dataInicio !== '' && $dataFim !== '' && validarDatetime($dataInicio) && validarDatetime($dataFim)) {
        $dtI = new DateTime($dataInicio);
        $dtF = new DateTime($dataFim);
        if ($dtF <= $dtI) $erros[] = 'A data de término deve ser posterior à data de início.';
    }

    if ($localEstado !== '' && !preg_match('/^[A-Z]{2}$/', $localEstado))
        $erros[] = 'Estado inválido (sigla com 2 letras, ex: RO, SP).';

    if (!validarEmail($produtorEmail))
        $erros[] = 'E-mail do produtor inválido.';

    if ($produtorCpfCnpj !== '' && !validarCpfCnpj($produtorCpfCnpj))
        $erros[] = 'CPF ou CNPJ do produtor inválido.';

    if (!empty($erros)) {
        jsonResponse(false, implode(' ', $erros));
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE info_evento SET
                nome_evento        = :nome_evento,
                data_inicio        = :data_inicio,
                data_fim           = :data_fim,
                local_nome         = :local_nome,
                local_cidade       = :local_cidade,
                local_estado       = :local_estado,
                produtor_nome      = :produtor_nome,
                produtor_cpf_cnpj  = :produtor_cpf_cnpj,
                produtor_endereco  = :produtor_endereco,
                produtor_email     = :produtor_email,
                produtor_telefone1 = :produtor_telefone1,
                produtor_telefone2 = :produtor_telefone2,
                detalhes_evento    = :detalhes_evento,
                atualizado_por     = :atualizado_por,
                updated_at         = NOW()
            WHERE id = 1
        ");

        $stmt->execute([
            ':nome_evento'        => $nomeEvento,
            ':data_inicio'        => normalizeDatetime($dataInicio),
            ':data_fim'           => normalizeDatetime($dataFim),
            ':local_nome'         => $localNome,
            ':local_cidade'       => $localCidade,
            ':local_estado'       => $localEstado,
            ':produtor_nome'      => $produtorNome,
            ':produtor_cpf_cnpj'  => $produtorCpfCnpj,
            ':produtor_endereco'  => $produtorEndereco !== '' ? $produtorEndereco : null,
            ':produtor_email'     => $produtorEmail,
            ':produtor_telefone1' => $produtorTel1,
            ':produtor_telefone2' => $produtorTel2,
            ':detalhes_evento'   => $detalhesEvento !== '' ? $detalhesEvento : null,
            ':atualizado_por'     => $operadorId,
        ]);

    } catch (Throwable $e) {
        jsonResponse(false, 'Erro ao salvar as informações do evento.');
    }

    jsonResponse(true, 'Informações salvas com sucesso.');
}

// ============================================================================
// ACTION: upload_capa — faz upload da imagem de capa do hero banner
// ============================================================================
if ($action === 'upload_capa') {

    $uploadDir = ROOT_PATH . '/public/uploads/evento/';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            jsonResponse(false, 'Não foi possível criar o diretório de upload.');
        }
    }

    if (empty($_FILES['capa']) || ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        jsonResponse(false, 'Nenhum arquivo enviado.');
    }

    $file = $_FILES['capa'];

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $erroMsg = [
            UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o limite do formulário.',
            UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária ausente.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar no disco.',
        ];
        jsonResponse(false, $erroMsg[$file['error']] ?? 'Erro no upload.');
    }

    // Limite de 5 MB
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(false, 'A imagem deve ter no máximo 5 MB.');
    }

    // Valida MIME real (não extensão)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!array_key_exists($mime, $extMap)) {
        jsonResponse(false, 'Formato inválido. Use JPG, PNG ou WebP.');
    }

    $ext      = $extMap[$mime];
    $filename = 'banner_' . bin2hex(random_bytes(8)) . '.' . $ext;

    // Remove capa antiga
    $stmtOld = $pdo->query("SELECT capa_evento FROM info_evento WHERE id = 1 LIMIT 1");
    $oldFile  = $stmtOld ? $stmtOld->fetchColumn() : '';
    if ($oldFile !== '' && $oldFile !== false) {
        $oldPath = $uploadDir . basename((string)$oldFile);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        jsonResponse(false, 'Falha ao salvar a imagem no servidor.');
    }

    $pdo->prepare("UPDATE info_evento SET capa_evento = ?, updated_at = NOW() WHERE id = 1")
        ->execute([$filename]);

    jsonResponse(true, ['message' => 'Capa enviada com sucesso.', 'filename' => $filename]);
}

// ============================================================================
// ACTION: remove_capa — remove a capa do banner
// ============================================================================
if ($action === 'remove_capa') {

    $uploadDir = ROOT_PATH . '/public/uploads/evento/';

    $stmtOld = $pdo->query("SELECT capa_evento FROM info_evento WHERE id = 1 LIMIT 1");
    $oldFile  = $stmtOld ? $stmtOld->fetchColumn() : '';

    if ($oldFile !== '' && $oldFile !== false) {
        $oldPath = $uploadDir . basename((string)$oldFile);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    $pdo->prepare("UPDATE info_evento SET capa_evento = '', updated_at = NOW() WHERE id = 1")
        ->execute();

    jsonResponse(true, 'Capa removida com sucesso.');
}

// ============================================================================
// ACTION: toggle_card — ativa/desativa exibição do card de info no site
// ============================================================================
if ($action === 'toggle_card') {
    $stmt = $pdo->query("SELECT exibir_card_info FROM info_evento WHERE id = 1 LIMIT 1");
    $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    if (!$row) {
        jsonResponse(false, 'Registro não encontrado.');
    }

    $novoValor = ((int)$row['exibir_card_info'] === 1) ? 0 : 1;

    $pdo->prepare("UPDATE info_evento SET exibir_card_info = ?, updated_at = NOW() WHERE id = 1")
        ->execute([$novoValor]);

    jsonResponse(true, [
        'message'         => $novoValor ? 'Card ativado.' : 'Card desativado.',
        'exibir_card_info' => $novoValor,
    ]);
}
