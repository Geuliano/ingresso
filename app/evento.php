<?php

declare(strict_types=1);

/**
 * Busca as informações do evento da tabela info_evento (registro id = 1).
 */
function fetch_info_evento(PDO $pdo): array
{
    $defaults = [
        'nome_evento'        => '', 'data_inicio'     => '', 'data_fim'          => '',
        'local_nome'         => '', 'local_cidade'    => '', 'local_estado'      => '',
        'produtor_nome'      => '', 'produtor_cpf_cnpj'=> '', 'produtor_endereco' => '',
        'produtor_email'     => '', 'produtor_telefone1'=> '', 'produtor_telefone2'=> '',
        'data_inicio_br'     => '', 'data_fim_br'     => '', 'data_inicio_hora'  => '',
        'data_fim_hora'      => '', 'periodo_br'      => '', 'local_completo'    => '',
        'detalhes_evento'    => '', 'exibir_card_info' => 1, 'exibir_intro' => 1,
        'capa_evento'        => '',
    ];

    try {
        $stmt = $pdo->query("
            SELECT nome_evento, data_inicio, data_fim,
                   local_nome, local_cidade, local_estado,
                   produtor_nome, produtor_cpf_cnpj, produtor_endereco,
                   produtor_email, produtor_telefone1, produtor_telefone2,
                   detalhes_evento, exibir_card_info, exibir_intro, capa_evento
            FROM info_evento WHERE id = 1 LIMIT 1
        ");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    } catch (Throwable $e) {
        return $defaults;
    }

    if (!$row) return $defaults;

    $tz = new DateTimeZone('America/Porto_Velho');
    $dataInicioBr = $dataInicioHora = $dataFimBr = $dataFimHora = $periodoBr = '';

    if (!empty($row['data_inicio'])) {
        try {
            $dtI = new DateTime($row['data_inicio'], $tz);
            $dataInicioBr   = strftime_compat($dtI, 'd \d\e F', 'pt_BR');
            $dataInicioHora = $dtI->format('H\hi');
        } catch (Throwable $e) {}
    }
    if (!empty($row['data_fim'])) {
        try {
            $dtF = new DateTime($row['data_fim'], $tz);
            $dataFimBr   = strftime_compat($dtF, 'd \d\e F', 'pt_BR');
            $dataFimHora = $dtF->format('H\hi');
        } catch (Throwable $e) {}
    }
    if ($dataInicioBr) {
        $periodoBr = $dataInicioBr;
        if ($dataInicioHora) {
            $periodoBr .= ' · ' . $dataInicioHora;
            if ($dataFimHora) $periodoBr .= ' – ' . $dataFimHora;
        }
    }

    $localCompleto = '';
    $partes = array_filter([
        $row['local_nome']   !== '' ? $row['local_nome']   : null,
        $row['local_cidade'] !== '' ? $row['local_cidade'] : null,
    ]);
    if (!empty($partes)) {
        $localCompleto = implode(' · ', $partes);
        if (!empty($row['local_estado'])) $localCompleto .= ', ' . $row['local_estado'];
    }

    return array_merge($defaults, [
        'nome_evento'        => (string)($row['nome_evento']        ?? ''),
        'data_inicio'        => (string)($row['data_inicio']        ?? ''),
        'data_fim'           => (string)($row['data_fim']           ?? ''),
        'local_nome'         => (string)($row['local_nome']         ?? ''),
        'local_cidade'       => (string)($row['local_cidade']       ?? ''),
        'local_estado'       => (string)($row['local_estado']       ?? ''),
        'produtor_nome'      => (string)($row['produtor_nome']      ?? ''),
        'produtor_cpf_cnpj'  => (string)($row['produtor_cpf_cnpj']  ?? ''),
        'produtor_endereco'  => (string)($row['produtor_endereco']  ?? ''),
        'produtor_email'     => (string)($row['produtor_email']     ?? ''),
        'produtor_telefone1' => (string)($row['produtor_telefone1'] ?? ''),
        'produtor_telefone2' => (string)($row['produtor_telefone2'] ?? ''),
        'detalhes_evento'    => (string)($row['detalhes_evento']    ?? ''),
        'exibir_card_info'   => (int)($row['exibir_card_info']    ?? 1),
        'exibir_intro'       => (int)($row['exibir_intro']        ?? 1),
        'capa_evento'        => (string)($row['capa_evento']       ?? ''),
        'data_inicio_br'     => $dataInicioBr,
        'data_fim_br'        => $dataFimBr,
        'data_inicio_hora'   => $dataInicioHora,
        'data_fim_hora'      => $dataFimHora,
        'periodo_br'         => $periodoBr,
        'local_completo'     => $localCompleto,
    ]);
}

/**
 * Formata uma DateTime em pt-BR sem depender da extensão intl nem do locale do SO.
 */
function strftime_compat(DateTime $dt, string $format, string $locale = 'pt_BR'): string
{
    $meses = [
        'pt_BR' => [
            1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',
            5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',
            9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro',
        ],
    ];
    $map    = $meses[$locale] ?? $meses['pt_BR'];
    $mes    = (int)$dt->format('n');
    $result = '';
    $chars  = str_split($format);
    $escape = false;
    foreach ($chars as $c) {
        if ($escape) { $result .= $c; $escape = false; continue; }
        if ($c === '\\') { $escape = true; continue; }
        $result .= ($c === 'F') ? ($map[$mes] ?? $dt->format('F')) : $dt->format($c);
    }
    return $result;
}

/**
 * Busca as perguntas ativas do FAQ, ordenadas por campo 'ordem'.
 * Retorna array vazio silenciosamente se a tabela ainda não existir.
 */
function fetch_faq(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT id, icone, pergunta, resposta
            FROM faq_perguntas
            WHERE ativo = 1 AND deleted_at IS NULL
            ORDER BY ordem ASC, id ASC
        ");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
    return array_map(static function (array $row): array {
        return [
            'id'       => (int)$row['id'],
            'icone'    => (string)($row['icone']    ?? 'help'),
            'pergunta' => (string)($row['pergunta'] ?? ''),
            'resposta' => (string)($row['resposta'] ?? ''),
        ];
    }, $rows);
}

/**
 * Busca as atrações ativas do line-up, ordenadas por nível (headliner primeiro).
 * Retorna array vazio silenciosamente se a tabela ainda não existir.
 */
function fetch_lineup(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT id, nome, nivel, descricao
            FROM lineup_atracoes
            WHERE ativo = 1 AND deleted_at IS NULL
            ORDER BY nivel ASC, ordem ASC, nome ASC
        ");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
    return array_map(static function (array $row): array {
        return [
            'id'        => (int)$row['id'],
            'nome'      => (string)$row['nome'],
            'nivel'     => (int)$row['nivel'],
            'descricao' => (string)($row['descricao'] ?? ''),
        ];
    }, $rows);
}
