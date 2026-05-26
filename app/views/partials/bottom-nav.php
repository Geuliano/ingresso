<?php
$activeNav = $activeNav ?? '';
?>
<nav class="bottom-nav">
  <a id="nav-home" class="nav-item <?= $activeNav === 'home' ? 'is-active' : '' ?>" aria-label="Inicio"
    href="<?= url('index'); ?>">
    <span class="material-symbols-outlined">home</span>
    <small>Inicio</small>
  </a>
  <a id="nav-tickets" class="nav-item <?= $activeNav === 'tickets' ? 'is-active' : '' ?>" aria-label="Ingressos"
    href="<?= url('index#tickets-section'); ?>">
    <span class="material-symbols-outlined">confirmation_number</span>
    <small>Ingressos</small>
  </a>
  <a id="nav-cart" class="nav-item <?= $activeNav === 'cart' ? 'is-active' : '' ?>" aria-label="Carrinho"
    href="<?= url('carrinho'); ?>">
    <span class="material-symbols-outlined">shopping_cart</span>
    <span class="nav-badge" id="nav-cart-badge" aria-live="polite" aria-label="Itens no carrinho" hidden>0</span>
    <small>Carrinho</small>
  </a>
  <a id="nav-profile" class="nav-item <?= $activeNav === 'profile' ? 'is-active' : '' ?>" aria-label="Perfil"
    href="<?= url('perfil'); ?>">
    <span class="material-symbols-outlined">account_circle</span>
    <small>Perfil</small>
  </a>
</nav>
