<?php
requireLogin();
require_once APP_PATH . '/core/module_header.php';
require_once APP_PATH . '/core/permissions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$canCreate = userHasPermission($_SESSION['usuario_id'], 'faq', 'pode_criar');
$canEdit   = userHasPermission($_SESSION['usuario_id'], 'faq', 'pode_editar');
$canDelete = userHasPermission($_SESSION['usuario_id'], 'faq', 'pode_excluir');
?>

<style>
/* ── FAQ Preview ──────────────────────────────────────────────────────────── */
.faq-preview-card {
    background: var(--bs-body-bg, #fff);
    border: 1px solid var(--bs-border-color);
    border-radius: 10px;
    padding: 18px 20px;
    min-height: 80px;
}
.faq-preview-card .preview-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--bs-border-color-translucent, rgba(0,0,0,.06));
}
.faq-preview-card .preview-item:last-child { border-bottom: none; }
.faq-preview-card .preview-icon {
    font-size: 18px;
    color: var(--bs-primary);
    flex-shrink: 0;
}
.faq-preview-card .preview-question {
    font-weight: 600;
    font-size: .92rem;
    color: var(--bs-body-color);
}

/* ── Tabela ───────────────────────────────────────────────────────────────── */
.faq-icon-preview {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: .88rem;
    font-weight: 500;
}
.faq-icon-preview .material-symbols-outlined {
    font-size: 20px;
    color: var(--bs-primary);
}
.faq-resposta-cell {
    max-width: 340px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--bs-secondary-color, #6c757d);
    font-size: .85rem;
}

