<?php
requireLogin();
require_once APP_PATH . '/core/module_header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $stmt = $pdo->query("SELECT id, nome, descricao FROM niveis_acesso ORDER BY nome ASC");
    $niveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $niveis = [];
}

$csrfToken = $_SESSION['csrf_token'];
?>

<link href="<?= BASE_URL ?>public/assets/libs/simple-datatables/style.css" rel="stylesheet">

<div class="card">
    <div class="card-header">
        <div class="row align-items-center g-2">
            <div class="col">
                <h4 class="card-title mb-0">Níveis de acesso</h4>
                <p class="text-muted mb-0">Cadastre níveis e gerencie as permissões de acesso por módulo.</p>
            </div>
            <div class="col-auto">
                <button type="button" class="btn bg-primary text-white" onclick="openLevelModal('create')">
                    <i class="las la-plus me-1"></i> Novo nível
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table mb-0 table-hover" id="datatable_niveis">
                <thead class="table-dark">
                    <tr>
                        <th>Nome</th>
                        <th>Descrição</th>
                        <th class="text-end">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($niveis as $nivel): ?>
                        <tr>
                            <td><?= htmlspecialchars($nivel['nome']) ?></td>
                            <td><?= htmlspecialchars($nivel['descricao'] ?? '') ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-link text-secondary p-0 me-2"
                                    data-bs-toggle="tooltip" title="Editar nível"
                                    onclick="openLevelModal('edit', <?= (int)$nivel['id'] ?>)">
                                    <i class="las la-pen fs-18"></i>
                                </button>
                                <button type="button" class="btn btn-link text-secondary p-0 me-2"
                                    data-bs-toggle="tooltip" title="Permissões"
                                    onclick="openPermissionsModal(<?= (int)$nivel['id'] ?>, '<?= htmlspecialchars($nivel['nome'], ENT_QUOTES) ?>')">
                                    <i class="las la-user-shield fs-18"></i>
                                </button>
                                <button type="button" class="btn btn-link text-danger p-0"
                                    data-bs-toggle="tooltip" title="Excluir nível"
                                    onclick="confirmDeleteLevel(<?= (int)$nivel['id'] ?>)">
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

<!-- Modal Nível -->
<div class="modal fade" id="modalNivel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNivelTitulo">Novo nível</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formNivel">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="nivel_id" id="campo_nivel_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" id="campo_nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" id="campo_descricao" rows="3" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Salvar nível</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Permissões -->
<div class="modal fade" id="modalPermissoes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Permissões do nível
                    <span class="text-muted fs-14 d-block" id="tituloPermissoesNivel"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formPermissoesNivel">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="nivel_id" id="permissoes_nivel_id">
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle" id="tabelaModulosPermissoes">
                            <thead>
                                <tr>
                                    <th>Módulo</th>
                                    <th class="text-center">Todas</th>
                                    <th class="text-center">Visualizar</th>
                                    <th class="text-center">Criar</th>
                                    <th class="text-center">Editar</th>
                                    <th class="text-center">Excluir</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-success">Salvar permissões</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>public/assets/libs/simple-datatables/umd/simple-datatables.js"></script>
