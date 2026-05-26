<header class="hero hero--compact">
  <div class="hero__top">
    <div>
      <p class="eyebrow">MEUID</p>
      <h1>Entrar na conta</h1>
    </div>
  </div>
</header>

<div class="login-mobile-brand">
  <span class="material-symbols-outlined login-mobile-brand__icon">account_circle</span>
  <h1 class="login-mobile-brand__title">Entrar na conta</h1>
  <p class="login-mobile-brand__sub">Acesse com e-mail ou CPF</p>
</div>

<main class="layout">
  <section class="panel auth-card">
    <header class="panel__header panel__header--auth">
      <div>
        <p class="eyebrow">Login</p>
        <h2>Acesse com e-mail ou CPF</h2>
        <p style="margin:0px;" class="lede">Sem senha! Você usará um código recebido no seu <b>E-mail</b> ou <b>WhatsApp</b>.</p>
      </div>
    </header>

    <form class="auth-form" id="login-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
      <div id="login-step" class="auth-step">
        <label class="field field--float">
          <input style="margin-top:1px" type="text" name="identifier" placeholder=" " autocomplete="email" required>
          <span>E-mail ou CPF</span>
        </label>

        <div class="choice-group" role="group" aria-label="Canal para receber o codigo">
          <p class="subtle" style="margin-top: 20px;">Como prefere receber o código?</p>
          <label class="choice-card">
            <input type="radio" name="delivery_method" value="email" checked>
            <div class="choice-card__body">
              <div class="choice-card__title">
                <span class="material-symbols-outlined" aria-hidden="true">mail</span>
                <strong>E-mail</strong>
              </div>
              <small>Você recebe o código de confirmação no seu e-mail</small>
            </div>
          </label>
          <label class="choice-card">
            <input type="radio" name="delivery_method" value="whatsapp">
            <div class="choice-card__body">
              <div class="choice-card__title">
                <span class="material-symbols-outlined" aria-hidden="true">chat</span>
                <strong>WhatsApp</strong>
              </div>
              <small>Você recebe o código de confirmação no WhatsApp.</small>
            </div>
          </label>
        </div>

        <label class="checkbox">
          <input type="checkbox" name="remember_me" value="1" checked>
          <span style="margin-top:10px">Manter conectado neste dispositivo</span>
        </label>

        <div class="auth-actions auth-actions--stack">
          <button class="btn btn-secondary btn-full" type="button" id="request-code">
            <span class="btn-label">Enviar código</span>
            <span class="btn-spinner" aria-hidden="true"></span>
          </button>
          <p class="subtle" style="text-align:center; margin-top:12px; margin-bottom:0;">Ainda não tem conta? <a class="helper-link" href="<?= url('registro'); ?>">Criar cadastro</a></p>
        </div>
      </div>

      <div id="code-step" class="auth-step" hidden>
        <label class="field field--float">
          <input type="text" name="code" placeholder=" " inputmode="numeric" pattern="\d*"
            autocomplete="one-time-code" maxlength="6">
          <span>Código de 6 dígitos</span>
        </label>

        <button class="btn btn-primary btn-full" type="submit" id="submit-login" disabled>
          <span class="btn-label">Entrar com código</span>
          <span class="btn-spinner" aria-hidden="true"></span>
        </button>
      </div>

      <p class="subtle" id="login-message" aria-live="polite"></p>
    </form>
  </section>

  <aside class="panel auth-side">
    <p class="eyebrow">MeuID</p>
    <h3>Centralize ingressos e consumo</h3>
    <p class="subtle">Entre para ver QR Codes ativos, reembolsar saldo e fazer check-in sem filas.</p>

    <div class="auth-benefits">
      <div class="benefit">
        <span class="material-symbols-outlined">swap_horiz</span>
        <div>
          <strong>Transferencias</strong>
          <small>Envie ingressos para amigos com seguranca.</small>
        </div>
      </div>
      <div class="benefit">
        <span class="material-symbols-outlined">shield</span>
        <div>
          <strong>Protecao</strong>
          <small>Validacao de CPF e monitoramento antifraude.</small>
        </div>
      </div>
    </div>

    <div class="auth-alert">
      <span class="material-symbols-outlined">info</span>
      <div>
        <strong>Primeiro acesso?</strong>
        <small>Envie seu e-mail e CPF. Vamos mandar um codigo de verificacao para confirmar.</small>
      </div>
    </div>
  </aside>
</main>