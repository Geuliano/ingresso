/**
 * confirmacao.js — Página de confirmação de pedido.
 *
 * Carrega o status do pedido via /api/pedido/status.php
 * e exibe o estado correto (aprovado, PIX pendente, em análise, rejeitado).
 */

const BASE_PATH   = (document.querySelector('meta[name="base-path"]')?.content ?? '').replace(/\/$/, '');
const PEDIDO_UID  = document.querySelector('meta[name="pedido-uid"]')?.content ?? '';

const titleEl     = document.getElementById('confirm-title');
const subtitleEl  = document.getElementById('confirm-subtitle');

function show(id) {
  ['confirm-loading', 'confirm-approved', 'confirm-pix', 'confirm-processing', 'confirm-rejected']
    .forEach((sid) => {
      const el = document.getElementById(sid);
      if (el) el.style.display = sid === id ? 'block' : 'none';
    });
}

function formatBRL(v) {
  return Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

// ── Renderiza itens do pedido ─────────────────────────────────────────────────
function renderItems(items, total) {
  const list = document.getElementById('confirm-items-list');
  const totalEl = document.getElementById('confirm-total');
  if (!list) return;

  list.replaceChildren();
  items.forEach((item) => {
    const row = document.createElement('div');
    row.className = 'summary__item';
    row.innerHTML = `
      <span class="summary__item-icon material-symbols-outlined">confirmation_number</span>
      <span class="summary__label">
        <span>${item.ticket_nome}</span>
        <span class="summary__unit">${item.batch_nome} · ${item.titular_nome}</span>
      </span>
      <span class="summary__price">${formatBRL(item.subtotal)}</span>
    `;
    list.appendChild(row);
  });

  if (totalEl) totalEl.textContent = formatBRL(total);
}

// ── PIX polling ───────────────────────────────────────────────────────────────
let pixInterval = null;

function startPixPolling() {
  if (pixInterval) return;
  pixInterval = setInterval(loadStatus, 5000);
}

function showPixData(pedido) {
  const pix = pedido.pix;
  if (!pix) return;

  const img  = document.getElementById('confirm-pix-img');
  const code = document.getElementById('confirm-pix-code');
  const exp  = document.getElementById('confirm-pix-expiry');
  const copy = document.getElementById('confirm-pix-copy');

  if (img && pix.qr_code_base64) img.src = 'data:image/png;base64,' + pix.qr_code_base64;
  if (code && pix.qr_code)       code.value = pix.qr_code;
  if (exp && pix.expiration) {
    exp.textContent = 'Válido até ' + new Date(pix.expiration).toLocaleString('pt-BR');
  }

  if (copy) {
    copy.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(code?.value ?? '');
        copy.innerHTML = '<span class="material-symbols-outlined">check</span> Copiado!';
        setTimeout(() => {
          copy.innerHTML = '<span class="material-symbols-outlined">content_copy</span> Copiar';
        }, 2500);
      } catch (_) { code?.select(); }
    });
  }
}

// ── Carrega e renderiza o status ──────────────────────────────────────────────
async function loadStatus() {
  if (!PEDIDO_UID) {
    show('confirm-rejected');
    return;
  }

  try {
    const res  = await fetch(`${BASE_PATH}/api/pedido/status.php?uid=${encodeURIComponent(PEDIDO_UID)}`, {
      credentials: 'same-origin',
    });
    const data = await res.json();

    if (!data.success) {
      show('confirm-rejected');
      return;
    }

    const pedido = data.pedido;
    const items  = data.items ?? [];

    switch (pedido.status) {
      case 'aprovado':
        clearInterval(pixInterval);
        if (titleEl)    titleEl.textContent    = 'Pagamento aprovado!';
        if (subtitleEl) subtitleEl.textContent = 'Seus ingressos foram emitidos com sucesso.';
        renderItems(items, pedido.total);
        show('confirm-approved');
        break;

      case 'pendente_pix':
        if (titleEl)    titleEl.textContent    = 'Aguardando PIX';
        if (subtitleEl) subtitleEl.textContent = 'Escaneie o QR Code ou cole o código no seu banco.';
        showPixData(pedido);
        show('confirm-pix');
        startPixPolling();
        break;

      case 'processando':
        clearInterval(pixInterval);
        if (titleEl)    titleEl.textContent    = 'Pagamento em análise';
        if (subtitleEl) subtitleEl.textContent = 'Você receberá uma notificação em breve.';
        show('confirm-processing');
        break;

      case 'rejeitado':
      case 'cancelado':
        clearInterval(pixInterval);
        if (titleEl)    titleEl.textContent    = 'Pagamento não concluído';
        if (subtitleEl) subtitleEl.textContent = 'Não foi possível processar seu pagamento.';
        const retryLink = document.getElementById('confirm-retry-link');
        if (retryLink) retryLink.href = `${BASE_PATH}/checkout?uid=${encodeURIComponent(PEDIDO_UID)}`;
        show('confirm-rejected');
        break;

      default:
        if (titleEl) titleEl.textContent = 'Verificando…';
        show('confirm-processing');
    }
  } catch (err) {
    console.error('[confirmacao]', err);
    show('confirm-rejected');
  }
}

document.addEventListener('DOMContentLoaded', loadStatus);
