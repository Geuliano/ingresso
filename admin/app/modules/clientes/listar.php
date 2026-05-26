<?php
requireLogin();
require_once APP_PATH . '/core/module_header.php';
require_once APP_PATH . '/core/permissions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Auto-migration silenciosa
foreach (['blocked_at' => 'DATETIME NULL', 'admin_notes' => 'TEXT NULL', 'deleted_at' => 'DATETIME NULL'] as $col => $def) {
    try { $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$def} DEFAULT NULL"); } catch (Throwable $e) {}
}

$csrfToken = $_SESSION['csrf_token'];
$canEdit   = userHasPermission($_SESSION['usuario_id'], 'clientes', 'pode_editar');
$canDelete = userHasPermission($_SESSION['usuario_id'], 'clientes', 'pode_excluir');
?>
<link href="<?= BASE_URL ?>public/assets/libs/simple-datatables/style.css" rel="stylesheet">

<!-- Cards de estatísticas -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-22 fw-bold text-primary" id="stat_total">--</div>
                <div class="fs-12 text-muted">Total</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-22 fw-bold text-success" id="stat_ativos">--</div>
                <div class="fs-12 text-muted">Ativos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-22 fw-bold text-info" id="stat_verificados">--</div>
                <div class="fs-12 text-muted">Verificados</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-22 fw-bold text-warning" id="stat_nao_verificados">--</div>
                <div class="fs-12 text-muted">Não verificados</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-22 fw-bold text-danger" id="stat_bloqueados">--</div>
                <div class="fs-12 text-muted">Bloqueados</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-22 fw-bold text-secondary" id="stat_novos_30d">--</div>
                <div class="fs-12 text-muted">Novos (30d)</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabela principal -->
<div class="card">
    <div class="card-header">
        <div class="row align-items-center g-2">
            <div class="col">
                <h4 class="card-title mb-0">Clientes</h4>
                <p class="text-muted mb-0 fs-13">Usuários cadastrados no site principal.</p>
            </div>
            <div class="col-auto d-flex gap-2 flex-wrap align-items-center">
                <select class="form-select form-select-sm" id="filtroStatus" style="min-width:160px">
                    <option value="">Todos</option>
                    <option value="verificado">Verificados</option>
                    <option value="nao_verificado">Não verificados</option>
                    <option value="bloqueado">Bloqueados</option>
                </select>
                <div class="form-check form-switch mb-0 ms-1">
                    <input class="form-check-input" type="checkbox" id="toggleExcluidos">
                    <label class="form-check-label fs-12 text-muted" for="toggleExcluidos">Ver excluídos</label>
                </div>
                <button class="btn btn-outline-secondary btn-sm" onclick="recarregarTabela()" title="Recarregar">
                    <i class="las la-sync-alt"></i>
                </button>
                <button class="btn btn-outline-success btn-sm" onclick="exportarCSV()">
                    <i class="las la-file-csv me-1"></i>CSV
                </button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <!-- tableContainer: innerHTML é reconstruído a cada reload para evitar conflito com simpleDatatables -->
        <div id="tableContainer">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabelaClientes">
                    <thead class="table-dark">
                        <tr>
                            <th>Cliente</th>
                            <th>CPF</th>
                            <th>WhatsApp</th>
                            <th>Canal</th>
                            <th>Status</th>
                            <th>E-mail</th>
                            <th>Cadastro</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="8" class="text-center py-4 text-muted">
                            <i class="las la-spinner la-spin me-2"></i>Carregando...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal detalhes / edição -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clienteModalTitle">Detalhes do Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalClienteBody">
                <div class="text-center py-5"><i class="las la-spinner la-spin fs-24"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal atividade -->
<div class="modal fade" id="modalAtividade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Atividade do Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalAtividadeBody">
                <div class="text-center py-5"><i class="las la-spinner la-spin fs-24"></i></div>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>public/assets/libs/simple-datatables/umd/simple-datatables.js"></script>
<script>
const controllerUrl = '<?= BASE_URL ?>app/modules/clientes/clientes_controller.php';
const csrfToken     = '<?= htmlspecialchars($csrfToken) ?>';
const canEdit       = <?= $canEdit   ? 'true' : 'false' ?>;
const canDelete     = <?= $canDelete ? 'true' : 'false' ?>;

let todosClientes = [];
let dtClientes    = null;

