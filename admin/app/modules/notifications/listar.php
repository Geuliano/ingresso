<?php
requireLogin();
require_once APP_PATH . '/core/module_header.php';
require_once __DIR__ . '/notifications_helper.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ensureNotificationModulesTable($pdo);

try {
    $stmt = $pdo->query("
        SELECT
            n.id,
            n.titulo,
            n.mensagem,
            n.tipo_alerta,
            n.destino_tipo,
            n.destino_valor,
            n.vigencia_inicio,
            n.vigencia_fim,
            n.fixa,
            n.pode_fechar,
            n.ativo,
            n.criado_em,
            u.nome AS criado_por
        FROM notificacoes n
        LEFT JOIN usuarios u ON u.id = n.criado_por
        ORDER BY n.criado_em DESC
    ");
    $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $notificacoes = [];
}

$modulosPorNotificacao = [];
if (!empty($notificacoes)) {
    $ids = array_column($notificacoes, 'id');
    $modulosPorNotificacao = fetchNotificationModules($pdo, $ids);
    foreach ($notificacoes as &$notificacao) {
        $idAtual = (int)($notificacao['id'] ?? 0);
        $listaModulos = $modulosPorNotificacao[$idAtual] ?? [];
        $notificacao['modulos'] = $listaModulos;
        $notificacao['modulos_slugs'] = array_column($listaModulos, 'slug');
    }
    unset($notificacao);
}

try {
    $niveisAcesso = $pdo->query("SELECT id, nome FROM niveis_acesso ORDER BY nome ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $niveisAcesso = [];
}

try {
    $usuariosSistema = $pdo->query("SELECT id, nome, email FROM usuarios ORDER BY nome ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $usuariosSistema = [];
}

try {
    $modulosSistema = $pdo->query("
        SELECT slug, nome
        FROM modulos
        WHERE ativo = 1
        ORDER BY nome ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $modulosSistema = [];
}

$csrfToken = $_SESSION['csrf_token'];
$alertTypes = [
    'primary' => 'Primário',
    'secondary' => 'Secundário',
    'success' => 'Sucesso',
    'info' => 'Informação',
    'warning' => 'Alerta',
    'danger' => 'Perigo'
];
?>

<link href="<?= BASE_URL ?>public/assets/libs/simple-datatables/style.css" rel="stylesheet">
<style>
    #modalNotificacao .modal-dialog {
        max-width: 960px;
    }

    #modalNotificacao .modal-body {
        max-height: calc(100vh - 220px);
        overflow-y: auto;
    }

    .col-modulos {
        max-width: 240px;
        white-space: normal;
    }

    .col-modulos .badge {
        display: inline-block;
        margin-bottom: 4px;
    }
</style>

<div class="card">
    <div class="card-header">
        <div class="row align-items-center g-2">
            <div class="col">
                <h4 class="card-title mb-0">Notificações</h4>
                <p class="text-muted mb-0">Cadastre comunicados, alertas e regras de exibição por usuário, nível e módulo.</p>
            </div>
            <div class="col-auto">
                <button type="button" class="btn bg-primary text-white" onclick="openNotificationModal('create')">
                    <i class="las la-plus me-1"></i> Nova notificação
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table mb-0 table-hover" id="datatable_notifications">
                <thead class="table-dark">
                    <tr>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Alcance</th>
                        <th class="col-modulos">Modulos</th>
                        <th>Vigência</th>
                        <th>Status</th>
                        <th class="text-end">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notificacoes as $notificacao): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($notificacao['titulo']) ?></div>
                                <small class="text-muted">
                                    Criada por <?= htmlspecialchars($notificacao['criado_por'] ?? 'Sistema') ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-<?= htmlspecialchars($notificacao['tipo_alerta']) ?>">
                                    <?= htmlspecialchars($alertTypes[$notificacao['tipo_alerta']] ?? ucfirst($notificacao['tipo_alerta'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($notificacao['destino_tipo'] === 'nivel'):
                                    $destinosNiveis = array_filter(array_map('trim', explode(',', (string)($notificacao['destino_valor'] ?? ''))));
                                    $totalNiveis = count($destinosNiveis);
                                ?>
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        Níveis <?= $totalNiveis ? "($totalNiveis)" : '' ?>
                                    </span>
                                <?php elseif ($notificacao['destino_tipo'] === 'usuario'):
                                    $destinosUsuarios = array_filter(array_map('trim', explode(',', (string)($notificacao['destino_valor'] ?? ''))));
                                    $totalUsuarios = count($destinosUsuarios);
                                ?>
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        Usuários <?= $totalUsuarios ? "($totalUsuarios)" : '' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success">Todos</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-modulos">
                                <?php
                                $modulosNotificacao = $notificacao['modulos'] ?? [];
                                ?>
                                <?php if (empty($modulosNotificacao)): ?>
                                    <span class="badge bg-info-subtle text-info">Todos</span>
                                <?php else: ?>
                                    <?php foreach ($modulosNotificacao as $moduloInfo): ?>
                                        <span class="badge bg-primary-subtle text-primary mb-1">
                                            <?= htmlspecialchars($moduloInfo['nome']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $inicio = $notificacao['vigencia_inicio']
                                    ? date('d/m/Y H:i', strtotime($notificacao['vigencia_inicio']))
                                    : 'Imediata';
                                $fim = $notificacao['vigencia_fim']
                                    ? date('d/m/Y H:i', strtotime($notificacao['vigencia_fim']))
                                    : ($notificacao['fixa'] ? 'Sem data final' : 'â€”');
                                ?>
                                <small class="text-muted d-block"><?= $inicio ?></small>
                                <small class="text-muted d-block"><?= $fim ?></small>
                            </td>
                            <td>
                                <?php if ((int)$notificacao['ativo'] === 1): ?>
                                    <span class="badge bg-success-subtle text-success">Ativa</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Inativa</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-link text-secondary p-0 me-2"
                                    data-bs-toggle="tooltip" title="Editar notificação"
                                    onclick="openNotificationModal('edit', <?= (int)$notificacao['id'] ?>)">
                                    <i class="las la-pen fs-18"></i>
                                </button>
                                <button type="button" class="btn btn-link text-danger p-0"
                                    data-bs-toggle="tooltip" title="Excluir notificação"
                                    onclick="confirmDeleteNotification(<?= (int)$notificacao['id'] ?>)">
                                    <i class="las la-trash-alt fs-18"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNotificacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNotificacaoTitulo">Nova notificação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formNotificacao">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="notificacao_id" id="campo_notificacao_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Título</label>
                            <input type="text" class="form-control" name="titulo" id="campo_titulo" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo visual</label>
                            <select class="form-select" name="tipo_alerta" id="campo_tipo_alerta" required>
                                <?php foreach ($alertTypes as $key => $label): ?>
                                    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mensagem</label>
                            <textarea class="form-control" name="mensagem" id="campo_mensagem" rows="4" required></textarea>
                            <small class="text-muted d-block">Aceita HTML básico para links e formatação.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alcance</label>
                            <select class="form-select" name="destino_tipo" id="campo_destino_tipo" required>
                                <option value="todos">Todos os usuários</option>
                                <option value="nivel">Por nível</option>
                                <option value="usuario">Usuário específico</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="wrapDestinoNivel">
                            <label class="form-label">Níveis</label>
                            <select class="form-select" name="destino_nivel[]" id="campo_destino_nivel" multiple size="5">
                                <?php foreach ($niveisAcesso as $nivel): ?>
                                    <option value="<?= (int)$nivel['id'] ?>"><?= htmlspecialchars($nivel['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Selecione um ou mais níveis.</small>
                        </div>
                        <div class="col-md-6 d-none" id="wrapDestinoUsuario">
                            <label class="form-label">Usuários específicos</label>
                            <select class="form-select" name="destino_usuario[]" id="campo_destino_usuario" multiple size="5">
                                <?php foreach ($usuariosSistema as $usuario): ?>
                                    <option value="<?= (int)$usuario['id'] ?>">
                                        <?= htmlspecialchars($usuario['nome']) ?> (<?= htmlspecialchars($usuario['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Segure Ctrl/âŒ˜ para selecionar vários usuários.</small>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="campo_restringir_modulos" name="restricao_modulos" value="1">
                                <label class="form-check-label" for="campo_restringir_modulos">
                                    Restringir exibição a módulos específicos
                                </label>
                            </div>
                            <small class="text-muted">Quando desativado, a notificação aparece em todo o painel.</small>
                            <div class="mt-2 d-none" id="wrapModulos">
                                <label class="form-label">Selecione os módulos</label>
                                <select class="form-select" name="modulos_exibicao[]" id="campo_modulos" multiple size="6">
                                    <?php foreach ($modulosSistema as $modulo): ?>
                                        <option value="<?= htmlspecialchars($modulo['slug']) ?>">
                                            <?= htmlspecialchars($modulo['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted d-block">Escolha um ou mais módulos. Limpe a seleção para mostrar em todos.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Início</label>
                            <input type="datetime-local" class="form-control" name="vigencia_inicio" id="campo_vigencia_inicio">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fim</label>
                            <input type="datetime-local" class="form-control" name="vigencia_fim" id="campo_vigencia_fim">
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" id="campo_fixa" name="fixa" value="1">
                                <label class="form-check-label" for="campo_fixa">Notificação fixa</label>
                            </div>
                            <small class="text-muted">Quando ativa, ignora data final.</small>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" id="campo_pode_fechar" name="pode_fechar" value="1" checked>
                                <label class="form-check-label" for="campo_pode_fechar">Usuário pode fechar</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4 pt-2">
                                <input class="form-check-input" type="checkbox" id="campo_ativo" name="ativo" value="1" checked>
                                <label class="form-check-label" for="campo_ativo">Ativar imediatamente</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnSalvarNotificacao">Salvar notificação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>public/assets/libs/simple-datatables/umd/simple-datatables.js"></script>
<script>
    const controllerUrl = '<?= BASE_URL ?>app/modules/notifications/notifications_controller.php';
    const csrfToken = '<?= htmlspecialchars($csrfToken) ?>';
    const niveisDisponiveis = <?= json_encode($niveisAcesso, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
    const usuariosDisponiveis = <?= json_encode($usuariosSistema, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;

    function escapeHtml(value) {
        if (value === undefined || value === null) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }


    function parseJsonResponse(response) {
        return response.text().then(text => {
            if (!text) {
                throw new Error('Resposta vazia do servidor.');
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(text);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const tabela = new simpleDatatables.DataTable('#datatable_notifications', {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            labels: {
                placeholder: 'Pesquisar...',
                perPage: 'registros por pagina',
                noRows: 'Nenhuma notificacao encontrada',
                info: 'Mostrando {start} a {end} de {rows} registros'
            }
        });

        if (window.initTooltips) {
            window.initTooltips();
            ['datatable.page', 'datatable.sort', 'datatable.search'].forEach(evt => {
                tabela.on(evt, () => window.initTooltips && window.initTooltips());
            });
        }

        document.getElementById('formNotificacao')?.addEventListener('submit', submitNotificationForm);
        document.getElementById('campo_destino_tipo')?.addEventListener('change', updateDestinoFields);
        document.getElementById('campo_restringir_modulos')?.addEventListener('change', updateModuloFields);
        updateDestinoFields();
        updateModuloFields();
    });

    function updateDestinoFields() {
        const tipo = document.getElementById('campo_destino_tipo')?.value || 'todos';
        const nivelWrap = document.getElementById('wrapDestinoNivel');
        const usuarioWrap = document.getElementById('wrapDestinoUsuario');
        if (nivelWrap && usuarioWrap) {
            nivelWrap.classList.toggle('d-none', tipo !== 'nivel');
            usuarioWrap.classList.toggle('d-none', tipo !== 'usuario');
            if (tipo !== 'usuario') {
                setMultiSelectValues(document.getElementById('campo_destino_usuario'), []);
            }
            if (tipo !== 'nivel') {
                setMultiSelectValues(document.getElementById('campo_destino_nivel'), []);
            }
        }
    }

    function updateModuloFields() {
        const toggle = document.getElementById('campo_restringir_modulos');
        const wrap = document.getElementById('wrapModulos');
        const select = document.getElementById('campo_modulos');
        const ativo = toggle?.checked ?? false;
        if (wrap) {
            wrap.classList.toggle('d-none', !ativo);
        }
        if (!ativo) {
            setMultiSelectValues(select, []);
        }
    }

    function setMultiSelectValues(select, values) {
        if (!select) {
            return;
        }
        const set = new Set((values || []).map(val => String(val)));
        Array.from(select.options).forEach(option => {
            option.selected = set.has(option.value);
        });
    }

    function resetNotificationForm() {
        const form = document.getElementById('formNotificacao');
        form.reset();
        form.dataset.mode = 'create';
        document.getElementById('campo_notificacao_id').value = '';
        document.getElementById('campo_tipo_alerta').value = 'primary';
        document.getElementById('campo_destino_tipo').value = 'todos';
        setMultiSelectValues(document.getElementById('campo_destino_nivel'), []);
        setMultiSelectValues(document.getElementById('campo_destino_usuario'), []);
        const toggleModulo = document.getElementById('campo_restringir_modulos');
        if (toggleModulo) {
            toggleModulo.checked = false;
        }
        setMultiSelectValues(document.getElementById('campo_modulos'), []);
        document.getElementById('campo_fixa').checked = false;
        document.getElementById('campo_pode_fechar').checked = true;
        document.getElementById('campo_ativo').checked = true;
        updateDestinoFields();
        updateModuloFields();
    }

    async function openNotificationModal(mode, id = null) {
        const modal = bootstrap.Modal.getOrCreateInstance('#modalNotificacao');
        resetNotificationForm();

        const form = document.getElementById('formNotificacao');
        form.dataset.mode = mode;
        document.getElementById('modalNotificacaoTitulo').textContent = mode === 'edit'
            ? 'Editar notificação'
            : 'Nova notificação';

        if (mode === 'edit' && id) {
            try {
                const resp = await fetch(`${controllerUrl}?action=get_notification&id=${id}`, { credentials: 'same-origin' });
                const data = await parseJsonResponse(resp);
                if (!data.success) {
                    adminAlert(data.message || 'Não foi possível carregar a notificação.', 'danger');
                    return;
                }
                fillNotificationForm(data.notificacao || {});
            } catch (err) {
                adminAlert(err.message || 'Erro ao carregar dados.', 'danger');
                return;
            }
        }

        modal.show();
    }

    function fillNotificationForm(notificacao) {
        document.getElementById('campo_notificacao_id').value = notificacao.id || '';
        document.getElementById('campo_titulo').value = notificacao.titulo || '';
        document.getElementById('campo_mensagem').value = notificacao.mensagem || '';
        document.getElementById('campo_tipo_alerta').value = notificacao.tipo_alerta || 'primary';
        document.getElementById('campo_destino_tipo').value = notificacao.destino_tipo || 'todos';
        const niveisSelecionados = (notificacao.destino_tipo === 'nivel' && notificacao.destino_valor)
            ? String(notificacao.destino_valor)
                .split(',')
                .map(v => v.trim())
                .filter(Boolean)
            : [];
        setMultiSelectValues(document.getElementById('campo_destino_nivel'), niveisSelecionados);
        const usuariosSelecionados = (notificacao.destino_tipo === 'usuario' && notificacao.destino_valor)
            ? String(notificacao.destino_valor)
                .split(',')
                .map(v => v.trim())
                .filter(Boolean)
            : [];
        setMultiSelectValues(document.getElementById('campo_destino_usuario'), usuariosSelecionados);
        const modulosSelecionados = Array.isArray(notificacao.modulos)
            ? notificacao.modulos
                .map(item => {
                    if (typeof item === 'string') {
                        return item;
                    }
                    if (item && typeof item.slug === 'string') {
                        return item.slug;
                    }
                    return '';
                })
                .filter(Boolean)
            : [];
        const toggleModulo = document.getElementById('campo_restringir_modulos');
        if (toggleModulo) {
            toggleModulo.checked = modulosSelecionados.length > 0;
        }
        setMultiSelectValues(document.getElementById('campo_modulos'), modulosSelecionados);
        document.getElementById('campo_vigencia_inicio').value = formatDateInput(notificacao.vigencia_inicio);
        document.getElementById('campo_vigencia_fim').value = formatDateInput(notificacao.vigencia_fim);
        document.getElementById('campo_fixa').checked = Number(notificacao.fixa) === 1;
        document.getElementById('campo_pode_fechar').checked = Number(notificacao.pode_fechar) === 1;
        document.getElementById('campo_ativo').checked = Number(notificacao.ativo) === 1;
        updateDestinoFields();
        updateModuloFields();
    }

    function formatDateInput(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        const off = date.getTimezoneOffset();
        const localDate = new Date(date.getTime() - off * 60000);
        return localDate.toISOString().slice(0, 16);
    }

    async function submitNotificationForm(event) {
        event.preventDefault();
        const form = event.target;
        const mode = form.dataset.mode || 'create';

        const fd = new FormData(form);
        fd.append('action', 'save_notification');

        const btn = document.getElementById('btnSalvarNotificacao');
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        try {
            const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await parseJsonResponse(resp);
            if (data.success) {
                adminToast(data.message || 'Notificação salva.', 'success');
                setTimeout(() => window.location.reload(), 900);
            } else {
                adminAlert(data.message || 'Falha ao salvar.', 'danger');
            }
        } catch (err) {
            adminAlert(err.message || 'Erro inesperado.', 'danger');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar notificação';
        }
    }

    async function confirmDeleteNotification(id) {
        if (!id) { adminAlert('Notificação inválida.', 'danger'); return; }
        const ok = await adminConfirm({
            title: 'Excluir notificação?',
            text: 'Esta ação não pode ser desfeita.',
            confirmText: 'Excluir',
        });
        if (!ok) return;

        const fd = new FormData();
        fd.append('action', 'delete_notification');
        fd.append('id', id);
        fd.append('csrf_token', csrfToken);

        try {
            const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await parseJsonResponse(resp);
            if (!data.success) { adminAlert(data.message || 'Não foi possível excluir.', 'danger'); return; }
            adminToast(data.message || 'Notificação excluída.', 'success');
            setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            adminAlert(err.message || 'Erro inesperado.', 'danger');
        }
    }
</script>