/* ── Drag handle ──────────────────────────────────────────────────────────── */
.drag-handle {
    cursor: grab;
    color: var(--bs-secondary-color, #aaa);
    font-size: 1.1rem;
    padding: 0 4px;
    user-select: none;
}
.drag-handle:active { cursor: grabbing; }
tr.sortable-ghost { opacity: .35; background: var(--bs-primary-bg-subtle, #cfe2ff); }
tr.sortable-chosen { background: var(--bs-warning-bg-subtle, #fff3cd); }

/* ── Nota de ícones ───────────────────────────────────────────────────────── */
.icon-libs-note {
    background: rgba(var(--bs-info-rgb, 13,202,240), .08);
    border: 1px solid rgba(var(--bs-info-rgb, 13,202,240), .25);
    border-radius: 8px;
    padding: 10px 14px;
    font-size: .8rem;
    line-height: 1.6;
    color: var(--bs-body-color);
}
.icon-libs-note strong { color: var(--bs-info); }
.icon-libs-note a { color: var(--bs-info); }
.icon-libs-note code {
    background: rgba(var(--bs-info-rgb, 13,202,240), .15);
    padding: 1px 5px;
    border-radius: 4px;
    font-size: .78rem;
}
</style>

<!-- Material Symbols (necessário para pré-visualização dos ícones) -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">

<div class="row">
    <div class="col-12">

        <!-- ── Alertas ─────────────────────────────────────────────────────── -->
        <div id="alertaGlobal" class="d-none mb-3"></div>

        <!-- ── PRÉ-VISUALIZAÇÃO DO FAQ ────────────────────────────────────── -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="las la-question-circle fs-20 text-primary"></i>
                <div>
                    <h5 class="card-title mb-0">FAQ — Pré-visualização</h5>
                    <p class="text-muted mb-0 fs-12">Como as perguntas aparecem no site</p>
                </div>
                <div class="ms-auto">
                    <button class="btn btn-sm btn-outline-secondary" id="btnAtualizar" title="Atualizar">
                        <i class="las la-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="faq-preview-card" id="faqPreviewCard">
                    <div class="text-center text-muted py-3" id="previewLoading">
                        <i class="las la-spinner la-spin me-2"></i>Carregando…
                    </div>
                </div>
            </div>
        </div>

        <!-- ── FORMULÁRIO DE CADASTRO / EDIÇÃO ────────────────────────────── -->
        <?php if ($canCreate || $canEdit): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="las la-plus-circle fs-20 text-success"></i>
                <div>
                    <h5 class="card-title mb-0" id="formTitulo">Nova Pergunta</h5>
                    <p class="text-muted mb-0 fs-12">Preencha os dados e clique em Salvar</p>
                </div>
            </div>
            <div class="card-body">
                <form id="formFaq" novalidate>
                    <input type="hidden" id="fFaqId" value="0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="row g-3">

                        <!-- Ícone -->
                        <div class="col-md-4">
                            <label for="fIcone" class="form-label fw-semibold">
                                Ícone <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text" id="iconePreviewSpan">
                                    <span class="material-symbols-outlined" id="iconePreviewIcon" style="font-size:20px;color:var(--bs-primary)">help</span>
                                </span>
                                <input type="text" class="form-control" id="fIcone"
                                       placeholder="Ex: help, credit_card, policy…"
                                       maxlength="120" value="help">
                            </div>
                            <!-- Nota sobre libs compatíveis -->
                            <div class="icon-libs-note mt-2">
                                <strong><i class="las la-info-circle me-1"></i>Libs de ícones compatíveis:</strong><br>
                                • <strong>Material Symbols</strong> (padrão do site):
                                  use o nome do ícone em <code>snake_case</code>, ex: <code>help</code>, <code>credit_card</code>, <code>account_balance_wallet</code>, <code>policy</code>, <code>theaters</code>.
                                  <a href="https://fonts.google.com/icons" target="_blank" rel="noopener">Ver catálogo ↗</a><br>
                                • <strong>Line Awesome</strong> (painel admin):
                                  use a classe completa, ex: <code>las la-question-circle</code>, <code>las la-lock</code>.
                                  <a href="https://icons8.com/line-awesome" target="_blank" rel="noopener">Ver catálogo ↗</a><br>
                                • <strong>Font Awesome 6</strong> (disponível no admin):
                                  use a classe completa, ex: <code>fa-solid fa-circle-question</code>.
                                  <a href="https://fontawesome.com/icons" target="_blank" rel="noopener">Ver catálogo ↗</a>
                            </div>
                        </div>

                        <!-- Pergunta -->
                        <div class="col-md-8">
                            <label for="fPergunta" class="form-label fw-semibold">
                                Pergunta <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="fPergunta"
                                   placeholder="Ex.: Posso transferir o ingresso?" maxlength="500" required>
                            <div class="form-text"><span id="charCountPergunta">0</span>/500 caracteres</div>
                        </div>

                        <!-- Resposta -->
                        <div class="col-12">
                            <label for="fResposta" class="form-label fw-semibold">
                                Resposta <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="fResposta" rows="3"
                                      placeholder="Resposta detalhada para a pergunta…" maxlength="3000" required></textarea>
                            <div class="form-text"><span id="charCountResposta">0</span>/3000 caracteres</div>
                        </div>

                        <!-- Ativo -->
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="fAtivo" checked>
                                <label class="form-check-label fw-semibold" for="fAtivo">Exibir no site</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-3">
                        <button type="button" class="btn btn-outline-secondary d-none" id="btnCancelarEdicao">
                            <i class="las la-undo me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnSalvar">
                            <i class="las la-save me-1"></i>Salvar Pergunta
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── TABELA DE PERGUNTAS ─────────────────────────────────────────── -->
        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="las la-list fs-20 text-info"></i>
                <div>
                    <h5 class="card-title mb-0">Perguntas cadastradas</h5>
                    <p class="text-muted mb-0 fs-12" id="legendaTotal">—</p>
                </div>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <input type="search" class="form-control form-control-sm" id="filtroPergunta"
                           placeholder="Filtrar por pergunta…" style="min-width:180px">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabelaFaq">
                        <thead class="table-light">
                            <tr>
                                <?php if ($canEdit): ?>
                                <th style="width:40px" title="Arrastar para reordenar"></th>
                                <?php endif; ?>
                                <th style="width:50px" class="text-center">Ordem</th>
                                <th style="width:180px">Ícone</th>
                                <th>Pergunta</th>
                                <th class="d-none d-lg-table-cell">Resposta</th>
                                <th class="text-center" style="width:90px">Status</th>
                                <?php if ($canEdit || $canDelete): ?>
                                <th class="text-end" style="width:110px">Ações</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tbodyFaq">
                            <tr id="trCarregando">
                                <td colspan="<?= ($canEdit ? 1 : 0) + 4 + ($canEdit ? 1 : 0) + ($canEdit || $canDelete ? 1 : 0) ?>"
                                    class="text-center py-4 text-muted">
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

<!-- ── Modal confirmar exclusão ──────────────────────────────────────────────── -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalExcluirLabel">
                    <i class="las la-trash text-danger me-2"></i>Excluir Pergunta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Deseja remover a pergunta <strong id="textoExcluir"></strong> do FAQ?
                Esta ação não pode ser desfeita.
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

<!-- Sortable.js para drag-and-drop -->
<script src="<?= BASE_URL ?>public/assets/libs/sortablejs/Sortable.min.js"></script>

<script>
// ── Config ─────────────────────────────────────────────────────────────────────
const ctrlUrl   = '<?= BASE_URL ?>app/modules/faq/faq_controller.php';
const csrfToken = '<?= htmlspecialchars($csrfToken) ?>';
const canCreate = <?= $canCreate ? 'true' : 'false' ?>;
const canEdit   = <?= $canEdit   ? 'true' : 'false' ?>;
const canDelete = <?= $canDelete ? 'true' : 'false' ?>;

let todasPerguntas = [];
let idExcluir      = null;
let modalExcluir   = null;
let sortableInst   = null;

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

// ── Detectar tipo de ícone ─────────────────────────────────────────────────────
// Material Symbols: snake_case sem espaço (ex: help, credit_card)
// Line Awesome: começa com "la" + espaço (ex: las la-question)
// Font Awesome: começa com "fa" + espaço (ex: fa-solid fa-circle)
function normalizarIcone(icone) {
    let v = String(icone || 'help')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_\-\s]/g, '')
        .replace(/\s+/g, ' ');

    if (v && !v.includes(' ')) {
        const prefixes = [
            'fa-duotone', 'fa-regular', 'fa-brands', 'fa-solid',
            'fa-light', 'fa-thin', 'fas', 'far', 'fab', 'fal', 'fad',
            'las', 'lar', 'lab'
        ];
        for (const prefix of prefixes) {
            if (v.startsWith(prefix)) {
                const rest = v.slice(prefix.length);
                if (/^(fa|la)-[a-z0-9_-]+$/.test(rest)) {
                    v = `${prefix} ${rest}`;
                    break;
                }
            }
        }
    }

    return v || 'help';
}

function renderIcone(icone) {
    const v = normalizarIcone(icone);
    if (v.includes(' ')) {
        // Classe CSS (Line Awesome ou Font Awesome)
        return `<i class="${esc(v)}" style="font-size:20px"></i>`;
    }
    // Material Symbols (nome do ícone)
    return `<span class="material-symbols-outlined" style="font-size:20px;color:var(--bs-primary)">${esc(v)}</span>`;
}

// ── Carregar perguntas ─────────────────────────────────────────────────────────
async function carregarPerguntas() {
    try {
        const resp = await fetch(`${ctrlUrl}?action=listar`, { credentials: 'same-origin' });
        const data = await resp.json();
        if (!data.success) { showAlerta(data.message || 'Erro ao carregar.'); return; }
        todasPerguntas = data.perguntas || [];
        renderPreview();
        renderTabela();
    } catch(e) {
        showAlerta('Erro de comunicação com o servidor.');
    }
}

// ── Preview ────────────────────────────────────────────────────────────────────
function renderPreview() {
    const card = document.getElementById('faqPreviewCard');
    const ativas = todasPerguntas.filter(p => p.ativo == 1);

    if (ativas.length === 0) {
        card.innerHTML = `<div class="text-center text-muted py-3">
            <i class="las la-question-circle fs-24 d-block mb-1 opacity-50"></i>
            Nenhuma pergunta ativa cadastrada ainda.
        </div>`;
        return;
    }

    card.innerHTML = ativas.map(p => `
        <div class="preview-item">
            <span class="preview-icon">${renderIcone(p.icone)}</span>
            <span class="preview-question">${esc(p.pergunta)}</span>
        </div>
    `).join('');
}

// ── Tabela ─────────────────────────────────────────────────────────────────────
function renderTabela(filtro = '') {
    const tbody   = document.getElementById('tbodyFaq');
    const legenda = document.getElementById('legendaTotal');

    const filtradas = filtro
        ? todasPerguntas.filter(p => p.pergunta.toLowerCase().includes(filtro.toLowerCase()))
        : [...todasPerguntas];

    legenda.textContent = `${filtradas.length} pergunta(s) encontrada(s)`;

    if (filtradas.length === 0) {
        const cols = 4 + (canEdit ? 1 : 0) + (canEdit ? 1 : 0) + (canEdit || canDelete ? 1 : 0);
        tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-4 text-muted">
            <i class="las la-search me-2"></i>Nenhuma pergunta encontrada.
        </td></tr>`;
        if (sortableInst) { sortableInst.destroy(); sortableInst = null; }
        return;
    }

    const handleCol = canEdit ? `<td class="drag-handle text-center"><i class="las la-grip-vertical"></i></td>` : '';
    const acoesHeader = canEdit || canDelete;

    tbody.innerHTML = filtradas.map((p, idx) => {
        const statusBadge = p.ativo
            ? `<span class="badge bg-success-subtle text-success border border-success-subtle fs-11">Ativo</span>`
            : `<span class="badge bg-secondary-subtle text-secondary border fs-11">Oculto</span>`;

        const acoes = acoesHeader ? `<td class="text-end">
            ${canEdit ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="editarPergunta(${p.id})" title="Editar">
                <i class="las la-pen"></i>
            </button>` : ''}
            ${canDelete ? `<button class="btn btn-sm btn-outline-danger" onclick="confirmarExcluir(${p.id}, '${esc(p.pergunta)}')" title="Excluir">
                <i class="las la-trash"></i>
            </button>` : ''}
        </td>` : '';

        return `<tr data-id="${p.id}">
            ${handleCol}
            <td class="text-center text-muted fs-13">${p.ordem}</td>
            <td><span class="faq-icon-preview">${renderIcone(p.icone)}<code class="fs-11">${esc(normalizarIcone(p.icone))}</code></span></td>
            <td class="fw-semibold fs-14">${esc(p.pergunta)}</td>
            <td class="d-none d-lg-table-cell"><div class="faq-resposta-cell">${esc(p.resposta)}</div></td>
            <td class="text-center">${statusBadge}</td>
            ${acoes}
        </tr>`;
    }).join('');

    // Drag-and-drop (só se pode editar e sem filtro ativo)
    if (canEdit && !filtro) {
        if (sortableInst) sortableInst.destroy();
        sortableInst = new Sortable(tbody, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: async function() {
                const ids = [...tbody.querySelectorAll('tr[data-id]')].map(tr => tr.dataset.id);
                const body = new FormData();
                body.append('action',     'reordenar');
                body.append('csrf_token', csrfToken);
                body.append('ids',        JSON.stringify(ids));
                try {
                    const resp = await fetch(ctrlUrl, { method: 'POST', body, credentials: 'same-origin' });
                    const data = await resp.json();
                    if (data.success) {
                        await carregarPerguntas();
                    } else {
                        showAlerta(data.message || 'Erro ao reordenar.');
                    }
                } catch(_) {
                    showAlerta('Erro de comunicação ao reordenar.');
                }
            }
        });
    }
}

// ── Pré-visualização do ícone no formulário ────────────────────────────────────
function atualizarIconePreview() {
    const val  = normalizarIcone(document.getElementById('fIcone').value);
    const span = document.getElementById('iconePreviewIcon');
    const cont = document.getElementById('iconePreviewSpan');

    if (val.includes(' ')) {
        // Classe CSS
        cont.innerHTML = `<i class="${esc(val)}" style="font-size:20px;color:var(--bs-primary)"></i>`;
    } else {
        cont.innerHTML = `<span class="material-symbols-outlined" style="font-size:20px;color:var(--bs-primary)">${esc(val)}</span>`;
    }
}

// ── Formulário ─────────────────────────────────────────────────────────────────
function resetarForm() {
    document.getElementById('fFaqId').value      = '0';
    document.getElementById('fIcone').value      = 'help';
    document.getElementById('fPergunta').value   = '';
    document.getElementById('fResposta').value   = '';
    document.getElementById('fAtivo').checked    = true;
    document.getElementById('formTitulo').textContent = 'Nova Pergunta';
    document.getElementById('btnCancelarEdicao').classList.add('d-none');
    document.getElementById('btnSalvar').innerHTML = '<i class="las la-save me-1"></i>Salvar Pergunta';
    document.getElementById('charCountPergunta').textContent = '0';
    document.getElementById('charCountResposta').textContent = '0';
    atualizarIconePreview();
}

function editarPergunta(id) {
    const p = todasPerguntas.find(x => x.id === id);
    if (!p) return;

    document.getElementById('fFaqId').value    = p.id;
    document.getElementById('fIcone').value    = normalizarIcone(p.icone);
    document.getElementById('fPergunta').value = p.pergunta;
    document.getElementById('fResposta').value = p.resposta;
    document.getElementById('fAtivo').checked  = p.ativo == 1;
    document.getElementById('formTitulo').textContent = 'Editar Pergunta';
    document.getElementById('btnCancelarEdicao').classList.remove('d-none');
    document.getElementById('btnSalvar').innerHTML = '<i class="las la-save me-1"></i>Atualizar';
    document.getElementById('charCountPergunta').textContent = p.pergunta.length;
    document.getElementById('charCountResposta').textContent = p.resposta.length;

    atualizarIconePreview();
    document.getElementById('formFaq').closest('.card').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Submit ─────────────────────────────────────────────────────────────────────
document.getElementById('formFaq')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const id       = parseInt(document.getElementById('fFaqId').value) || 0;
    const icone    = normalizarIcone(document.getElementById('fIcone').value);
    const pergunta = document.getElementById('fPergunta').value.trim();
    const resposta = document.getElementById('fResposta').value.trim();

    if (!pergunta) { showAlerta('Informe a pergunta.'); return; }
    if (!resposta) { showAlerta('Informe a resposta.'); return; }

    const btn  = document.getElementById('btnSalvar');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="las la-spinner la-spin me-1"></i>Salvando…';

    const body = new FormData();
    body.append('action',     'salvar');
    body.append('csrf_token', csrfToken);
    body.append('id',         id);
    body.append('icone',      icone);
    body.append('pergunta',   pergunta);
    body.append('resposta',   resposta);
    if (document.getElementById('fAtivo').checked) body.append('ativo', '1');

    try {
        const resp = await fetch(ctrlUrl, { method: 'POST', body, credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) {
            showAlerta(data.message || 'Salvo com sucesso!', 'success');
            resetarForm();
            await carregarPerguntas();
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
function confirmarExcluir(id, pergunta) {
    idExcluir = id;
    const resumo = pergunta.length > 60 ? pergunta.substring(0, 60) + '…' : pergunta;
    document.getElementById('textoExcluir').textContent = `"${resumo}"`;
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
            showAlerta(data.message || 'Removida!', 'success');
            await carregarPerguntas();
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
document.getElementById('btnAtualizar')?.addEventListener('click', carregarPerguntas);

document.getElementById('fIcone')?.addEventListener('input', atualizarIconePreview);

document.getElementById('fPergunta')?.addEventListener('input', function() {
    document.getElementById('charCountPergunta').textContent = this.value.length;
});
document.getElementById('fResposta')?.addEventListener('input', function() {
    document.getElementById('charCountResposta').textContent = this.value.length;
});

document.getElementById('filtroPergunta')?.addEventListener('input', function() {
    renderTabela(this.value);
});

// ── Init ───────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    modalExcluir = new bootstrap.Modal(document.getElementById('modalExcluir'));
    atualizarIconePreview();
    carregarPerguntas();
});
</script>
