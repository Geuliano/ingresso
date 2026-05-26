<?php
/**
 * ==========================================================
 * MÓDULO CLIENTES — Entry Point (seguro)
 * ==========================================================
 * Gerenciamento dos usuários cadastrados no site principal.
 * Segue o mesmo padrão de whitelist dos demais módulos.
 * ==========================================================
 */

requireLogin();

$moduloSlug = basename(__DIR__); // 'clientes'

$acao = $_GET['acao'] ?? 'listar';

// Whitelist de ações permitidas
$acoesPermitidas = [
    'listar',
];

if (!in_array($acao, $acoesPermitidas, true)) {
    $acao = 'listar';
}

$basePath = __DIR__;
$arquivo  = "{$basePath}/{$acao}.php";

if (!file_exists($arquivo)) {
    $arquivo = "{$basePath}/listar.php";
}

require_once dirname(__DIR__, 2) . '/core/module_header.php';

require $arquivo;