// ─── Template da tabela ───────────────────────────────────────────────────────
// Reconstruído no tableContainer a cada reload para evitar que o simpleDatatables
// corrompa o tbody e cause "tbody is null" nas chamadas seguintes.
const TABLE_HTML = `
<div class="table-responsive">
    <table class="table table-hover mb-0" id="tabelaClientes">
        <thead class="table-dark">
            <tr>
                <th>Cliente</th><th>CPF</th><th>WhatsApp</th><th>Canal</th>
                <th>Status</th><th>E-mail</th><th>Cadastro</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>`;

function getContainer() { return document.getElementById('tableContainer'); }
function getTbody()     { return document.querySelector('#tabelaClientes tbody'); }

function resetContainer(loadingMsg) {
    if (dtClientes) { try { dtClientes.destroy(); } catch(e){} dtClientes = null; }
    getContainer().innerHTML = TABLE_HTML;
    if (loadingMsg) getTbody().innerHTML = loadingMsg;
}

function initDT() {
    try {
        dtClientes = new simpleDatatables.DataTable('#tabelaClientes', {
            searchable: true,
            fixedHeight: false,
            perPage: 15,
            perPageSelect: [10, 15, 25, 50, 100],
            labels: {
                placeholder: 'Buscar cliente...',
                perPage: 'por página',
                noRows: 'Nenhum resultado.',
                info: '{start}–{end} de {rows}',
                previous: '‹',
                next: '›',
            }
        });
    } catch(e) {}
    if (window.initTooltips) window.initTooltips();
}

