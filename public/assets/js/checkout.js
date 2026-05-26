/**
 * checkout.js — Inicialização do Payment Brick (Mercado Pago)
 *
 * Lê os dados do pedido via <meta> tags injetadas pela view PHP,
 * instancia o Brick, trata submissão para cartão e PIX,
 * e exibe o QR Code em caso de PIX.
 */

const { maskCpf, isValidCpf } = window.IngressoUtils;

// ── Lê metadados do pedido ────────────────────────────────────────────────────
function getMeta(name) {
  return document.querySelector(`meta[name="${name}"]`)?.content ?? '';
}

const MP_PUBLIC_KEY = getMeta('mp-public-key');
const PEDIDO_UID    = getMeta('pedido-uid');
const PEDIDO_TOTAL  = parseFloat(getMeta('pedido-total')) || 0;
const PAYER_EMAIL   = getMeta('payer-email');
const PAYER_NAME    = getMeta('payer-name');
const BASE_PATH     = getMeta('base-path').replace(/\/$/, '');
const CSRF_TOKEN    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ── Elementos UI ──────────────────────────────────────────────────────────────
const loadingEl    = document.getElementById('checkout-loading');
const errorEl      = document.getElementById('checkout-error');
const errorMsgEl   = document.getElementById('checkout-error-msg');
const retryBtn     = document.getElementById('checkout-retry-btn');
const paymentEl    = document.getElementById('payment-section');
const pixSection   = document.getElementById('pix-section');
const pixQrImg     = document.getElementById('pix-qr-img');
const pixCodeInput = document.getElementById('pix-code');
const pixCopyBtn   = document.getElementById('pix-copy-btn');
const pixExpiryEl  = document.getElementById('pix-expiry-text');
const pixStatusEl  = document.getElementById('pix-status');
const payerCpfInput = document.getElementById('payer-cpf');
const payerCpfError = document.getElementById('payer-cpf-error');

// ── CPF do pagador ────────────────────────────────────────────────────────────
if (payerCpfInput) {
  payerCpfInput.addEventListener('input', () => {
    payerCpfInput.value = maskCpf(payerCpfInput.value);
    payerCpfError.style.display = 'none';
  });
}

function getPayerCpf() {
  return (payerCpfInput?.value ?? '').replace(/\D/g, '');
}

function validatePayerCpf() {
  const cpf = getPayerCpf();
  if (!isValidCpf(cpf)) {
    if (payerCpfError) payerCpfError.style.display = 'block';
    payerCpfInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    payerCpfInput?.focus();
    return false;
  }
  if (payerCpfError) payerCpfError.style.display = 'none';
  return true;
}

// ── Funções de UI ─────────────────────────────────────────────────────────────
function showLoading() {
  if (loadingEl) loadingEl.style.display = 'flex';
  if (errorEl)   errorEl.style.display   = 'none';
}

function showError(msg) {
  if (loadingEl) loadingEl.style.display = 'none';
  if (errorEl)   errorEl.style.display   = 'flex';
  if (errorMsgEl) errorMsgEl.textContent = msg || 'Ocorreu um erro. Tente novamente.';
}

function hideStateUi() {
  if (loadingEl) loadingEl.style.display = 'none';
  if (errorEl)   errorEl.style.display   = 'none';
}

// ── Polling de status do PIX ──────────────────────────────────────────────────
let pixPollingInterval = null;

function startPixPolling() {
  if (pixPollingInterval) return;

  pixPollingInterval = setInterval(async () => {
    try {
      const res  = await fetch(`${BASE_PATH}/api/pedido/status.php?uid=${encodeURIComponent(PEDIDO_UID)}`, {
        credentials: 'same-origin',
      });
      const data = await res.json();

      if (!data.success) return;

      if (data.pedido.status === 'aprovado') {
        clearInterval(pixPollingInterval);
        pixPollingInterval = null;
        if (pixStatusEl) {
          pixStatusEl.innerHTML = `
            <span class="material-symbols-outlined" style="color:var(--color-primary,#5affd6)">check_circle</span>
            <span>Pagamento confirmado! Redirecionando…</span>
          `;
        }
        setTimeout(() => {
          window.location.href = `${BASE_PATH}/pedido/confirmacao?uid=${encodeURIComponent(PEDIDO_UID)}`;
        }, 2000);
      }
    } catch (_) {
      // silencioso
    }
  }, 4000);
}

