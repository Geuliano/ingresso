<?php
requireLogin();
require_once APP_PATH . '/core/module_header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdoColumnsEnsured = false;
function ensureUsuariosExtraColumns(PDO $pdo): void
{
    global $pdoColumnsEnsured;
    if ($pdoColumnsEnsured) {
        return;
    }
    $pdoColumnsEnsured = true;
    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN apelido VARCHAR(160) NULL AFTER nome");
    } catch (Throwable $e) {
        // já existe ou sem permissão
    }
    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN telefone VARCHAR(20) NULL AFTER email");
    } catch (Throwable $e) {
        // já existe ou sem permissão
    }
}
ensureUsuariosExtraColumns($pdo);

$telefoneExibicao = null;

function formatarTelefoneParaExibicao(?string $telefone): ?string
{
    if (!$telefone) {
        return null;
    }
    $numeros = preg_replace('/\D+/', '', $telefone);
    if (strpos($numeros, '55') !== 0) {
        return $telefone;
    }
    $semDdi = substr($numeros, 2);
    if (strlen($semDdi) === 10) {
        return sprintf('+55 (%s) %s-%s', substr($semDdi, 0, 2), substr($semDdi, 2, 4), substr($semDdi, 6));
    }
    if (strlen($semDdi) === 11) {
        return sprintf('+55 (%s) %s %s-%s', substr($semDdi, 0, 2), substr($semDdi, 2, 1), substr($semDdi, 3, 4), substr($semDdi, 7));
    }
    return '+55 ' . $semDdi;
}

$usuarioId = $_SESSION['usuario_id'] ?? 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            nome,
            apelido,
            email,
            telefone,
            avatar,
            ultimo_login,
            tema,
            criado_em
        FROM usuarios
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $usuario = [];
}

$defaultAvatar = BASE_URL . 'public/assets/images/users/default.png';
$avatarAtual = !empty($usuario['avatar'])
    ? BASE_URL . 'public/uploads/avatars/' . $usuario['avatar']
    : $defaultAvatar;
$telefoneExibicao = formatarTelefoneParaExibicao($usuario['telefone'] ?? null);