// ─── Formatos ────────────────────────────────────────────────────────────────
function dateBR(str) {
    if (!str) return '--';
    const d = new Date(str.replace(' ', 'T'));
    return isNaN(d) ? str : d.toLocaleString('pt-BR', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
}

function badgeStatus(c) {
    if (c.excluido)  return '<span class="badge bg-secondary">Excluído</span>';
    if (c.bloqueado) return '<span class="badge bg-danger">Bloqueado</span>';
    return '<span class="badge bg-success">Ativo</span>';
}

function badgeEmail(c) {
    return c.email_verificado
        ? '<span class="badge bg-info text-dark">Verificado</span>'
        : '<span class="badge bg-warning text-dark">Pendente</span>';
}

function iconCanal(canal) {
    return canal === 'whatsapp'
        ? '<i class="lab la-whatsapp text-success me-1"></i>WhatsApp'
        : '<i class="las la-envelope text-primary me-1"></i>E-mail';
}

function escapeHtml(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ─── Carregamento ─────────────────────────────────────────────────────────────
async function recarregarTabela() {
    const status        = document.getElementById('filtroStatus').value;
    const inclExcluidos = document.getElementById('toggleExcluidos').checked ? 1 : 0;

    // Destrói DT e reconstrói o DOM antes de qualquer fetch
    resetContainer('<tr><td colspan="8" class="text-center py-4 text-muted"><i class="las la-spinner la-spin me-2"></i>Carregando...</td></tr>');

    let url = `${controllerUrl}?action=list&incluir_excluidos=${inclExcluidos}`;
    if (status) url += `&status=${encodeURIComponent(status)}`;

    try {
        const resp = await fetch(url, { credentials: 'same-origin' });
        const data = await resp.json();

        if (!data.success) {
            getTbody().innerHTML = `<tr><td colspan="8" class="text-center text-danger py-3">${data.message || 'Erro ao carregar.'}</td></tr>`;
            return;
        }

        todosClientes = data.clientes || [];
        atualizarStats(data.stats || {});
        renderizarTabela(todosClientes);

    } catch (e) {
        getTbody().innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Erro de comunicação com o servidor.</td></tr>';
    }
}

function atualizarStats(s) {
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v ?? '--'; };
    set('stat_total',           s.total);
    set('stat_ativos',          s.ativos);
    set('stat_verificados',     s.verificados);
    set('stat_nao_verificados', s.nao_verificados);
    set('stat_bloqueados',      s.bloqueados);
    set('stat_novos_30d',       s.novos_30d);
}

function renderizarTabela(clientes) {
    // getTbody() sempre funciona aqui pois resetContainer() já reconstruiu o DOM
    const tbody = getTbody();

    if (!clientes.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Nenhum cliente encontrado.</td></tr>';
        initDT();
        return;
    }

    tbody.innerHTML = clientes.map(c => {
        const rowClass = c.excluido ? 'table-secondary opacity-50' : (c.bloqueado ? 'table-danger' : '');

        const btns = [];
        btns.push(`<button class="btn btn-link text-secondary p-0 me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Ver detalhes" onclick="abrirDetalhes(${c.id})"><i class="las la-eye fs-18"></i></button>`);

        if (canEdit && !c.excluido) {
            btns.push(`<button class="btn btn-link text-primary p-0 me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Editar" onclick="abrirEdicao(${c.id})"><i class="las la-pen fs-18"></i></button>`);
            const iconBloquear   = c.bloqueado ? `<i class="las la-unlock fs-18"></i>` : `<i class="las la-ban fs-18"></i>`;
            const tituloBloquear = c.bloqueado ? 'Desbloquear' : 'Bloquear';
            const corBloquear    = c.bloqueado ? 'text-success' : 'text-warning';
            btns.push(`<button class="btn btn-link ${corBloquear} p-0 me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="${tituloBloquear}" onclick="alternarBloqueio(${c.id},'${escapeHtml(c.name)}',${c.bloqueado})">${iconBloquear}</button>`);
        }

        if (canDelete) {
            if (c.excluido) {
                btns.push(`<button class="btn btn-link text-success p-0" data-bs-toggle="tooltip" data-bs-placement="top" title="Restaurar" onclick="restaurarCliente(${c.id},'${escapeHtml(c.name)}')"><i class="las la-undo fs-18"></i></button>`);
            } else {
                btns.push(`<button class="btn btn-link text-danger p-0" data-bs-toggle="tooltip" data-bs-placement="top" title="Excluir" onclick="excluirCliente(${c.id},'${escapeHtml(c.name)}')"><i class="las la-trash-alt fs-18"></i></button>`);
            }
        }

        return `<tr class="${rowClass}">
            <td>
                <div class="d-flex align-items-start flex-column">
                    <span class="fw-semibold">${escapeHtml(c.name)}</span>
                    <a href="mailto:${escapeHtml(c.email)}" class="fs-12 text-muted text-decoration-none">${escapeHtml(c.email)}</a>
                </div>
            </td>
            <td class="fs-13">${c.cpf_formatado || '--'}</td>
            <td class="fs-13">${c.whatsapp_formatado || '--'}</td>
            <td class="fs-13">${iconCanal(c.verification_channel)}</td>
            <td>${badgeStatus(c)}</td>
            <td>${badgeEmail(c)}</td>
            <td class="fs-12 text-muted">${dateBR(c.created_at)}</td>
            <td class="text-end text-nowrap">${btns.join('')}</td>
        </tr>`;
    }).join('');

    initDT();
}

// ─── Modal detalhes ───────────────────────────────────────────────────────────
async function abrirDetalhes(id) {
    const modal = bootstrap.Modal.getOrCreateInstance('#modalCliente');
    document.getElementById('clienteModalTitle').textContent = 'Detalhes do Cliente';
    document.getElementById('modalClienteBody').innerHTML = '<div class="text-center py-5"><i class="las la-spinner la-spin fs-24"></i></div>';
    modal.show();

    try {
        const resp = await fetch(`${controllerUrl}?action=get&id=${id}`, { credentials: 'same-origin' });
        const data = await resp.json();
        if (!data.success) {
            document.getElementById('modalClienteBody').innerHTML = `<p class="text-danger">${data.message}</p>`;
            return;
        }
        renderModalDetalhes(data.cliente);
    } catch(e) {
        document.getElementById('modalClienteBody').innerHTML = '<p class="text-danger">Erro ao carregar.</p>';
    }
}

function renderModalDetalhes(c) {
    const bloqBtn = canEdit && !c.excluido ? `
        <button class="btn btn-sm ${c.bloqueado ? 'btn-outline-success' : 'btn-outline-warning'} me-2"
            onclick="alternarBloqueio(${c.id},'${escapeHtml(c.name)}',${c.bloqueado})">
            <i class="las ${c.bloqueado ? 'la-unlock' : 'la-ban'} me-1"></i>${c.bloqueado ? 'Desbloquear' : 'Bloquear'}
        </button>` : '';

    const verBtn = canEdit && !c.email_verificado && !c.excluido ? `
        <button class="btn btn-sm btn-outline-info me-2" onclick="verificarEmail(${c.id},'${escapeHtml(c.name)}')">
            <i class="las la-check-circle me-1"></i>Verificar e-mail
        </button>` : '';

    const revBtn = canEdit ? `
        <button class="btn btn-sm btn-outline-secondary me-2" onclick="revogarSessoes(${c.id},'${escapeHtml(c.name)}')">
            <i class="las la-sign-out-alt me-1"></i>Revogar sessões
        </button>` : '';

    const actBtn = `<button class="btn btn-sm btn-outline-dark" onclick="abrirAtividade(${c.id})">
        <i class="las la-history me-1"></i>Atividade
    </button>`;

    document.getElementById('modalClienteBody').innerHTML = `
        <div class="row g-3">
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="text-muted fw-normal" style="width:40%">ID</th><td>#${c.id}</td></tr>
                    <tr><th class="text-muted fw-normal">Nome</th><td>${escapeHtml(c.name)}</td></tr>
                    <tr><th class="text-muted fw-normal">E-mail</th><td><a href="mailto:${escapeHtml(c.email)}">${escapeHtml(c.email)}</a></td></tr>
                    <tr><th class="text-muted fw-normal">CPF</th><td>${c.cpf_formatado || '--'}</td></tr>
                    <tr><th class="text-muted fw-normal">WhatsApp</th><td>${c.whatsapp_formatado || '--'}</td></tr>
                    <tr><th class="text-muted fw-normal">Canal</th><td>${c.verification_channel}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="text-muted fw-normal" style="width:40%">Status</th><td>${badgeStatus(c)}</td></tr>
                    <tr><th class="text-muted fw-normal">E-mail</th><td>${badgeEmail(c)}</td></tr>
                    <tr><th class="text-muted fw-normal">Verificado em</th><td>${dateBR(c.email_verified_at)}</td></tr>
                    <tr><th class="text-muted fw-normal">Sessões ativas</th><td><span class="badge bg-secondary">${c.sessoes_ativas}</span></td></tr>
                    <tr><th class="text-muted fw-normal">Bloqueado em</th><td>${c.bloqueado ? dateBR(c.blocked_at) : '--'}</td></tr>
                    <tr><th class="text-muted fw-normal">Cadastro</th><td>${dateBR(c.created_at)}</td></tr>
                </table>
            </div>
            ${c.admin_notes ? `<div class="col-12"><div class="alert alert-warning mb-0 py-2"><strong>Notas:</strong> ${escapeHtml(c.admin_notes)}</div></div>` : ''}
        </div>
        <div class="mt-3 d-flex flex-wrap gap-2">${bloqBtn}${verBtn}${revBtn}${actBtn}</div>`;
}

// ─── Modal edição ─────────────────────────────────────────────────────────────
async function abrirEdicao(id) {
    const modal = bootstrap.Modal.getOrCreateInstance('#modalCliente');
    document.getElementById('clienteModalTitle').textContent = 'Editar Cliente';
    document.getElementById('modalClienteBody').innerHTML = '<div class="text-center py-5"><i class="las la-spinner la-spin fs-24"></i></div>';
    modal.show();

    try {
        const resp = await fetch(`${controllerUrl}?action=get&id=${id}`, { credentials: 'same-origin' });
        const data = await resp.json();
        if (!data.success) {
            document.getElementById('modalClienteBody').innerHTML = `<p class="text-danger">${data.message}</p>`;
            return;
        }
        renderFormEdicao(data.cliente);
    } catch(e) {
        document.getElementById('modalClienteBody').innerHTML = '<p class="text-danger">Erro ao carregar.</p>';
    }
}

function formatCPF(v) {
    const d = (v||'').replace(/\D/g,'').slice(0,11);
    if (d.length <= 3) return d;
    if (d.length <= 6) return d.slice(0,3)+'.'+d.slice(3);
    if (d.length <= 9) return d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);
    return d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);
}

