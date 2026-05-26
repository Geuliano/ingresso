<?php
requireLogin();
require_once APP_PATH . '/core/module_header.php';
require_once APP_PATH . '/core/permissions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$canCreate = userHasPermission($_SESSION['usuario_id'], 'line-up', 'pode_criar');
$canEdit   = userHasPermission($_SESSION['usuario_id'], 'line-up', 'pode_editar');
$canDelete = userHasPermission($_SESSION['usuario_id'], 'line-up', 'pode_excluir');
?>

<style>
/* ── Nuvem de Line-up ─────────────────────────────────────────────────── */
.lineup-cloud {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 10px 18px;
    padding: 32px 24px;
    min-height: 160px;
    line-height: 1.3;
}

.lineup-cloud .atração-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: default;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    transition: transform .15s, opacity .15s;
    white-space: nowrap;
}
.lineup-cloud .atração-tag:hover { transform: scale(1.06); opacity: .85; }

/* Tamanhos por nível — 1 = maior (headliner) */
.lineup-cloud .nivel-1 { font-size: 2.4rem;  color: var(--bs-primary); }
.lineup-cloud .nivel-2 { font-size: 1.65rem; color: var(--bs-info);    }
.lineup-cloud .nivel-3 { font-size: 1.15rem; color: var(--bs-success); }
.lineup-cloud .nivel-4 { font-size: 0.88rem; color: var(--bs-warning); }
.lineup-cloud .nivel-5 { font-size: 0.72rem; color: var(--bs-secondary); }

/* Separador entre atrações */
.lineup-cloud .sep {
    color: var(--bs-border-color);
    font-size: 1rem;
    font-weight: 300;
    opacity: .4;
    user-select: none;
}

/* Estado vazio */
.lineup-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--bs-secondary-color, #6c757d);
}
.lineup-empty i { font-size: 3rem; display: block; margin-bottom: .5rem; opacity: .4; }

/* ── Tabela ───────────────────────────────────────────────────────────── */
.nivel-badge {
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 20px;
}
.nb-1 { background: rgba(var(--bs-primary-rgb),.15);  color: var(--bs-primary); }
.nb-2 { background: rgba(var(--bs-info-rgb),.15);     color: var(--bs-info); }
.nb-3 { background: rgba(var(--bs-success-rgb),.15);  color: var(--bs-success); }
.nb-4 { background: rgba(var(--bs-warning-rgb),.15);  color: var(--bs-warning); }
.nb-5 { background: rgba(var(--bs-secondary-rgb),.15);color: var(--bs-secondary); }
</style>