$csrfToken = $_SESSION['csrf_token'];
?>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <img
                    src="<?= htmlspecialchars($avatarAtual) ?>"
                    id="previewAvatar"
                    alt="Avatar"
                    class="rounded-circle shadow mb-3"
                    style="width:140px;height:140px;object-fit:cover">
                <h4><?= htmlspecialchars($usuario['nome'] ?? '') ?></h4>
                <?php if (!empty($usuario['apelido'] ?? '')): ?>
                    <p class="text-muted mb-1">Apelido: <?= htmlspecialchars($usuario['apelido']) ?></p>
                <?php endif; ?>
                <p class="text-muted mb-0"><?= htmlspecialchars($usuario['email'] ?? '') ?></p>
                <?php if ($telefoneExibicao): ?>
                    <small class="text-muted d-block mt-2"><?= htmlspecialchars($telefoneExibicao) ?></small>
                <?php endif; ?>
                <small class="text-muted d-block mt-3">Último login: <?= !empty($usuario['ultimo_login']) ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : '-' ?></small>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Atualizar dados pessoais</h5>
            </div>
            <div class="card-body">
                <form id="formPerfil" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" name="nome" value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apelido</label>
                            <input type="text" class="form-control" name="apelido" value="<?= htmlspecialchars($usuario['apelido'] ?? '') ?>" placeholder="Como prefere ser chamado">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefone</label>
                            <div class="input-group">
                                <span class="input-group-text">+55</span>
                                <input type="text" class="form-control" name="telefone" id="perfil_telefone" value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>" placeholder="(11) 98888-7777" inputmode="tel">
                            </div>
                            <small class="text-muted">Será salvo com DDI para uso no WhatsApp.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Avatar</label>
                            <input type="file" class="form-control" name="avatar" accept="image/*">
                            <small class="text-muted d-block">JPG/PNG até 2MB</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tema preferido</label>
                            <select class="form-select" name="tema">
                                <option value="light" <?= ($usuario['tema'] ?? 'light') === 'light' ? 'selected' : '' ?>>Claro</option>
                                <option value="dark" <?= ($usuario['tema'] ?? 'light') === 'dark' ? 'selected' : '' ?>>Escuro</option>
                            </select>
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-success">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Alterar senha</h5>
            </div>
            <div class="card-body">
                <form id="formSenha">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label">Senha atual</label>
                        <input type="password" class="form-control" name="senha_atual" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nova senha</label>
                        <input type="password" class="form-control" name="senha_nova" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar nova senha</label>
                        <input type="password" class="form-control" name="senha_confirma" required>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Atualizar senha</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const perfilController = '<?= BASE_URL ?>app/modules/meus-dados/meus_dados_controller.php';
    const defaultAvatar = '<?= addslashes($defaultAvatar) ?>';
    const currentAvatar = '<?= addslashes($avatarAtual) ?>';

    function formatTelefoneBr(value) {
        let digits = (value || '').replace(/\D+/g, '');
        if (digits.startsWith('55') && digits.length > 11) {
            digits = digits.slice(2);
        }
        digits = digits.slice(0, 11);
        const ddd = digits.slice(0, 2);
        const rest = digits.slice(2);
        if (!ddd) return '';
        if (rest.length <= 4) return `(${ddd}) ${rest}`;
        if (rest.length <= 8) return `(${ddd}) ${rest.slice(0, 4)}-${rest.slice(4)}`;
        return `(${ddd}) ${rest.slice(0, 5)}-${rest.slice(5, 9)}`;
    }

    function attachTelefoneMaskPerfil() {
        const tel = document.getElementById('perfil_telefone');
        if (!tel) return;
        // Formata valor inicial
        tel.value = formatTelefoneBr(tel.value);
        tel.addEventListener('input', () => {
            tel.value = formatTelefoneBr(tel.value);
        });
    }

    function setPreviewSrc(src) {
        const preview = document.getElementById('previewAvatar');
        if (!preview) return;
        preview.onerror = () => {
            preview.onerror = null;
            preview.src = defaultAvatar;
        };
        preview.src = src || defaultAvatar;
    }

    attachTelefoneMaskPerfil();
    setPreviewSrc(currentAvatar);

    document.querySelector('input[name="avatar"]')?.addEventListener('change', (event) => {
        const file = event.target.files?.[0];
        if (file) {
            const url = URL.createObjectURL(file);
            setPreviewSrc(url);
        } else {
            setPreviewSrc(currentAvatar);
        }
    });

    document.getElementById('formPerfil')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        const fd = new FormData(form);
        fd.append('action', 'update_profile');

        btn.disabled = true;
        btn.textContent = 'Salvando...';

        try {
            const resp = await fetch(perfilController, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (data.success) {
                adminToast(data.message || 'Dados atualizados.', 'success');
                setTimeout(() => location.reload(), 900);
            } else {
                adminAlert(data.message || 'Não foi possível salvar.', 'danger');
            }
        } catch (err) {
            adminAlert(err.message || 'Erro inesperado.', 'danger');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar alterações';
        }
    });

    document.getElementById('formSenha')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        const fd = new FormData(form);
        fd.append('action', 'update_password');

        btn.disabled = true;
        btn.textContent = 'Atualizando...';

        try {
            const resp = await fetch(perfilController, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (data.success) {
                adminToast(data.message || 'Senha atualizada.', 'success');
                form.reset();
            } else {
                adminAlert(data.message || 'Não foi possível atualizar.', 'danger');
            }
        } catch (err) {
            adminAlert(err.message || 'Erro inesperado.', 'danger');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Atualizar senha';
        }
    });
</script>