function formatWhatsLocal(v) {
    if (!v) return '';
    let d = v.replace(/\D/g,'');
    if (d.startsWith('55') && d.length > 11) d = d.slice(2);
    d = d.slice(0,11);
    const ddd = d.slice(0,2), rest = d.slice(2);
    if (!ddd) return '';
    if (rest.length <= 4) return `(${ddd}) ${rest}`;
    if (rest.length <= 8) return `(${ddd}) ${rest.slice(0,4)}-${rest.slice(4)}`;
    return `(${ddd}) ${rest.slice(0,5)}-${rest.slice(5,9)}`;
}

function renderFormEdicao(c) {
    document.getElementById('modalClienteBody').innerHTML = `
        <form id="formEditarCliente" novalidate>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}">
            <input type="hidden" name="id" value="${c.id}">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome completo</label>
                    <input type="text" class="form-control" name="name" value="${escapeHtml(c.name)}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail</label>
                    <input type="email" class="form-control" name="email" value="${escapeHtml(c.email)}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">CPF</label>
                    <input type="text" class="form-control" name="cpf" id="campoCpf"
                        value="${c.cpf ? formatCPF(c.cpf) : ''}" placeholder="000.000.000-00" maxlength="14" inputmode="numeric">
                </div>
                <div class="col-md-5">
                    <label class="form-label">WhatsApp</label>
                    <div class="input-group">
                        <span class="input-group-text">+55</span>
                        <input type="text" class="form-control" name="whatsapp_number" id="campoWhatsapp"
                            value="${formatWhatsLocal(c.whatsapp_number)}" placeholder="(11) 99999-9999" inputmode="tel">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Canal preferido</label>
                    <select class="form-select" name="verification_channel">
                        <option value="email"    ${c.verification_channel==='email'    ? 'selected':''}>E-mail</option>
                        <option value="whatsapp" ${c.verification_channel==='whatsapp' ? 'selected':''}>WhatsApp</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Notas internas <small class="text-muted">(visível apenas no painel)</small></label>
                    <textarea class="form-control" name="admin_notes" rows="2"
                        placeholder="Observações sobre este cliente...">${escapeHtml(c.admin_notes||'')}</textarea>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvarCliente">
                    <i class="las la-save me-1"></i>Salvar alterações
                </button>
            </div>
        </form>`;

    const cpfEl = document.getElementById('campoCpf');
    if (cpfEl) cpfEl.addEventListener('input', () => { cpfEl.value = formatCPF(cpfEl.value); });

    const watEl = document.getElementById('campoWhatsapp');
    if (watEl) { watEl.value = formatWhatsLocal(watEl.value); watEl.addEventListener('input', () => { watEl.value = formatWhatsLocal(watEl.value); }); }

    document.getElementById('formEditarCliente').addEventListener('submit', salvarCliente);
}