<div class="row">
    <div class="col-12">

        <!-- ── Alertas ─────────────────────────────────────────────────────── -->
        <div id="alertaGlobal" class="d-none mb-3"></div>

        <!-- ── NUVEM DE ATRAÇÕES ──────────────────────────────────────────── -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="las la-music fs-20 text-primary"></i>
                <div>
                    <h5 class="card-title mb-0">Line-up</h5>
                    <p class="text-muted mb-0 fs-12">Nuvem de atrações — do headliner à abertura</p>
                </div>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <?php if ($canEdit): ?>
                    <button type="button" class="btn btn-sm" id="btnToggleIntro" title="Ativar/desativar seção Line-up no site">
                        <i class="las la-eye me-1" id="iconToggleIntro"></i>
                        <span id="labelToggleIntro">Line-up no site</span>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-secondary" id="btnAtualizarNuvem" title="Atualizar">
                        <i class="las la-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0" id="areaCloud">
                <div class="lineup-empty" id="cloudLoading">
                    <i class="las la-spinner la-spin"></i>
                    <span class="text-muted">Carregando atrações…</span>
                </div>
            </div>
        </div>

        <!-- ── FORMULÁRIO DE CADASTRO / EDIÇÃO ────────────────────────────── -->
        <?php if ($canCreate || $canEdit): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="las la-plus-circle fs-20 text-success"></i>
                <div>
                    <h5 class="card-title mb-0" id="formTitulo">Nova Atração</h5>
                    <p class="text-muted mb-0 fs-12">Preencha os dados e clique em Salvar</p>
                </div>
            </div>
            <div class="card-body">
                <form id="formAtracao" novalidate>
                    <input type="hidden" id="fAtracaoId" value="0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="row g-3">
                        <!-- Nome -->
                        <div class="col-md-6">
                            <label for="fNome" class="form-label fw-semibold">
                                Nome da Atração <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="fNome"
                                   placeholder="Ex.: DJ Alok, Banda XYZ…" maxlength="255" required>
                        </div>

                        <!-- Nível -->
                        <div class="col-md-3">
                            <label for="fNivel" class="form-label fw-semibold">
                                Nível / Destaque <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="fNivel" required>
                                <option value="1">1 — Headliner (maior)</option>
                                <option value="2">2 — Sub-headliner</option>
                                <option value="3" selected>3 — Suporte</option>
                                <option value="4">4 — Abertura (menor)</option>
                            </select>
                            <div class="form-text">Define o tamanho na nuvem.</div>
                        </div>

                        <!-- Ativo -->
                        <div class="col-md-3 d-flex align-items-end pb-1">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="fAtivo" checked>
                                <label class="form-check-label fw-semibold" for="fAtivo">Exibir na nuvem</label>
                            </div>
                        </div>

                        <!-- Descrição -->
                        <div class="col-12">
                            <label for="fDesc" class="form-label fw-semibold">Descrição / Observação</label>
                            <textarea class="form-control" id="fDesc" rows="2"
                                      placeholder="Gênero musical, horário previsto, link…"></textarea>
                        </div>
                    </div>

                    <!-- Pré-visualização do tamanho -->
                    <div class="mt-3 p-3 rounded border text-center" id="previewArea" style="min-height:64px; background: rgba(0,0,0,.03)">
                        <span id="previewNome" class="atração-tag nivel-3" style="font-weight:700;text-transform:uppercase;letter-spacing:.02em">
                            Nome da Atração
                        </span>
                    </div>
                    <div class="form-text text-center mb-3">Pré-visualização do tamanho na nuvem</div>

                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary d-none" id="btnCancelarEdicao">
                            <i class="las la-undo me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnSalvar">
                            <i class="las la-save me-1"></i>Salvar Atração
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── TABELA DE ATRAÇÕES ──────────────────────────────────────────── -->
        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="las la-list fs-20 text-info"></i>
                <div>
                    <h5 class="card-title mb-0">Atrações cadastradas</h5>
                    <p class="text-muted mb-0 fs-12" id="legendaTotal">—</p>
                </div>
                <div class="ms-auto">
                    <input type="search" class="form-control form-control-sm" id="filtroNome"
                           placeholder="Filtrar por nome…" style="min-width:180px">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabelaAtracoes">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th class="text-center" style="width:140px">Nível</th>
                                <th class="d-none d-md-table-cell">Descrição</th>
                                <th class="text-center" style="width:90px">Status</th>
                                <?php if ($canEdit || $canDelete): ?>
                                <th class="text-end" style="width:110px">Ações</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tbodyAtracoes">
                            <tr id="trCarregando">
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="las la-spinner la-spin me-2"></i>Carregando…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ── Modal confirmar exclusão ─────────────────────────────────────────────── -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalExcluirLabel">
                    <i class="las la-trash text-danger me-2"></i>Excluir Atração
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Deseja remover <strong id="nomeExcluir"></strong> do line-up?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmarExcluir">
                    <i class="las la-trash me-1"></i>Excluir
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Config ─────────────────────────────────────────────────────────────────────
const ctrlUrl   = '<?= BASE_URL ?>app/modules/line-up/lineup_controller.php';
const csrfToken = '<?= htmlspecialchars($csrfToken) ?>';
const canCreate = <?= $canCreate ? 'true' : 'false' ?>;
const canEdit   = <?= $canEdit   ? 'true' : 'false' ?>;
const canDelete = <?= $canDelete ? 'true' : 'false' ?>;

