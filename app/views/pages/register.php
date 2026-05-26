<header class="hero hero--compact">
  <div class="hero__top">
    <div>
      <p class="eyebrow">Cadastro</p>
      <h1>Crie sua conta</h1>
      <p class="lede">Preencha os dados e valide o contato com um codigo temporario.</p>
    </div>
  </div>
</header>

<main class="layout layout--auth">
  <section class="panel auth-card">
    <header class="panel__header panel__header--auth">
      <div>
        <p class="eyebrow">CRIE SUA CONTA</p>
        <h2>Cadastre-se, é rapidão!</h2>
        <p class="subtle">Sem senha. Usamos um codigo de 6 digitos enviado para seu e-mail ou WhatsApp.</p>
      </div>
    </header>

    <form class="auth-form auth-step" id="register-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

      <div id="register-fields">
        <label class="field field--float">
          <input type="text" name="name" placeholder=" " autocomplete="name" required>
          <span>Nome completo</span>
          <small class="field-hint" data-hint-for="name"></small>
        </label>

        <div class="auth-grid">
          <label class="field field--float">
            <input type="email" name="email" placeholder=" " autocomplete="email" required>
            <span>E-mail</span>
            <small class="field-hint" data-hint-for="email"></small>
          </label>
          <label class="field field--float">
            <input type="tel" name="whatsapp" placeholder=" " inputmode="tel" autocomplete="tel-national" required>
            <span>WhatsApp</span>
            <small class="field-hint" data-hint-for="whatsapp"></small>
          </label>
        </div>

        <label class="field field--float">
          <input type="text" name="cpf" placeholder=" " inputmode="numeric" pattern="\d*" autocomplete="off" required>
          <span>CPF (apenas numeros)</span>
          <small class="field-hint" data-hint-for="cpf"></small>
        </label>

        <div class="choice-group" role="group" aria-label="Canal para receber o codigo">
          <p class="subtle" style="margin: 0;">Preferencia para receber o codigo</p>
          <label class="choice-card">
            <input type="radio" name="delivery_method" value="email" checked>
            <div class="choice-card__body">
              <div class="choice-card__title">
                <span class="material-symbols-outlined" aria-hidden="true">mail</span>
                <strong>E-mail</strong>
              </div>
              <small>Enviaremos para o e-mail informado.</small>
            </div>
          </label>
          <label class="choice-card">
            <input type="radio" name="delivery_method" value="whatsapp">
            <div class="choice-card__body">
              <div class="choice-card__title">
                <span class="material-symbols-outlined" aria-hidden="true">chat</span>
                <strong>WhatsApp</strong>
              </div>
              <small>Usamos o numero digitado acima com DDD.</small>
            </div>
          </label>
        </div>

        <label class="checkbox">
          <input type="checkbox" name="remember_me" value="1" checked>
          <span>Manter conectado neste dispositivo</span>
        </label>

        <button class="btn btn-primary btn-full" type="submit" id="submit-register">
          <span class="btn-label">Criar conta</span>
          <span class="btn-spinner" aria-hidden="true"></span>
        </button>
      </div>

      <div id="code-step" class="auth-step" hidden style="margin-top: 1.5rem;">
        <div class="code-box__header">
          <div>
            <p class="eyebrow">Proxima etapa</p>
            <strong>Validar codigo</strong>
          </div>
          <button class="btn btn-secondary btn-small" type="button" id="resend-code">
            <span class="btn-label">Reenviar codigo</span>
            <span class="btn-spinner" aria-hidden="true"></span>
          </button>
        </div>
        <label class="field field--float">
          <input type="text" name="code" placeholder=" " autocomplete="one-time-code" inputmode="numeric" pattern="\d*" maxlength="6">
          <span>Codigo recebido</span>
          <small class="field-hint" data-hint-for="code"></small>
        </label>
        <button class="btn btn-primary btn-full" type="button" id="verify-code">
          <span class="btn-label">Confirmar codigo</span>
          <span class="btn-spinner" aria-hidden="true"></span>
        </button>
        <p class="subtle code-box__hint" id="code-status">Cadastre-se e, em seguida, valide o codigo.</p>
      </div>

      <p class="subtle" id="register-message" aria-live="polite"></p>
    </form>
  </section>

  <aside class="panel auth-side">
    <p class="eyebrow">MeuID</p>
    <h3>Centralize ingressos e consumo</h3>
    <p class="subtle">Use a mesma conta para reembolsar saldo, transferir titulares e fazer check-in sem filas.</p>

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
        <strong>Como funciona</strong><br>
        <small>Preencha os dados, valide o e-mail com o codigo e conclua o cadastro. Sem senha fraca.</small>
      </div>
    </div>
  </aside>
</main>