async function salvarCliente(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarCliente');
    btn.disabled = true; btn.textContent = 'Salvando...';

    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: new FormData(e.target), credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) {
            adminToast(data.message || 'Salvo.', 'success');
            bootstrap.Modal.getInstance('#modalCliente')?.hide();
            setTimeout(recarregarTabela, 600);
        } else {
            adminAlert(data.message || 'Falha ao salvar.', 'danger');
        }
    } catch(err) {
        adminAlert('Erro de comunicação.', 'danger');
    } finally {
        btn.disabled = false; btn.textContent = 'Salvar alterações';
    }
}

// ─── Ações ────────────────────────────────────────────────────────────────────
async function verificarEmail(id, nome) {
    const ok = await adminConfirm({ title: 'Verificar e-mail?', text: `Marcar o e-mail de "${nome}" como verificado manualmente?`, confirmText: 'Verificar' });
    if (!ok) return;
    const fd = new FormData();
    fd.append('action', 'verify_email'); fd.append('id', id); fd.append('csrf_token', csrfToken);
    const data = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
    adminToast(data.message, data.success ? 'success' : 'danger');
    if (data.success) { bootstrap.Modal.getInstance('#modalCliente')?.hide(); setTimeout(recarregarTabela, 600); }
}

async function alternarBloqueio(id, nome, bloqueado) {
    const acao  = bloqueado ? 'Desbloquear' : 'Bloquear';
    const texto = bloqueado ? `Desbloquear o cliente "${nome}"?` : `Bloquear "${nome}"? As sessões ativas serão revogadas.`;
    const ok = await adminConfirm({ title: `${acao} cliente?`, text: texto, confirmText: acao });
    if (!ok) return;
    const fd = new FormData();
    fd.append('action', 'toggle_block'); fd.append('id', id); fd.append('csrf_token', csrfToken);
    const data = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
    adminToast(data.message, data.success ? 'success' : 'danger');
    if (data.success) { bootstrap.Modal.getInstance('#modalCliente')?.hide(); setTimeout(recarregarTabela, 600); }
}

async function revogarSessoes(id, nome) {
    const ok = await adminConfirm({ title: 'Revogar sessões?', text: `Remover todos os tokens de "${nome}"?`, confirmText: 'Revogar' });
    if (!ok) return;
    const fd = new FormData();
    fd.append('action', 'revoke_sessions'); fd.append('id', id); fd.append('csrf_token', csrfToken);
    const data = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
    adminToast(data.message, data.success ? 'success' : 'danger');
}