// ── Exibe tela de PIX ─────────────────────────────────────────────────────────
function showPixScreen(pixData) {
  if (paymentEl)  paymentEl.style.display  = 'none';
  if (pixSection) pixSection.style.display = 'block';

  if (pixQrImg && pixData.qr_code_base64) {
    pixQrImg.src = 'data:image/png;base64,' + pixData.qr_code_base64;
  }

  if (pixCodeInput && pixData.qr_code) {
    pixCodeInput.value = pixData.qr_code;
  }

  if (pixExpiryEl && pixData.expiration) {
    const exp = new Date(pixData.expiration);
    pixExpiryEl.textContent = 'Válido até ' + exp.toLocaleString('pt-BR');
  }

  startPixPolling();
}

// Copiar código PIX
if (pixCopyBtn) {
  pixCopyBtn.addEventListener('click', async () => {
    const code = pixCodeInput?.value;
    if (!code) return;
    try {
      await navigator.clipboard.writeText(code);
      pixCopyBtn.innerHTML = '<span class="material-symbols-outlined">check</span> Copiado!';
      setTimeout(() => {
        pixCopyBtn.innerHTML = '<span class="material-symbols-outlined">content_copy</span> Copiar';
      }, 2500);
    } catch (_) {
      pixCodeInput?.select();
    }
  });
}

// ── Inicialização do Payment Brick ────────────────────────────────────────────
async function initBrick() {
  if (!MP_PUBLIC_KEY) {
    showError('Configuração de pagamento ausente. Fale com o suporte.');
    return;
  }

  if (!window.MercadoPago) {
    showError('SDK do Mercado Pago não carregou. Verifique sua conexão.');
    return;
  }

  showLoading();

  try {
    const mp     = new MercadoPago(MP_PUBLIC_KEY, { locale: 'pt-BR' });
    const bricks = mp.bricks();

    const brick = await bricks.create('payment', 'paymentBrick_container', {
      initialization: {
        amount: PEDIDO_TOTAL,
        payer: {
          email: PAYER_EMAIL,
        },
      },
      customization: {
        paymentMethods: {
          creditCard:       'all',
          debitCard:        'all',
          ticket:           'none',
          bankTransfer:     'pix',
          atm:              'none',
          onboarding_credits: 'none',
          wallet_purchase:  'none',
        },
        visual: {
          style: {
            theme: 'dark',
            customVariables: {
              baseColor:     '#5affd6',
              baseColorFirstVariant:  '#5affd6',
              baseColorSecondVariant: '#5affd6',
              borderRadiusLarge: '12px',
              borderRadiusMedium: '8px',
              borderRadiusSmall:  '6px',
            },
          },
          hideFormTitle: true,
        },
      },
      callbacks: {
        onReady: () => {
          hideStateUi();
        },

        onSubmit: async ({ selectedPaymentMethod, formData }) => {
          if (!validatePayerCpf()) {
            return;
          }

          const payerCpf = getPayerCpf();

          try {
            const res = await fetch(`${BASE_PATH}/api/pedido/pagar.php`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'same-origin',
              body: JSON.stringify({
                pedido_uid: PEDIDO_UID,
                form_data:  formData,
                payer_cpf:  payerCpf,
              }),
            });

            const result = await res.json();

            if (!res.ok || result.error) {
              // Retorna a mensagem de erro ao Brick (ele exibe internamente)
              return Promise.reject(result.error || 'Erro ao processar pagamento.');
            }

            if (result.payment_method === 'pix') {
              showPixScreen(result.pix);
            } else {
              // Cartão: redireciona para página de confirmação
              window.location.href = `${BASE_PATH}/pedido/confirmacao?uid=${encodeURIComponent(PEDIDO_UID)}`;
            }
          } catch (err) {
            return Promise.reject(err?.message || 'Erro de conexão. Tente novamente.');
          }
        },

        onError: (error) => {
          console.error('[Brick] Erro:', error);
          if (error?.cause?.some?.(c => c.code !== 'E301')) {
            showError('Erro no formulário de pagamento. Tente recarregar a página.');
          }
        },
      },
    });
  } catch (err) {
    console.error('[checkout] Erro ao inicializar Brick:', err);
    showError('Não foi possível carregar o formulário de pagamento. Tente novamente.');
  }
}

// Retry
if (retryBtn) {
  retryBtn.addEventListener('click', () => {
    initBrick();
  });
}

// ── Inicializa ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Se o pedido já está em estado de PIX pendente, mostra a tela de PIX direto
  // (recarregamento de página com PIX em andamento)
  // O status é conhecido via API; mas para simplificar, só inicia o Brick.
  initBrick();
});