// Mapa de configuração por nível
const NIVEIS = {
    1: { label: 'Headliner',     cls: 'nb-1', tagCls: 'nivel-1' },
    2: { label: 'Sub-headliner', cls: 'nb-2', tagCls: 'nivel-2' },
    3: { label: 'Suporte',       cls: 'nb-3', tagCls: 'nivel-3' },
    4: { label: 'Abertura',      cls: 'nb-4', tagCls: 'nivel-4' },
};

// Estado global
let todasAtracoes = [];
let idExcluir     = null;
let modalExcluir  = null;

// ── Helpers ────────────────────────────────────────────────────────────────────
function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function showAlerta(msg, tipo = 'danger') {
    const el = document.getElementById('alertaGlobal');
    el.className = `alert alert-${tipo} alert-dismissible fade show`;
    el.innerHTML = `<i class="las la-${tipo === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${esc(msg)}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (tipo === 'success') setTimeout(() => { try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch(_){} }, 4000);
}

// ── Carregar atrações ──────────────────────────────────────────────────────────
async function carregarAtracoes() {
    try {
        const resp = await fetch(`${ctrlUrl}?action=listar`, { credentials: 'same-origin' });
        const data = await resp.json();
        if (!data.success) { showAlerta(data.message || 'Erro ao carregar.'); return; }
        todasAtracoes = data.atracoes || [];
        renderNuvem();
        renderTabela();
    } catch (e) {
        showAlerta('Erro de comunicação com o servidor.');
    }
}

// ── Nuvem ──────────────────────────────────────────────────────────────────────
function renderNuvem() {
    const area = document.getElementById('areaCloud');
    const ativas = todasAtracoes.filter(a => a.ativo == 1);

    if (ativas.length === 0) {
        area.innerHTML = `<div class="lineup-empty">
            <i class="las la-music"></i>
            <span class="text-muted fs-14">Nenhuma atração cadastrada ainda.</span>
        </div>`;
        return;
    }

    // Ordena: menor nível (headliner=1) primeiro, depois por nome
    const ordenadas = [...ativas].sort((a, b) => a.nivel - b.nivel || a.nome.localeCompare(b.nome));

    let html = '<div class="lineup-cloud">';
    ordenadas.forEach((a, i) => {
        const cfg = NIVEIS[a.nivel] || NIVEIS[3];
        const tooltip = a.descricao ? ` title="${esc(a.descricao)}"` : '';
        html += `<span class="atração-tag ${cfg.tagCls}"${tooltip}>${esc(a.nome)}</span>`;
        if (i < ordenadas.length - 1) {
            html += `<span class="sep">•</span>`;
        }
    });
    html += '</div>';
    area.innerHTML = html;
}

// ── Tabela ─────────────────────────────────────────────────────────────────────
function renderTabela(filtro = '') {
    const tbody = document.getElementById('tbodyAtracoes');
    const legenda = document.getElementById('legendaTotal');

    const filtradas = filtro
        ? todasAtracoes.filter(a => a.nome.toLowerCase().includes(filtro.toLowerCase()))
        : [...todasAtracoes];

    legenda.textContent = `${filtradas.length} atração(ões) encontrada(s)`;

    if (filtradas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">
            <i class="las la-search me-2"></i>Nenhuma atração encontrada.
        </td></tr>`;
        return;
    }

    // Ordena tabela: nível ASC, nome ASC
    const ordenadas = [...filtradas].sort((a, b) => a.nivel - b.nivel || a.nome.localeCompare(b.nome));

    tbody.innerHTML = ordenadas.map(a => {
        const cfg = NIVEIS[a.nivel] || NIVEIS[3];
        const statusBadge = a.ativo
            ? '<span class="badge bg-success-subtle text-success border border-success-subtle fs-11">Ativo</span>'
            : '<span class="badge bg-secondary-subtle text-secondary border fs-11">Oculto</span>';

        const acoes = (canEdit || canDelete) ? `
            <td class="text-end">
                ${canEdit ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="editarAtracao(${a.id})" title="Editar">
                    <i class="las la-pen"></i>
                </button>` : ''}
                ${canDelete ? `<button class="btn btn-sm btn-outline-danger" onclick="confirmarExcluir(${a.id}, '${esc(a.nome)}')" title="Excluir">
                    <i class="las la-trash"></i>
                </button>` : ''}
            </td>` : '';

        return `<tr>
            <td class="fw-semibold">${esc(a.nome)}</td>
            <td class="text-center"><span class="nivel-badge ${cfg.cls}">${esc(cfg.label)}</span></td>
            <td class="d-none d-md-table-cell text-muted fs-13">${esc(a.descricao || '—')}</td>
            <td class="text-center">${statusBadge}</td>
            ${acoes}
        </tr>`;
    }).join('');
}

// ── Pré-visualização ───────────────────────────────────────────────────────────
function atualizarPreview() {
    const nome  = document.getElementById('fNome').value.trim() || 'Nome da Atração';
    const nivel = parseInt(document.getElementById('fNivel').value) || 3;
    const cfg   = NIVEIS[nivel] || NIVEIS[3];
    const span  = document.getElementById('previewNome');
    span.className = `atração-tag ${cfg.tagCls}`;
    span.textContent = nome.toUpperCase();
}

// ── Formulário ─────────────────────────────────────────────────────────────────
function resetarForm() {
    document.getElementById('fAtracaoId').value = '0';
    document.getElementById('fNome').value      = '';
    document.getElementById('fNivel').value     = '3';
    document.getElementById('fDesc').value      = '';
    document.getElementById('fAtivo').checked   = true;
    document.getElementById('formTitulo').textContent = 'Nova Atração';
    document.getElementById('btnCancelarEdicao').classList.add('d-none');
    document.getElementById('btnSalvar').innerHTML = '<i class="las la-save me-1"></i>Salvar Atração';
    atualizarPreview();
}

function editarAtracao(id) {
    const a = todasAtracoes.find(x => x.id === id);
    if (!a) return;

    document.getElementById('fAtracaoId').value = a.id;
    document.getElementById('fNome').value      = a.nome;
    document.getElementById('fNivel').value     = a.nivel;
    document.getElementById('fDesc').value      = a.descricao || '';
    document.getElementById('fAtivo').checked   = a.ativo == 1;
    document.getElementById('formTitulo').textContent = 'Editar Atração';
    document.getElementById('btnCancelarEdicao').classList.remove('d-none');
    document.getElementById('btnSalvar').innerHTML = '<i class="las la-save me-1"></i>Atualizar';

    atualizarPreview();

    // Scroll suave até o form
    document.getElementById('formAtracao').closest('.card').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Submit ─────────────────────────────────────────────────────────────────────
document.getElementById('formAtracao')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const id   = parseInt(document.getElementById('fAtracaoId').value) || 0;
    const nome = document.getElementById('fNome').value.trim();
    if (!nome) { showAlerta('Informe o nome da atração.'); return; }

    const btn = document.getElementById('btnSalvar');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="las la-spinner la-spin me-1"></i>Salvando…';

    const body = new FormData();
    body.append('action',      'salvar');
    body.append('csrf_token',  csrfToken);
    body.append('id',          id);
    body.append('nome',        nome);
    body.append('nivel',       document.getElementById('fNivel').value);
    body.append('descricao',   document.getElementById('fDesc').value.trim());
    if (document.getElementById('fAtivo').checked) body.append('ativo', '1');

    try {
        const resp = await fetch(ctrlUrl, { method: 'POST', body, credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) {
            showAlerta(data.message || 'Salvo com sucesso!', 'success');
            resetarForm();
            await carregarAtracoes();
        } else {
            showAlerta(data.message || 'Erro ao salvar.');
        }
    } catch(_) {
        showAlerta('Erro de comunicação com o servidor.');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = orig;
    }
});

// ── Exclusão ───────────────────────────────────────────────────────────────────
function confirmarExcluir(id, nome) {
    idExcluir = id;
    document.getElementById('nomeExcluir').textContent = nome;
    modalExcluir.show();
}

document.getElementById('btnConfirmarExcluir')?.addEventListener('click', async function() {
    if (!idExcluir) return;

    this.disabled = true;
    this.innerHTML = '<i class="las la-spinner la-spin me-1"></i>Excluindo…';

    const body = new FormData();
    body.append('action',     'excluir');
    body.append('csrf_token', csrfToken);
    body.append('id',         idExcluir);

    try {
        const resp = await fetch(ctrlUrl, { method: 'POST', body, credentials: 'same-origin' });
        const data = await resp.json();
        modalExcluir.hide();
        if (data.success) {
            showAlerta(data.message || 'Removido!', 'success');
            await carregarAtracoes();
        } else {
            showAlerta(data.message || 'Erro ao excluir.');
        }
    } catch(_) {
        showAlerta('Erro de comunicação com o servidor.');
        modalExcluir.hide();
    } finally {
        this.disabled  = false;
        this.innerHTML = '<i class="las la-trash me-1"></i>Excluir';
        idExcluir = null;
    }
});

// ── Eventos auxiliares ─────────────────────────────────────────────────────────
document.getElementById('btnCancelarEdicao')?.addEventListener('click', resetarForm);
document.getElementById('btnAtualizarNuvem')?.addEventListener('click', carregarAtracoes);

document.getElementById('fNome')?.addEventListener('input',  atualizarPreview);
document.getElementById('fNivel')?.addEventListener('change', atualizarPreview);

document.getElementById('filtroNome')?.addEventListener('input', function() {
    renderTabela(this.value);
});

// ── Toggle intro ───────────────────────────────────────────────────────────────
function atualizarBtnIntro(exibir) {
    const btn   = document.getElementById('btnToggleIntro');
    const icon  = document.getElementById('iconToggleIntro');
    const label = document.getElementById('labelToggleIntro');
    if (!btn) return;
    if (exibir) {
        btn.className  = 'btn btn-sm btn-success';
        icon.className = 'las la-eye me-1';
        label.textContent = 'Line-up visível';
    } else {
        btn.className  = 'btn btn-sm btn-outline-secondary';
        icon.className = 'las la-eye-slash me-1';
        label.textContent = 'Line-up oculto';
    }
}

async function carregarEstadoIntro() {
    try {
        const resp = await fetch(`${ctrlUrl}?action=get_intro`, { credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) atualizarBtnIntro(data.exibir_intro);
    } catch(_) {}
}

document.getElementById('btnToggleIntro')?.addEventListener('click', async function () {
    this.disabled = true;
    const body = new FormData();
    body.append('action',     'toggle_intro');
    body.append('csrf_token', csrfToken);
    try {
        const resp = await fetch(ctrlUrl, { method: 'POST', body, credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) {
            atualizarBtnIntro(data.exibir_intro);
            if (window.adminToast) adminToast(data.message, 'success');
            else showAlerta(data.message, 'success');
        } else {
            showAlerta(data.message || 'Erro ao alternar.');
        }
    } catch(_) {
        showAlerta('Erro de comunicação com o servidor.');
    } finally {
        this.disabled = false;
    }
});

// ── Init ───────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    modalExcluir = new bootstrap.Modal(document.getElementById('modalExcluir'));
    atualizarPreview();
    carregarAtracoes();
    carregarEstadoIntro();
});
</script>
