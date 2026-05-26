<?php
/**
 * ==========================================================
 * INDEX DO MÓDULO: INGRESSOS (SEGURO)
 * ==========================================================
 * - Só aceita ações permitidas (whitelist)
 * - Só inclui arquivos permitidos
 * - Impede exploração via URL
 * ==========================================================
 */

requireLogin();

// Ação solicitada
$acao = $_GET['acao'] ?? 'listar';

// Lista branca de ações PERMITIDAS
$acoesPermitidas = [
    'listar',
];

if (!in_array($acao, $acoesPermitidas, true)) {
    $acao = 'listar'; // fallback seguro
}

// Caminhos internos do módulo
$basePath = __DIR__;
$arquivo  = "{$basePath}/{$acao}.php";

// O arquivo precisa existir E a ação estar na lista branca
if (!file_exists($arquivo)) {
    $arquivo = "{$basePath}/listar.php"; // fallback
}

// Carrega o header do módulo
require_once dirname(__DIR__, 2) . '/core/module_header.php';

// Inclui a ação
require $arquivo;
