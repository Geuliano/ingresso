<?php
/**
 * ==========================================================
 * MÓDULO INFO-EVENTO — Entry Point (seguro)
 * ==========================================================
 * Gerenciamento das informações do evento e do produtor.
 * Segue o mesmo padrão de whitelist dos demais módulos.
 * ==========================================================
 */

requireLogin();

$moduloSlug = basename(__DIR__); // 'info-evento'

$acao = $_GET['acao'] ?? 'listar';

$acoesPermitidas = ['listar'];

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
