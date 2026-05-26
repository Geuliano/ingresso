<?php
requireLogin();
require_once APP_PATH . '/core/module_header.php';
require_once APP_PATH . '/core/permissions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$canEdit   = userHasPermission($_SESSION['usuario_id'], 'info-evento', 'pode_editar');
?>

<!-- =====================================================================
     INFO-EVENTO — View
     ===================================================================== -->

<div class="row">
    <div class="col-12">

        <!-- Alerta global -->
        <div id="alertaGlobal" class="d-none mb-3"></div>

        <!-- ── CARD: Dados do Evento ───────────────────────────────────── -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="las la-calendar-alt fs-20 text-primary"></i>
                <div>
                    <h5 class="card-title mb-0">Dados do Evento</h5>
                    <p class="text-muted mb-0 fs-12">Nome, datas e local do evento.</p>
                </div>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <div id="badgeAtualizado"></div>
                    <?php if ($canEdit): ?>
                    <button type="button" class="btn btn-sm" id="btnToggleCard" title="Ativar/desativar card 'Sobre o evento' no site">
                        <i class="las la-eye me-1" id="iconToggleCard"></i>
                        <span id="labelToggleCard">Card no site</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" id="cardEvento">
                <div class="text-center py-5 text-muted" id="loadingEvento">
                    <i class="las la-spinner la-spin fs-24 me-2"></i>Carregando...
                </div>
            </div>
        </div>

        <!-- ── CARD: Capa do Evento (Hero Banner) ────────────────────── -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="las la-image fs-20 text-warning"></i>
                <div>
                    <h5 class="card-title mb-0">Capa do Evento</h5>
                    <p class="text-muted mb-0 fs-12">Imagem exibida no banner principal do site (hero). Recomendado: 1200×500 px · JPG, PNG ou WebP · máx. 5 MB.</p>
                </div>
            </div>
            <div class="card-body">

                <!-- Área de preview -->
                <div id="capaPreviewWrap" class="mb-3" style="display:none">
                    <div style="position:relative;display:inline-block;max-width:100%;">
                        <img id="capaPreviewImg" src="" alt="Capa do evento"
                             style="max-width:100%;max-height:280px;border-radius:8px;border:1px solid rgba(255,255,255,.1);object-fit:cover;">
                        <?php if ($canEdit): ?>
                        <button type="button" id="btnRemoverCapa"
                                style="position:absolute;top:8px;right:8px;"
                                class="btn btn-sm btn-danger"
                                title="Remover capa">
                            <i class="las la-trash me-1"></i>Remover
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Placeholder quando não há capa -->
                <div id="capaPlaceholder" class="d-flex flex-column align-items-center justify-content-center gap-2 py-4"
                     style="border:2px dashed rgba(255,255,255,.15);border-radius:10px;background:rgba(255,255,255,.02);min-height:160px;">
                    <i class="las la-image" style="font-size:48px;opacity:.3;"></i>
                    <span class="text-muted fs-13">Nenhuma capa definida</span>
                    <?php if ($canEdit): ?>
                    <span class="text-muted fs-12">Clique em "Enviar capa" para adicionar</span>
                    <?php endif; ?>
                </div>

                <?php if ($canEdit): ?>
                <!-- Input de arquivo (oculto) -->
                <input type="file" id="capaFileInput" accept="image/jpeg,image/png,image/webp" class="d-none">

                <div class="d-flex align-items-center gap-2 mt-3">
                    <button type="button" class="btn btn-outline-warning btn-sm" id="btnEnviarCapa">
                        <i class="las la-upload me-1"></i>Enviar capa
                    </button>
                    <span id="capaUploadStatus" class="text-muted fs-12"></span>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- ── CARD: Dados do Produtor ────────────────────────────────── -->
        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="las la-user-tie fs-20 text-success"></i>
                <div>
                    <h5 class="card-title mb-0">Dados do Produtor</h5>
                    <p class="text-muted mb-0 fs-12">Responsável / organizador do evento.</p>
                </div>
            </div>
            <div class="card-body" id="cardProdutor">
                <!-- preenchido via JS após o GET -->
            </div>
        </div>

        <!-- ── Ações ───────────────────────────────────────────────────── -->
        <?php if ($canEdit): ?>
        <div class="d-flex justify-content-end mt-4 gap-2">
            <button type="button" class="btn btn-outline-secondary" id="btnCancelar" style="display:none!important">
                <i class="las la-undo me-1"></i>Cancelar
            </button>
            <button type="button" class="btn btn-primary" id="btnSalvar" disabled>
                <i class="las la-save me-1"></i>Salvar informações
            </button>
        </div>
        <?php else: ?>
        <div class="alert alert-info mt-3">
            <i class="las la-info-circle me-1"></i>Você possui acesso somente leitura a este módulo.
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
// ─── Configuração ──────────────────────────────────────────────────────────────
const controllerUrl = '<?= BASE_URL ?>app/modules/info-evento/info_evento_controller.php';
const csrfToken     = '<?= htmlspecialchars($csrfToken) ?>';
const canEdit       = <?= $canEdit ? 'true' : 'false' ?>;

