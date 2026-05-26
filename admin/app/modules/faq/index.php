<?php
/**
 * ==========================================================
 * INDEX DO MÓDULO: FAQ
 * ==========================================================
 */

requireLogin();

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
