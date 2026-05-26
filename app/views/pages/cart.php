<header class="hero">
  <div class="hero__top">
    <div>
      <p class="eyebrow">Finalizar compra</p>
      <h1>Confirme seus ingressos</h1>
      <p class="lede">Preencha com atenção os dados dos titulares de cada ingresso.</p>
    </div>
  </div>
</header>

<main class="layout">
  <section class="panel">
    <header class="panel__header">
      <div>
        <p class="eyebrow">Titulares</p>
        <h2 id="attendees-title">Dados de cada ingresso</h2>
        <p class="subtle" id="attendees-subtitle">Informe nome e CPF para emitir os tickets.</p>
      </div>
    </header>
    <div id="cart-attendees"></div>
  </section>

  <section class="panel">
    <header class="panel__header">
      <div>
        <p class="eyebrow">Resumo</p>
        <h2>Detalhes da compra</h2>
      </div>
    </header>

    <div class="summary__list" id="cart-summary"></div>
    <div class="summary__total">
      <span>Total</span>
      <strong id="cart-total">R$ 0,00</strong>
    </div>
    <button class="btn btn-primary btn-full" id="cta-buy">Ir para pagamento</button>
  </section>

</main>