<script>
    const controllerUrl = '<?= BASE_URL ?>app/modules/permissions/permissions_controller.php';
    const csrfToken = '<?= htmlspecialchars($csrfToken) ?>';
    const permissionTypes = ['visualizar', 'criar', 'editar', 'excluir'];

    document.addEventListener('DOMContentLoaded', () => {
        const tabelaNiveis = new simpleDatatables.DataTable('#datatable_niveis', {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            perPageSelect: [5, 10, 20, 50],
            labels: {
                placeholder: 'Pesquisar...',
                perPage: 'registros por página',
                noRows: 'Nenhum nível encontrado',
                info: 'Mostrando {start} a {end} de {rows} níveis',
                previous: 'Anterior',
                next: 'Próximo',
                first: 'Primeiro',
                last: 'Último',
            }
        });

        if (window.initTooltips) {
            window.initTooltips();
            ['datatable.page', 'datatable.sort', 'datatable.search'].forEach(evt => {
                tabelaNiveis.on(evt, () => window.initTooltips && window.initTooltips());
            });
        }

        document.getElementById('formNivel')?.addEventListener('submit', submitNivelForm);
        document.getElementById('formPermissoesNivel')?.addEventListener('submit', submitPermissoesForm);
        const corpoPermissoes = document.querySelector('#tabelaModulosPermissoes tbody');
        if (corpoPermissoes) {
            corpoPermissoes.addEventListener('change', handlePermissionCheckboxChange);
        }
    });

    function resetNivelForm() {
        const form = document.getElementById('formNivel');
        form.reset();
        document.getElementById('campo_nivel_id').value = '';
    }

    function handlePermissionCheckboxChange(event) {
        const target = event.target;
        if (!target || target.type !== 'checkbox') {
            return;
        }

        const row = target.closest('tr');
        if (!row) {
            return;
        }

        if (target.dataset.perm === 'all') {
            const others = row.querySelectorAll('input[data-perm]:not([data-perm="all"])');
            others.forEach(cb => {
                cb.checked = target.checked;
            });
            return;
        }

        if (permissionTypes.includes(target.dataset.perm)) {
            const others = permissionTypes
                .map(tipo => row.querySelector(`input[data-perm="${tipo}"]`))
                .filter(Boolean);
            const allCheckbox = row.querySelector('input[data-perm="all"]');
            if (allCheckbox) {
                allCheckbox.checked = others.length && others.every(cb => cb.checked);
            }
        }
    }

    function openLevelModal(mode, id = null) {
        resetNivelForm();
        document.getElementById('modalNivelTitulo').textContent = mode === 'edit' ? 'Editar nível' : 'Novo nível';

        if (mode === 'edit' && id) {
            fetch(`${controllerUrl}?action=get_level&id=${id}`, { credentials: 'same-origin' })
                .then(resp => resp.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Não foi possível carregar o nível.');
                    }
                    const nivel = data.nivel;
                    document.getElementById('campo_nivel_id').value = nivel.id;
                    document.getElementById('campo_nome').value = nivel.nome;
                    document.getElementById('campo_descricao').value = nivel.descricao || '';
                    bootstrap.Modal.getOrCreateInstance('#modalNivel').show();
                })
                .catch(err => adminAlert(err.message || 'Erro ao carregar nível.', 'danger'));
        } else {
            bootstrap.Modal.getOrCreateInstance('#modalNivel').show();
        }
    }

    async function submitNivelForm(event) {
        event.preventDefault();
        const form = event.target;
        const fd = new FormData(form);
        fd.append('action', 'save_level');

        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        try {
            const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (data.success) {
                adminToast(data.message || 'Nível salvo.', 'success');
                setTimeout(() => window.location.reload(), 900);
            } else {
                adminAlert(data.message || 'Falha ao salvar.', 'danger');
            }
        } catch (err) {
            adminAlert(err.message || 'Erro inesperado.', 'danger');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar nível';
        }
    }

    async function confirmDeleteLevel(id) {
        if (!id) { adminAlert('Nível inválido.', 'danger'); return; }
        const ok = await adminConfirm({
            title: 'Excluir nível?',
            text: 'Esta ação não pode ser desfeita.',
            confirmText: 'Excluir',
        });
        if (!ok) return;

        const fd = new FormData();
        fd.append('action', 'delete_level');
        fd.append('id', id);
        fd.append('csrf_token', csrfToken);

        try {
            const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (!data.success) { adminAlert(data.message || 'Não foi possível excluir.', 'danger'); return; }
            adminToast(data.message || 'Nível excluído.', 'success');
            setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            adminAlert(err.message || 'Erro inesperado.', 'danger');
        }
    }

    async function openPermissionsModal(nivelId, nomeNivel) {
        document.getElementById('permissoes_nivel_id').value = nivelId;
        document.getElementById('tituloPermissoesNivel').textContent = nomeNivel;
        document.querySelector('#tabelaModulosPermissoes tbody').innerHTML = '<tr><td colspan="6">Carregando...</td></tr>';

        const modal = bootstrap.Modal.getOrCreateInstance('#modalPermissoes');
        modal.show();

        try {
            const resp = await fetch(`${controllerUrl}?action=get_level_permissions&id=${nivelId}`, { credentials: 'same-origin' });
            const data = await resp.json();
            if (!data.success) {
                document.querySelector('#tabelaModulosPermissoes tbody').innerHTML = '<tr><td colspan="6">Erro ao carregar permissões.</td></tr>';
                return;
            }
            renderPermissionsRows(data.modulos || []);
        } catch (err) {
            document.querySelector('#tabelaModulosPermissoes tbody').innerHTML = `<tr><td colspan="6">${err.message}</td></tr>`;
        }
    }

    function renderPermissionsRows(modulos) {
        const tbody = document.querySelector('#tabelaModulosPermissoes tbody');
        tbody.innerHTML = '';

        if (!Array.isArray(modulos) || !modulos.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhum módulo encontrado.</td></tr>';
            return;
        }

        modulos.forEach(modulo => {
            const tr = document.createElement('tr');
            tr.dataset.id = modulo.id;
            const allChecked = permissionTypes.every(tipo => modulo['pode_' + tipo]);
            tr.innerHTML = `
                <td>${modulo.nome}</td>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input" data-perm="all" ${allChecked ? 'checked' : ''}>
                </td>
                ${permissionTypes.map(tipo => `
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input" data-perm="${tipo}" ${modulo['pode_' + tipo] ? 'checked' : ''}>
                    </td>
                `).join('')}
            `;
            tbody.appendChild(tr);
        });
    }

    async function submitPermissoesForm(event) {
        event.preventDefault();
        const form = event.target;
        const linhas = Array.from(document.querySelectorAll('#tabelaModulosPermissoes tbody tr'));
        const payload = linhas
            .filter(linha => linha.dataset.id)
            .map(linha => {
                const flag = tipo => linha.querySelector(`input[data-perm="${tipo}"]`)?.checked ? 1 : 0;
                return {
                    modulo_id: parseInt(linha.dataset.id, 10),
                    pode_visualizar: flag('visualizar'),
                    pode_criar: flag('criar'),
                    pode_editar: flag('editar'),
                    pode_excluir: flag('excluir'),
                };
            });

        const fd = new FormData(form);
        fd.append('action', 'save_level_permissions');
        fd.append('permissions', JSON.stringify(payload));

        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        try {
            const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await resp.json();
            if (data.success) {
                adminToast(data.message || 'Permissões atualizadas.', 'success');
                setTimeout(() => window.location.reload(), 900);
            } else {
                adminAlert(data.message || 'Falha ao salvar permissões.', 'danger');
            }
        } catch (err) {
            adminAlert(err.message || 'Erro inesperado.', 'danger');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar permissões';
        }
    }
</script>
