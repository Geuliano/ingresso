<!-- Injeta dados do pedido para o checkout.js via meta tags -->
<meta name="mp-public-key" content="<?= e($mpPublicKey) ?>">
<meta name="pedido-uid"    content="<?= e($pedido['uid']) ?>">
<meta name="pedido-total"  content="<?= e((string) $pedido['total']) ?>">
<meta name="payer-email"   content="<?= e($pedido['payer_email'] ?? '') ?>">
<meta name="payer-name"    content="<?= e($pedido['payer_name'] ?? '') ?>">

<header class="hero">
  <div class="hero__top">
    <div>
      <p class="eyebrow">Pagamento seguro</p>
      <h1>Finalizar compra</h1>
      <p class="lede">Escolha como prefere pagar seus ingressos.</p>
    </div>
  </div>
</header>

<main class="layout">

  <!-- Resumo do pedido -->
  <section class="panel">
    <header class="panel__header">
      <div>
        <p class="eyebrow">Resumo</p>
        <h2>Seus ingressos</h2>
      </div>
    </header>
    <div class="summary__list">
      <?php foreach ($items as $item): ?>
        <div class="summary__item">
          <span class="summary__item-icon material-symbols-outlined">confirmation_number</span>
          <span class="summary__label">
            <span><?= e($item['ticket_nome']) ?></span>
            <span class="summary__unit"><?= e($item['batch_nome']) ?> · <?= e($item['titular_nome']) ?></span>
          </span>
          <span class="summary__price">
            R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="summary__total">
      <span>Total</span>
      <strong>R$ <?= number_format((float) $pedido['total'], 2, ',', '.') ?></strong>
    </div>
  </section>

  <!-- Dados do pagador (CPF é obrigatório para cartão e PIX) -->
  <section class="panel" id="payer-section">
    <header class="panel__header">
      <div>
        <p class="eyebrow">Identificação</p>
        <h2>Seus dados</h2>
      </div>
    </header>
    <div class="panel__body">
      <label class="field field--float">
        <input type="text" id="payer-cpf" placeholder=" " maxlength="14" autocomplete="off">
        <span>CPF do responsável pela compra</span>
        <span class="attendee-card__field-icon material-symbols-outlined" aria-hidden="true">badge</span>
      </label>
      <p class="subtle" id="payer-cpf-error" style="color:var(--color-error,#ff6b6b);display:none;margin-top:4px;font-size:0.85rem;">CPF inválido.</p>
    </div>
  </section>

  <!-- Payment Brick do Mercado Pago -->
  <section class="panel" id="payment-section">
    <header class="panel__header">
      <div>
        <p class="eyebrow">Forma de pagamento</p>
        <h2>Como prefere pagar?</h2>
      </div>
    </header>
    <div id="paymentBrick_container"></div>

    <!-- Feedback de carregamento/erro -->
    <div id="checkout-loading" class="checkout-state" style="display:flex">
      <span class="material-symbols-outlined checkout-state__icon spin">progress_activity</span>
      <p>Carregando formulário de pagamento…</p>
    </div>
    <div id="checkout-error" class="checkout-state checkout-state--error" style="display:none">
      <span class="material-symbols-outlined checkout-state__icon">error</span>
      <p id="checkout-error-msg">Ocorreu um erro. Tente novamente.</p>
      <button class="btn btn-secondary" id="checkout-retry-btn">Tentar novamente</button>
    </div>
  </section>

  <!-- Tela de PIX (exibida após criar pagamento PIX) -->
  <section class="panel" id="pix-section" style="display:none">
    <header class="panel__header">
      <div>
        <p class="eyebrow">Pague com PIX</p>
        <h2>Escaneie o QR Code</h2>
        <p class="lede">Use o app do seu banco para pagar. O ingresso é liberado imediatamente após a confirmação.</p>
      </div>
    </header>
    <div class="pix-container">
      <div class="pix-qr" id="pix-qr-wrap">
        <img id="pix-qr-img" src="" alt="QR Code PIX" width="220" height="220">
      </div>
      <div class="pix-copy-wrap">
        <p class="subtle">Ou copie o código PIX:</p>
        <div class="pix-copy">
          <input type="text" id="pix-code" readonly class="pix-code-input">
          <button class="btn btn-secondary" id="pix-copy-btn">
            <span class="material-symbols-outlined">content_copy</span>
            Copiar
          </button>
        </div>
      </div>
      <p class="subtle pix-expiry" id="pix-expiry-text"></p>
      <div class="pix-status" id="pix-status">
        <span class="material-symbols-outlined spin">progress_activity</span>
        <span>Aguardando confirmação do pagamento…</span>
      </div>
    </div>
  </section>

</main>

<!-- SDK do Mercado Pago (carregado via script normal aqui para garantir que o Brick funcione) -->
<script src="https://sdk.mercadopago.com/js/v2"></script>
