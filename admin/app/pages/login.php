<?php
require_once dirname(__DIR__) . '/core/auth.php';
require_once APP_PATH . '/modules/personalizar/personalizar_helper.php';

// Cabeçalhos de segurança — enviados antes de qualquer output
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none';");

if (isLoggedIn()) {
    header("Location: " . BASE_URL);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$brandingConfig = getBrandingConfig($pdo);
$pageTitleBrand = $brandingConfig['titulo_site'] ?? 'Painel Administrativo';
$faviconLogin = $brandingConfig['favicon'] ?? 'public/assets/images/favicon.ico';
?>

<!DOCTYPE html>
<html lang="pt_BR" dir="ltr">
<head>
  <meta charset="utf-8" />
  <title><?= htmlspecialchars($pageTitleBrand) ?> | Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" href="<?= BASE_URL . $faviconLogin ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; height: 100%; }

    body {
      font-family: "Space Grotesk", system-ui, sans-serif;
      display: flex;
      min-height: 100vh;
      background: #080810;
      color: #eef0f8;
    }

    /* ── Lado esquerdo (decorativo) ── */
    .login-panel {
      flex: 1;
      display: none;
      position: relative;
      background: linear-gradient(145deg, #0e0f1c 0%, #13141f 60%, #1a1b2e 100%);
      overflow: hidden;
      padding: 3rem;
      flex-direction: column;
      justify-content: space-between;
      border-right: 1px solid rgba(255,255,255,.06);
    }

    @media (min-width: 900px) { .login-panel { display: flex; } }

    /* Blobs de luz coloridos */
    .login-panel__blobs { position: absolute; inset: 0; pointer-events: none; }
    .login-panel__blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(60px);
    }
    .login-panel__blob--a {
      width: 380px; height: 380px; top: -120px; right: -80px;
      background: rgba(90, 255, 214, 0.10);
    }
    .login-panel__blob--b {
      width: 260px; height: 260px; bottom: 80px; left: -60px;
      background: rgba(157, 122, 255, 0.12);
    }
    .login-panel__blob--c {
      width: 160px; height: 160px; bottom: -40px; right: 120px;
      background: rgba(90, 255, 214, 0.07);
    }

    .login-panel__body {
      position: relative;
      z-index: 1;
      color: #eef0f8;
    }
    .login-panel__body h2 {
      font-size: clamp(1.6rem, 2.6vw, 2.2rem);
      font-weight: 800;
      line-height: 1.2;
      margin: 0 0 .8rem;
    }
    .login-panel__body p {
      font-size: .97rem;
      color: #8892aa;
      line-height: 1.7;
      max-width: 36ch;
      margin: 0;
    }

    /* Linha accent decorativa */
    .login-panel__body::before {
      content: '';
      display: block;
      width: 32px;
      height: 2px;
      background: #5affd6;
      border-radius: 2px;
      margin-bottom: 1rem;
    }

    .login-panel__footer {
      position: relative;
      z-index: 1;
      color: #8892aa;
      font-size: .8rem;
    }

    /* ── Lado direito (form) ── */
    .login-form-wrap {
      width: 100%;
      max-width: 480px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2.5rem 1.5rem;
    }

    .login-box {
      width: 100%;
    }

    .login-box h1 {
      font-size: 1.65rem;
      font-weight: 800;
      letter-spacing: -.02em;
      margin: 0 0 1.5rem;
      color: #eef0f8;
    }

    .login-box__alert {
      display: none;
      padding: .8rem 1rem;
      border-radius: 10px;
      font-size: .88rem;
      font-weight: 500;
      margin-bottom: 1.2rem;
      border-left: 3px solid;
    }
    .login-box__alert.is-error   {
      background: rgba(255,107,107,.08);
      color: #ff6b6b;
      border-color: #ff6b6b;
    }
    .login-box__alert.is-success {
      background: rgba(90,255,214,.08);
      color: #5affd6;
      border-color: #5affd6;
    }
    .login-box__alert.is-visible { display: block; }

    .login-field { margin-bottom: 1rem; }
    .login-field label {
      display: block;
      font-size: .86rem;
      font-weight: 600;
      color: #b0b8cc;
      margin-bottom: .35rem;
    }
    .login-field input {
      width: 100%;
      padding: .8rem 1rem;
      border: 1.5px solid rgba(255,255,255,.10);
      border-radius: 12px;
      font: inherit;
      font-size: .95rem;
      color: #eef0f8;
      background: rgba(255,255,255,.04);
      outline: none;
      transition: border-color 180ms, box-shadow 180ms, background 180ms;
    }
    .login-field input::placeholder { color: #8892aa; }
    .login-field input:focus {
      border-color: rgba(90,255,214,.5);
      box-shadow: 0 0 0 3px rgba(90,255,214,.10);
      background: rgba(255,255,255,.06);
    }

    .login-btn {
      width: 100%;
      padding: .85rem;
      margin-top: .4rem;
      border: none;
      border-radius: 12px;
      font: inherit;
      font-size: .97rem;
      font-weight: 700;
      color: #050a09;
      background: linear-gradient(135deg, #5affd6, #2ce8b8);
      box-shadow: 0 8px 24px rgba(90,255,214,.28);
      cursor: pointer;
      transition: box-shadow 200ms, transform 180ms, opacity 180ms;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .5em;
    }
    .login-btn:hover:not(:disabled) {
      box-shadow: 0 12px 32px rgba(90,255,214,.40);
      transform: translateY(-1px);
    }
    .login-btn:disabled { opacity: .55; cursor: not-allowed; }

    .login-box__footer {
      margin-top: 1.6rem;
      text-align: center;
      font-size: .78rem;
      color: #8892aa;
    }
  </style>
</head>

<body>

  <!-- Painel decorativo esquerdo -->
  <aside class="login-panel" aria-hidden="true">
    <div class="login-panel__blobs">
      <div class="login-panel__blob login-panel__blob--a"></div>
      <div class="login-panel__blob login-panel__blob--b"></div>
      <div class="login-panel__blob login-panel__blob--c"></div>
    </div>

    <div class="login-panel__body">
      <h2>Painel Administrativo</h2>
      <p>Área de acesso restrito.</p>
    </div>

    <div class="login-panel__footer">
      © <?= date('Y') ?>
    </div>
  </aside>

  <!-- Formulário de login -->
  <div class="login-form-wrap">
    <div class="login-box">

      <h1>Acesso</h1>

      <div id="alert-box" class="login-box__alert" role="alert"></div>

      <form id="loginForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="login-field">
          <label for="username">E-mail</label>
          <input type="email" name="username" id="username" placeholder="seu@email.com" required autocomplete="username">
        </div>

        <div class="login-field">
          <label for="password">Senha</label>
          <input type="password" name="password" id="password" placeholder="••••••••" required autocomplete="current-password">
        </div>

        <button type="submit" class="login-btn" id="btnLogin">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Entrar
        </button>
      </form>

      <p class="login-box__footer">
        © <?= date('Y') ?>
      </p>
    </div>
  </div>

<script>
document.getElementById("loginForm")?.addEventListener("submit", async (e) => {
  e.preventDefault();

  const form = e.target;
  const btn  = document.getElementById("btnLogin");
  const box  = document.getElementById("alert-box");

  box.className = "login-box__alert";
  btn.disabled  = true;
  btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="32" stroke-dashoffset="12"/></svg> Verificando...';

  try {
    const resp = await fetch("<?= BASE_URL ?>app/auth/login_action.php", {
      method: "POST",
      body: new FormData(form),
      credentials: "same-origin",
    });
    const ct = resp.headers.get("content-type") || "";
    if (!ct.includes("application/json")) throw new Error("Resposta inesperada do servidor.");

    const result = await resp.json();

    box.classList.add("is-visible");
    if (result.success) {
      box.classList.add("is-success");
      box.textContent = "Tudo certo! Entrando no sistema...";
      setTimeout(() => (window.location.href = result.redirect), 900);
    } else {
      box.classList.add("is-error");
      box.textContent = result.message || "E-mail ou senha incorretos.";
      btn.disabled  = false;
      btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Entrar';
    }
  } catch (err) {
    box.classList.add("is-visible", "is-error");
    box.textContent = "Erro: " + err.message;
    btn.disabled  = false;
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Entrar';
  }
});
</script>

</body>
</html>