// Dados carregados
let dadosAtuais = null;

// ─── Helpers ───────────────────────────────────────────────────────────────────
function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function dataBR(str) {
    if (!str) return '--';
    const d = new Date(str.replace(' ','T'));
    if (isNaN(d)) return str;
    return d.toLocaleString('pt-BR', {
        day:'2-digit', month:'2-digit', year:'numeric',
        hour:'2-digit', minute:'2-digit'
    });
}

function showAlerta(msg, tipo = 'danger') {
    const el = document.getElementById('alertaGlobal');
    el.className = `alert alert-${tipo} alert-dismissible fade show`;
    el.innerHTML = `<i class="las la-${tipo==='success'?'check-circle':'exclamation-triangle'} me-2"></i>${escapeHtml(msg)}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Formata CPF enquanto digita
function formatCpfCnpj(v) {
    let d = (v || '').replace(/\D/g, '').slice(0, 14);
    if (d.length <= 11) {
        // CPF
        if (d.length <= 3)  return d;
        if (d.length <= 6)  return d.slice(0,3)+'.'+d.slice(3);
        if (d.length <= 9)  return d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);
        return d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);
    }
    // CNPJ
    if (d.length <= 2)  return d;
    if (d.length <= 5)  return d.slice(0,2)+'.'+d.slice(2);
    if (d.length <= 8)  return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5);
    if (d.length <= 12) return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8);
    return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8,12)+'-'+d.slice(12);
}

function formatTel(v) {
    let d = (v || '').replace(/\D/g, '').slice(0, 11);
    if (d.length === 0) return '';
    if (d.length <= 2) return '(' + d;
    const ddd  = d.slice(0, 2);
    const rest = d.slice(2);
    if (rest.length <= 4) return `(${ddd}) ${rest}`;
    if (rest.length <= 8) return `(${ddd}) ${rest.slice(0,4)}-${rest.slice(4)}`;
    return `(${ddd}) ${rest.slice(0,5)}-${rest.slice(5,9)}`;
}

// Lista de UFs
const UFS = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT',
             'PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];

function buildUfOptions(selected) {
    return '<option value="">-- Selecione --</option>' +
        UFS.map(uf => `<option value="${uf}" ${uf === selected ? 'selected' : ''}>${uf}</option>`).join('');
}

// ─── Carregar dados ────────────────────────────────────────────────────────────
async function carregarDados() {
    try {
        const resp = await fetch(`${controllerUrl}?action=get`, { credentials: 'same-origin' });
        const data = await resp.json();

        if (!data.success) {
            document.getElementById('cardEvento').innerHTML =
                `<p class="text-danger"><i class="las la-exclamation-triangle me-2"></i>${escapeHtml(data.message || 'Erro ao carregar.')}</p>`;
            return;
        }

        dadosAtuais = data.info;
        renderFormulario(dadosAtuais);
        atualizarBadge(dadosAtuais.updated_at);
        atualizarToggleCard(parseInt(dadosAtuais.exibir_card_info ?? 1));
        atualizarPreviewCapa(dadosAtuais.capa_evento || '');

    } catch (e) {
        document.getElementById('cardEvento').innerHTML =
            '<p class="text-danger"><i class="las la-exclamation-triangle me-2"></i>Erro de comunicação com o servidor.</p>';
    }
}

function atualizarToggleCard(exibir) {
    const btn   = document.getElementById('btnToggleCard');
    const icon  = document.getElementById('iconToggleCard');
    const label = document.getElementById('labelToggleCard');
    if (!btn) return;

    if (exibir) {
        btn.className  = 'btn btn-sm btn-success';
        icon.className = 'las la-eye me-1';
        label.textContent = 'Card visível';
    } else {
        btn.className  = 'btn btn-sm btn-outline-secondary';
        icon.className = 'las la-eye-slash me-1';
        label.textContent = 'Card oculto';
    }
}

async function toggleCard() {
    const btn = document.getElementById('btnToggleCard');
    if (!btn) return;

    btn.disabled = true;
    const fd = new FormData();
    fd.append('action',     'toggle_card');
    fd.append('csrf_token', csrfToken);

    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await resp.json();
        if (data.success) {
            atualizarToggleCard(data.exibir_card_info);
            if (window.adminToast) adminToast(data.message, 'success');
        } else {
            if (window.adminAlert) adminAlert(data.message || 'Erro ao alternar.', 'danger');
        }
    } catch (e) {
        if (window.adminAlert) adminAlert('Erro de comunicação.', 'danger');
    } finally {
        btn.disabled = false;
    }
}

function atualizarBadge(updatedAt) {
    const el = document.getElementById('badgeAtualizado');
    if (!el) return;
    if (updatedAt) {
        el.innerHTML = `<span class="badge bg-light text-muted border fs-11">
            <i class="las la-clock me-1"></i>Atualizado em ${dataBR(updatedAt)}
        </span>`;
    }
}

// ─── Renderizar formulário ─────────────────────────────────────────────────────
function renderFormulario(info) {
    const ro = !canEdit ? 'readonly disabled' : '';
    const roSel = !canEdit ? 'disabled' : '';

    // ── Card Evento ──────────────────────────────────────────────────────────
    document.getElementById('cardEvento').innerHTML = `
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-semibold">Nome do Evento <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome_evento"
                    value="${escapeHtml(info.nome_evento)}"
                    placeholder="Ex.: Festival de Música 2025"
                    maxlength="255" ${ro}>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Detalhes do Evento</label>
                <textarea class="form-control" id="detalhes_evento" rows="4"
                    placeholder="Descrição, atrações, informações adicionais sobre o evento..." ${ro}>${escapeHtml(info.detalhes_evento || '')}</textarea>
                <div class="form-text">Este texto será exibido como descrição do evento no site.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Data e Hora de Início</label>
                <input type="datetime-local" class="form-control" id="data_inicio"
                    value="${escapeHtml(info.data_inicio_input)}" ${ro}>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Data e Hora de Término</label>
                <input type="datetime-local" class="form-control" id="data_fim"
                    value="${escapeHtml(info.data_fim_input)}" ${ro}>
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted fs-12 mb-0"><i class="las la-map-marker me-1"></i>Local do Evento</p></div>

            <div class="col-md-6">
                <label class="form-label">Nome do Local</label>
                <input type="text" class="form-control" id="local_nome"
                    value="${escapeHtml(info.local_nome)}"
                    placeholder="Ex.: Arena Multiuso, Ginásio Municipal"
                    maxlength="255" ${ro}>
            </div>
            <div class="col-md-4">
                <label class="form-label">Cidade</label>
                <input type="text" class="form-control" id="local_cidade"
                    value="${escapeHtml(info.local_cidade)}"
                    placeholder="Ex.: Porto Velho"
                    maxlength="120" ${ro}>
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select class="form-select" id="local_estado" ${roSel}>
                    ${buildUfOptions(info.local_estado)}
                </select>
            </div>
        </div>`;

    // ── Card Produtor ─────────────────────────────────────────────────────────
    document.getElementById('cardProdutor').innerHTML = `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Nome / Razão Social</label>
                <input type="text" class="form-control" id="produtor_nome"
                    value="${escapeHtml(info.produtor_nome)}"
                    placeholder="Nome completo ou razão social"
                    maxlength="255" ${ro}>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">CPF / CNPJ</label>
                <input type="text" class="form-control" id="produtor_cpf_cnpj"
                    value="${escapeHtml(info.produtor_cpf_cnpj_formatado)}"
                    placeholder="000.000.000-00 ou 00.000.000/0000-00"
                    maxlength="18" inputmode="numeric" ${ro}>
            </div>
            <div class="col-12">
                <label class="form-label">Endereço</label>
                <textarea class="form-control" id="produtor_endereco" rows="2"
                    placeholder="Rua, número, bairro, cidade, CEP..." ${ro}>${escapeHtml(info.produtor_endereco || '')}</textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">E-mail</label>
                <input type="email" class="form-control" id="produtor_email"
                    value="${escapeHtml(info.produtor_email)}"
                    placeholder="contato@produtor.com.br"
                    maxlength="255" ${ro}>
            </div>
            <div class="col-md-4">
                <label class="form-label">Telefone 1</label>
                <input type="text" class="form-control" id="produtor_telefone1"
                    value="${escapeHtml(formatTel(info.produtor_telefone1))}"
                    placeholder="(69) 99999-9999"
                    maxlength="20" inputmode="tel" ${ro}>
            </div>
            <div class="col-md-4">
                <label class="form-label">Telefone 2 <small class="text-muted">(opcional)</small></label>
                <input type="text" class="form-control" id="produtor_telefone2"
                    value="${escapeHtml(formatTel(info.produtor_telefone2))}"
                    placeholder="(69) 3222-0000"
                    maxlength="20" inputmode="tel" ${ro}>
            </div>
        </div>`;

    if (canEdit) {
        // Máscaras CPF/CNPJ
        const cpfEl = document.getElementById('produtor_cpf_cnpj');
        if (cpfEl) cpfEl.addEventListener('input', () => { cpfEl.value = formatCpfCnpj(cpfEl.value); });

        // Máscaras telefone
        ['produtor_telefone1','produtor_telefone2'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', () => { el.value = formatTel(el.value); });
        });

        // Habilita botão Salvar
        document.getElementById('btnSalvar').disabled = false;
    }
}

// ─── Salvar ───────────────────────────────────────────────────────────────────
async function salvarDados() {
    if (!canEdit) return;

    const btn = document.getElementById('btnSalvar');
    btn.disabled = true;
    btn.innerHTML = '<i class="las la-spinner la-spin me-1"></i>Salvando...';

    const fd = new FormData();
    fd.append('action',      'save');
    fd.append('csrf_token',  csrfToken);

    // Evento
    fd.append('nome_evento',   document.getElementById('nome_evento')?.value   || '');
    fd.append('data_inicio',   document.getElementById('data_inicio')?.value   || '');
    fd.append('data_fim',      document.getElementById('data_fim')?.value      || '');
    fd.append('detalhes_evento', document.getElementById('detalhes_evento')?.value || '');
    fd.append('local_nome',    document.getElementById('local_nome')?.value    || '');
    fd.append('local_cidade',  document.getElementById('local_cidade')?.value  || '');
    fd.append('local_estado',  document.getElementById('local_estado')?.value  || '');

    // Produtor (envia apenas dígitos para CPF/CNPJ e telefones)
    fd.append('produtor_nome',      document.getElementById('produtor_nome')?.value      || '');
    fd.append('produtor_cpf_cnpj',  document.getElementById('produtor_cpf_cnpj')?.value  || '');
    fd.append('produtor_endereco',  document.getElementById('produtor_endereco')?.value  || '');
    fd.append('produtor_email',     document.getElementById('produtor_email')?.value     || '');
    fd.append('produtor_telefone1', document.getElementById('produtor_telefone1')?.value || '');
    fd.append('produtor_telefone2', document.getElementById('produtor_telefone2')?.value || '');

    try {
        const resp = await fetch(controllerUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        });
        const data = await resp.json();

        if (data.success) {
            if (window.adminToast) {
                adminToast(data.message || 'Salvo com sucesso!', 'success');
            } else {
                showAlerta(data.message || 'Salvo com sucesso!', 'success');
            }
            // Recarrega para atualizar badge e dados formatados
            setTimeout(carregarDados, 600);
        } else {
            if (window.adminAlert) {
                adminAlert(data.message || 'Falha ao salvar.', 'danger');
            } else {
                showAlerta(data.message || 'Falha ao salvar.', 'danger');
            }
        }
    } catch (e) {
        showAlerta('Erro de comunicação com o servidor.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="las la-save me-1"></i>Salvar informações';
    }
}

// ─── Capa do Evento (Hero Banner) ─────────────────────────────────────────────
const capaBaseUrl = '<?= BASE_URL ?>public/uploads/evento/';

function atualizarPreviewCapa(filename) {
    const wrap        = document.getElementById('capaPreviewWrap');
    const img         = document.getElementById('capaPreviewImg');
    const placeholder = document.getElementById('capaPlaceholder');

    if (filename) {
        img.src = capaBaseUrl + encodeURIComponent(filename);
        wrap.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
    } else {
        wrap.style.display = 'none';
        if (placeholder) placeholder.style.display = 'flex';
    }
}

async function uploadCapa(file) {
    const status = document.getElementById('capaUploadStatus');
    const btn    = document.getElementById('btnEnviarCapa');

    if (!file) return;

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        if (window.adminAlert) adminAlert('Formato inválido. Use JPG, PNG ou WebP.', 'danger');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        if (window.adminAlert) adminAlert('A imagem deve ter no máximo 5 MB.', 'danger');
        return;
    }

    if (btn) btn.disabled = true;
    if (status) status.textContent = 'Enviando...';

    const fd = new FormData();
    fd.append('action',     'upload_capa');
    fd.append('csrf_token', csrfToken);
    fd.append('capa',       file);

    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await resp.json();

        if (data.success) {
            atualizarPreviewCapa(data.filename);
            if (status) status.textContent = '';
            if (window.adminToast) adminToast(data.message || 'Capa enviada!', 'success');
        } else {
            if (status) status.textContent = '';
            if (window.adminAlert) adminAlert(data.message || 'Erro ao enviar a capa.', 'danger');
        }
    } catch (e) {
        if (status) status.textContent = '';
        if (window.adminAlert) adminAlert('Erro de comunicação com o servidor.', 'danger');
    } finally {
        if (btn) btn.disabled = false;
        // Limpa o input para permitir novo upload do mesmo arquivo
        const input = document.getElementById('capaFileInput');
        if (input) input.value = '';
    }
}

async function removerCapa() {
    const btn = document.getElementById('btnRemoverCapa');
    if (btn) btn.disabled = true;

    const fd = new FormData();
    fd.append('action',     'remove_capa');
    fd.append('csrf_token', csrfToken);

    try {
        const resp = await fetch(controllerUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await resp.json();

        if (data.success) {
            atualizarPreviewCapa('');
            if (window.adminToast) adminToast('Capa removida com sucesso.', 'success');
        } else {
            if (window.adminAlert) adminAlert(data.message || 'Erro ao remover a capa.', 'danger');
        }
    } catch (e) {
        if (window.adminAlert) adminAlert('Erro de comunicação com o servidor.', 'danger');
    } finally {
        if (btn) btn.disabled = false;
    }
}

// ─── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    carregarDados();

    const btnSalvar = document.getElementById('btnSalvar');
    if (btnSalvar) btnSalvar.addEventListener('click', salvarDados);

    const btnToggle = document.getElementById('btnToggleCard');
    if (btnToggle) btnToggle.addEventListener('click', toggleCard);

    // Capa
    const btnEnviar = document.getElementById('btnEnviarCapa');
    const fileInput = document.getElementById('capaFileInput');
    const btnRemover = document.getElementById('btnRemoverCapa');

    if (btnEnviar && fileInput) {
        btnEnviar.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files[0]) {
                uploadCapa(fileInput.files[0]);
            }
        });
    }

    if (btnRemover) {
        btnRemover.addEventListener('click', removerCapa);
    }
});
</script>
