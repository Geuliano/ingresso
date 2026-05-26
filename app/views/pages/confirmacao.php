<meta name="pedido-uid" content="<?= e($pedidoUid) ?>">

<header class="hero">
  <div class="hero__top">
    <div>
      <p class="eyebrow">Pedido</p>
      <h1 id="confirm-title">Verificando pagamento…</h1>
      <p class="lede" id="confirm-subtitle"></p>
    </div>
  </div>
</header>

<main class="layout">

  <!-- Estado de carregamento -->
  <section class="panel" id="confirm-loading">
    <div class="checkout-state" style="display:flex">
      <span class="material-symbols-outlined checkout-state__icon spin">progress_activity</span>
      <p>Carregando detalhes do pedido…</p>
    </div>
  </section>

  <!-- Estado: aprovado -->
  <section class="panel" id="confirm-approved" style="display:none">
    <div class="confirm-icon confirm-icon--success">
      <span class="material-symbols-outlined" style="font-size:56px;color:var(--color-primary,#5affd6)">check_circle</span>
    </div>
    <div id="confirm-items-list" class="summary__list"></div>
    <div class="summary__total">
      <span>Total pago</span>
      <strong id="confirm-total">—</strong>
    </div>
    <a class="btn btn-primary btn-full" href="<?= url('/') ?>">Voltar ao início</a>
  </section>

  <!-- Estado: PIX pendente -->
  <section class="panel" id="confirm-pix" style="display:none">
    <div class="pix-container">
      <div class="pix-qr">
        <img id="confirm-pix-img" src="" alt="QR Code PIX" width="220" height="220">
      </div>
      <div class="pix-copy-wrap">
        <p class="subtle">Copie o código PIX:</p>
        <div class="pix-copy">
          <input type="text" id="confirm-pix-code" readonly class="pix-code-input">
          <button class="btn btn-secondary" id="confirm-pix-copy">
            <span class="material-symbols-outlined">content_copy</span>
            Copiar
          </button>
        </div>
      </div>
      <p class="subtle" id="confirm-pix-expiry"></p>
      <div class="pix-status" id="confirm-pix-status">
        <span class="material-symbols-outlined spin">progress_activity</span>
        <span>Aguardando confirmação…</span>
      </div>
    </div>
  </section>

  <!-- Estado: processando (análise) -->
  <section class="panel" id="confirm-processing" style="display:none">
    <div class="checkout-state">
      <span class="material-symbols-outlined checkout-state__icon" style="color:#f0c040">hourglass_top</span>
      <p>Seu pagamento está em análise. Você receberá uma notificação quando for confirmado.</p>
    </div>
    <a class="btn btn-secondary btn-full" style="margin-top:16px" href="<?= url('/') ?>">Voltar ao início</a>
  </section>

  <!-- Estado: rejeitado -->
  <section class="panel" id="confirm-rejected" style="display:none">
    <div class="checkout-state checkout-state--error">
      <span class="material-symbols-outlined checkout-state__icon">cancel</span>
      <p>Pagamento recusado. Verifique os dados do cartão e tente novamente.</p>
    </div>
    <a class="btn btn-primary btn-full" style="margin-top:16px" id="confirm-retry-link" href="#">Tentar novamente</a>
  </section>

</main>
