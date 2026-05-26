<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

$evento = fetch_info_evento(db());
$lineup = fetch_lineup(db());
$faq    = fetch_faq(db());

render('home', [
    'title'      => $evento['nome_evento'] !== '' ? e($evento['nome_evento']) . ' | Ingressos' : 'Ingressos | Pulse Festival',
    'activeNav'  => 'home',
    'showTopNav' => true,
    'scripts'    => ['js/app.js'],
    'evento'     => $evento,
    'lineup'     => $lineup,
    'faq'        => $faq,
]);