async function excluirCliente(id, nome) {
    const ok = await adminConfirm({ title: 'Excluir cliente?', text: `"${nome}" será marcado como excluído (soft-delete). Pode ser restaurado depois.`, confirmText: 'Excluir' });
    if (!ok) return;
    const fd = new FormData();
    fd.append('action', 'delete'); fd.append('id', id); fd.append('csrf_token', csrfToken);
    const data = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
    adminToast(data.message, data.success ? 'success' : 'danger');
    if (data.success) setTimeout(recarregarTabela, 600);
}

async function restaurarCliente(id, nome) {
    const ok = await adminConfirm({ title: 'Restaurar cliente?', text: `Restaurar "${nome}"?`, confirmText: 'Restaurar' });
    if (!ok) return;
    const fd = new FormData();
    fd.append('action', 'restore'); fd.append('id', id); fd.append('csrf_token', csrfToken);
    const data = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
    adminToast(data.message, data.success ? 'success' : 'danger');
    if (data.success) setTimeout(recarregarTabela, 600);
}

// ─── Atividade ────────────────────────────────────────────────────────────────
async function abrirAtividade(id) {
    bootstrap.Modal.getInstance('#modalCliente')?.hide();
    const modal = bootstrap.Modal.getOrCreateInstance('#modalAtividade');
    document.getElementById('modalAtividadeBody').innerHTML = '<div class="text-center py-5"><i class="las la-spinner la-spin fs-24"></i></div>';
    modal.show();

    try {
        const resp = await fetch(`${controllerUrl}?action=get_activity&id=${id}`, { credentials: 'same-origin' });
        const data = await resp.json();
        if (!data.success) { document.getElementById('modalAtividadeBody').innerHTML = `<p class="text-danger">${data.message}</p>`; return; }
        renderAtividade(data);
    } catch(e) {
        document.getElementById('modalAtividadeBody').innerHTML = '<p class="text-danger">Erro ao carregar atividade.</p>';
    }
}

function renderAtividade(data) {
    const codigos = data.codigos || [];
    const tokens  = data.tokens  || [];

    const rowsCod = codigos.length
        ? codigos.map(r => `<tr>
            <td><span class="badge bg-${r.purpose==='login'?'primary':'success'}">${r.purpose}</span></td>
            <td>${dateBR(r.created_at)}</td>
            <td>${dateBR(r.expires_at)}</td>
            <td>${r.consumed_at ? '<span class="badge bg-success">Usado</span>' : '<span class="badge bg-secondary">Não usado</span>'}</td>
            <td>${r.attempts_left}</td>
            <td class="text-muted fs-12">${escapeHtml(r.created_ip||'--')}</td>
          </tr>`).join('')
        : '<tr><td colspan="6" class="text-center text-muted">Nenhum código encontrado.</td></tr>';

    const rowsTok = tokens.length
        ? tokens.map(r => `<tr>
            <td class="font-monospace fs-12">${r.selector}</td>
            <td>${dateBR(r.created_at)}</td>
            <td>${dateBR(r.expires_at)}</td>
            <td class="text-muted fs-12">${escapeHtml(r.created_ip||'--')}</td>
          </tr>`).join('')
        : '<tr><td colspan="4" class="text-center text-muted">Nenhum token ativo.</td></tr>';

    document.getElementById('modalAtividadeBody').innerHTML = `
        <h6 class="fw-semibold mb-2">Códigos de verificação (últimos 20)</h6>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr><th>Finalidade</th><th>Criado</th><th>Expira</th><th>Status</th><th>Tentativas</th><th>IP</th></tr>
                </thead>
                <tbody>${rowsCod}</tbody>
            </table>
        </div>
        <h6 class="fw-semibold mb-2">Tokens "lembre-me"</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr><th>Selector</th><th>Criado</th><th>Expira</th><th>IP</th></tr>
                </thead>
                <tbody>${rowsTok}</tbody>
            </table>
        </div>`;
}

// ─── Exportar CSV ─────────────────────────────────────────────────────────────
function exportarCSV() {
    const incl = document.getElementById('toggleExcluidos').checked ? 1 : 0;
    window.location.href = `${controllerUrl}?action=export_csv&incluir_excluidos=${incl}`;
}

// ─── Inicialização ────────────────────────────────────────────────────────────
document.getElementById('filtroStatus').addEventListener('change', recarregarTabela);
document.getElementById('toggleExcluidos').addEventListener('change', recarregarTabela);
document.addEventListener('DOMContentLoaded', recarregarTabela);
</script>
