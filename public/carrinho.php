<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

render('cart', [
    'title' => 'Carrinho | Pulse Festival',
    'activeNav' => 'cart',
    'showTopNav' => true,
    'scripts' => ['js/cart.js'],
]);
