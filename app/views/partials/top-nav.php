<?php $active = $activeNav ?? ''; ?>
<?php if ($active === 'home'): ?>
  <nav class="top-nav">
    <div class="top-nav__left"></div>
    <div class="top-nav__links">
      <a class="pill-link" href="<?= url('index#intro'); ?>">
        <span class="material-symbols-outlined">graphic_eq</span>
        <span>Line-up</span>
      </a>
      <a class="pill-link" href="<?= url('index#checkout'); ?>">
        <span class="material-symbols-outlined">finance_chip</span>
        <span>Preços</span>
      </a>
      <a class="pill-link" href="<?= url('index#faq'); ?>">
        <span class="material-symbols-outlined">help</span>
        <span>FAQ</span>
      </a>
      <a class="pill-link" href="<?= url('perfil'); ?>">
        <span class="material-symbols-outlined">confirmation_number</span>
        <span>Meus Ingressos</span>
      </a>
    </div>
  </nav>
<?php else: ?>
  <nav class="top-nav top-nav--back-only">
    <div class="top-nav__left">
      <a class="pill-link pill-link--icon" href="<?= url('/'); ?>">
        <span class="material-symbols-outlined">arrow_back</span>
        <span>VOLTAR</span>
      </a>
    </div>
  </nav>
<?php endif; ?>
