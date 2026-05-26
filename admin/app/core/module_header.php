<?php
requireLogin();

require_once CORE_PATH . '/config.php';
require_once CORE_PATH . '/permissions.php';

$moduloParam = $_GET['mod'] ?? ($GLOBALS['moduloIdAtual'] ?? null);
$moduloId = resolveModuleId($moduloParam);
if (!$moduloId) {
    $moduloId = getModuleIdBySlug('dashboard');
}

$acaoInput  = $_GET['acao'] ?? 'listar';
$acaoInput = preg_replace('/[^a-z0-9_\-]/i', '', (string)$acaoInput);

$acoesPermitidas = [
    'listar', 'adicionar', 'editar', 'excluir', 'detalhes', 'salvar'
];

$acao = in_array($acaoInput, $acoesPermitidas, true) ? $acaoInput : 'listar';

$moduloInfo = null;
$parentInfo = null;

try {
    $moduloInfo = $moduloId ? getModuleById((int)$moduloId) : null;
} catch (Throwable $e) {
    $moduloInfo = null;
}

if (!empty($moduloInfo['id_pai'])) {
    $pai = getModuleById((int)$moduloInfo['id_pai']);
    if ($pai) {
        $parentInfo = [
            'id' => $pai['id'],
            'nome' => $pai['nome'],
            'slug' => $pai['slug'] ?? '',
        ];
    }
}

$tituloModulo = $moduloInfo['nome'] ?? 'M��dulo';
$descModulo   = $moduloInfo['descricao'] ?? ($moduloInfo['slug'] ?? '');

$acoesLegiveis = [
    'listar'    => 'Listar',
    'adicionar' => 'Adicionar',
    'editar'    => 'Editar',
    'excluir'   => 'Excluir',
    'detalhes'  => 'Detalhes',
    'salvar'    => 'Salvar',
];

$tituloAcao = $acoesLegiveis[$acao] ?? ucfirst($acao);
?>


