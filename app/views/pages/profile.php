<?php
$displayName = $user['name'] ?? 'Visitante';
$firstName = trim(explode(' ', $displayName)[0] ?? $displayName);
$firstName = $firstName !== '' ? mb_convert_case($firstName, MB_CASE_TITLE, 'UTF-8') : 'Visitante';
$email = $user['email'] ?? '---';
$cpf = $user['cpf'] ?? '';

$cpfMasked = $cpf ? substr($cpf, 0, 3) . '.***.***-' . substr($cpf, -2) : '---';
$verified = !empty($user['email_verified_at']);
$memberSince = isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : '---';
?>

<header class="hero">
  <div class="hero__top">
    <div>
      <p class="eyebrow">Sua conta</p>
      <h1>Olá, <?= e($firstName); ?></h1>
      <p class="lede">
        E-mail: <?= e($email); ?> | CPF: <?= e($cpfMasked); ?>
      </p>
      <p class="subtle">
        Status: <?= $verified ? 'E-mail verificado' : 'Verifique seu e-mail para liberar tudo'; ?> | Membro desde <?= e($memberSince); ?>
      </p>
      <!-- Botão de sair visível apenas no mobile (hero__cta fica oculto) -->
      <form method="POST" action="<?= url('logout'); ?>" class="profile-logout-mobile">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <button class="btn btn-ghost btn-small" type="submit">
          <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">logout</span>
          Sair
        </button>
      </form>
    </div>
    <div class="hero__cta hero__cta--profile">
      <p class="cta-kicker">Ingressos ativos</p>
      <p class="cta-copy">Acompanhe seus tickets e vouchers vinculados a esta conta.</p>
      <button class="btn btn-primary" data-scroll-target="#tickets-comprados">Ver ingressos</button>
      <form method="POST" action="<?= url('logout'); ?>" class="mt-8">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <button class="btn btn-ghost" type="submit">Sair</button>
      </form>
    </div>
  </div>
</header>

<main class="layout">
  <section class="panel" id="tickets-comprados">
    <header class="panel__header">
      <div>
        <p class="eyebrow">Ingressos</p>
        <h2>Tickets e vouchers</h2>
        <p class="subtle">Baixe, transfira titulares ou peca reembolso.</p>
      </div>
      <div class="inline-actions">
        <button class="btn btn-ghost">Solicitar reembolso</button>
        <button class="btn btn-secondary">Transferir titular</button>
      </div>
    </header>

    <div class="ticket-grid">
      <div class="ticket ticket--compact">
        <div class="ticket__info">
          <div class="ticket__meta">
            <span class="badge badge--success">Confirmado</span>
            <span class="badge">QR Code ativo</span>
          </div>
          <h3>Pista - Pulse Festival</h3>
          <div class="ticket__meta">
            <span class="status"><span class="status-dot"></span>Check-in pendente</span>
            <span>14 Dez - 22h</span>
          </div>
        </div>
        <div class="ticket__actions">
          <button class="btn btn-secondary btn-small">Baixar ingresso</button>
          <button class="btn btn-ghost btn-small">Ver QR Code</button>
        </div>
      </div>

      <div class="ticket ticket--compact">
        <div class="ticket__info">
          <div class="ticket__meta">
            <span class="badge badge--warning">Pendente</span>
            <span class="badge">Aguardando pagamento</span>
          </div>
          <h3>Frontstage - Pulse Festival</h3>
          <div class="ticket__meta">
            <span class="status"><span class="status-dot is-warning"></span>Pagamento aberto</span>
            <span>14 Dez - 22h</span>
          </div>
        </div>
        <div class="ticket__actions">
          <button class="btn btn-secondary btn-small">Pagar agora</button>
          <button class="btn btn-ghost btn-small">Cancelar</button>
        </div>
      </div>

      <div class="ticket ticket--compact">
        <div class="ticket__info">
          <div class="ticket__meta">
            <span class="badge badge--success">Confirmado</span>
            <span class="badge">Voucher enviado</span>
          </div>
          <h3>Voucher Consumo R$ 200</h3>
          <div class="ticket__meta">
            <span class="status"><span class="status-dot"></span>Saldo ativo</span>
            <span>Expira pos-evento</span>
          </div>
        </div>
        <div class="ticket__actions">
          <button class="btn btn-secondary btn-small">Baixar voucher</button>
          <button class="btn btn-ghost btn-small">Reembolsar saldo</button>
        </div>
      </div>
    </div>
  </section>

  <section class="panel">
    <header class="panel__header">
      <div>
        <p class="eyebrow">Em breve</p>
        <h2>Area reservada</h2>
        <p class="subtle">Espaco ficticio para futuros recursos de conta.</p>
      </div>
    </header>

    <div class="info-grid">
      <div class="info-card">
        <span class="info-label">Placeholder</span>
        <strong>Planejando novidades</strong>
        <small>Em breve voce vera configuracoes e atalhos aqui.</small>
      </div>
      <div class="info-card">
        <span class="info-label">Sugestoes</span>
        <strong>Notificacoes e favoritos</strong>
        <small>Podemos exibir alertas de lotes e eventos salvos.</small>
      </div>
      <div class="info-card">
        <span class="info-label">Status</span>
        <strong>Rascunho</strong>
        <small>Secao aguardando definicao do produto.</small>
      </div>
    </div>
  </section>
</main>
