<?php
/**
 * home.php — Página inicial
 * Variável $evento injetada pelo public/index.php via render().
 * Todos os campos têm fallback seguro para exibição mesmo sem dados cadastrados.
 */

// Fallback: se a view for renderizada sem $evento (nunca deveria, mas previne warnings)
$evento = $evento ?? [];

$nomeEvento  = $evento['nome_evento']    !== '' ? $evento['nome_evento']    : 'Pulse Festival';
$localNome   = $evento['local_nome']     !== '' ? $evento['local_nome']     : '';
$localCidade = $evento['local_cidade']   !== '' ? $evento['local_cidade']   : '';
$localEstado = $evento['local_estado']   !== '' ? $evento['local_estado']   : '';
$detalhesEvento = $evento['detalhes_evento'] !== '' ? $evento['detalhes_evento'] : '';

$dataInicio  = $evento['data_inicio_br'] !== '' ? $evento['data_inicio_br'] : '';
$horaInicio  = $evento['data_inicio_hora'] !== '' ? $evento['data_inicio_hora'] : '';
$horaFim     = $evento['data_fim_hora']    !== '' ? $evento['data_fim_hora']    : '';
$dataFim     = $evento['data_fim_br']    !== '' ? $evento['data_fim_br']    : '';

// Meta-cards "Começa" e "Termina"
$metaComeça1 = $dataInicio !== '' ? $dataInicio : 'Em breve';
$metaComeça2 = $horaInicio !== '' ? $horaInicio : '';
$metaTermina1 = $dataFim   !== '' ? $dataFim    : '';
$metaTermina2 = $horaFim   !== '' ? $horaFim    : '';

// Meta-card "Onde"
$metaOnde1 = $localNome   !== '' ? $localNome   : '';
$metaOnde2 = $localCidade !== '' ? $localCidade . ($localEstado !== '' ? ', ' . $localEstado : '') : '';

// Eyebrow no topo do hero (ex: "Pulse Festival · 2025")
$eyebrow = $nomeEvento;
if ($dataInicio !== '') {
    // Extrai só o ano
    $ano = '';
    if (!empty($evento['data_inicio'])) {
        try { $ano = (new DateTime($evento['data_inicio']))->format('Y'); } catch (Throwable $e) {}
    }
    if ($ano) $eyebrow .= ' · ' . $ano;
}

// Capa do evento para o hero banner
$capaEvento = $evento['capa_evento'] ?? '';
$capaUrl    = '';
if ($capaEvento !== '') {
    $capaUrl = site_base_path() . '/admin/public/uploads/evento/' . rawurlencode(basename($capaEvento));
}
?>
<header class="hero">
  <div class="hero__banner<?= $capaUrl !== '' ? ' has-image' : '' ?>"<?= $capaUrl !== '' ? ' style="background-image:url(\'' . e($capaUrl) . '\')"' : '' ?>>
    <div class="hero__banner-bg"></div>
  </div>
  <div class="hero__top">
    <div>
      <h1><?= e($nomeEvento) ?></h1>
      <?php if ($detalhesEvento !== ''): ?>
      <p class="lede"><?= e($detalhesEvento) ?></p>
      <?php endif; ?>
      <div class="hero__meta">
        <div class="meta-card">
          <span class="meta-label">Começa</span>
          <span class="meta-value"><?= e($metaComeça1) ?></span>
          <?php if ($metaComeça2 !== ''): ?>
            <br><span class="meta-value"><?= e($metaComeça2) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($metaTermina1 !== ''): ?>
        <div class="meta-card">
          <span class="meta-label">Termina</span>
          <span class="meta-value"><?= e($metaTermina1) ?></span>
          <?php if ($metaTermina2 !== ''): ?>
            <br><span class="meta-value"><?= e($metaTermina2) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($metaOnde1 !== '' || $metaOnde2 !== ''): ?>
        <div class="meta-card meta-card--location">
          <span class="meta-label">Onde</span>
          <?php if ($metaOnde1 !== ''): ?>
            <span class="meta-value"><?= e($metaOnde1) ?></span><br>
          <?php endif; ?>
          <?php if ($metaOnde2 !== ''): ?>
            <span class="meta-value"><?= e($metaOnde2) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="hero__cta">
      <p class="cta-kicker">Checkout rápido</p>
      <p class="cta-copy">Selecione seus ingressos e créditos em 3 passos. Sem filas, sem burocracia.</p>
      <button class="btn btn-primary btn-full" data-scroll-target="#checkout">Garantir minha entrada</button>
      <div class="cta-guarantees">
        <span>Lotes limitados</span>
        <span>Voucher instantâneo</span>
        <span>Reembolso até 48h antes</span>
      </div>
    </div>
  </div>
</header>

<?php if (!empty($evento['exibir_intro'])): ?>
<section class="intro" id="intro">
  <p class="eyebrow">Line-up</p>
  <?php
  // $lineup injetado pelo public/index.php via render()
  $lineup = $lineup ?? [];
  if (!empty($lineup)):
  ?>
  <div class="intro__logos">
    <?php foreach ($lineup as $atracao): ?>
    <span class="intro__logo intro__logo--n<?= (int)$atracao['nivel'] ?>"<?= $atracao['descricao'] !== '' ? ' title="' . e($atracao['descricao']) . '"' : '' ?>><?= e($atracao['nome']) ?></span>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="intro__empty">Em breve…</p>
  <?php endif; ?>
</section>
<?php endif; ?>

<main class="layout" id="checkout">
  <section class="panel" id="tickets-section">
    <header class="panel__header">
      <div>
        <p class="eyebrow">Ingressos</p>
      </div>
    </header>
    <div class="ticket-grid" id="tickets"></div>

    <header class="panel__header panel__header--compact">
      <div>
        <p class="eyebrow">Consumo</p>
        <h3>Créditos antecipados</h3>
        <small style="color:var(--muted);font-size:13px;">Use nos bares e praça de alimentação dentro do evento.</small>
      </div>
    </header>
    <div class="credit-grid">
      <div class="credit-single">
        <div class="credit-single__header">
          <div>
            <p class="eyebrow">Voucher</p>
            <h3>Saldo de consumo</h3>
            <small style="color:var(--muted);font-size:13px;">Defina o valor do voucher entre R$ 50 e R$ 500.</small>
          </div>
        </div>
        <div class="credit-single__control">
          <input type="range" id="credit-slider" class="slider" min="0" max="500" step="50" value="0" />
          <div class="counter" id="credit-counter">
            <button aria-label="Remover crédito" data-credit-action="dec">-</button>
            <span id="credit-value">R$ 0,00</span>
            <button aria-label="Adicionar crédito" data-credit-action="inc">+</button>
          </div>
        </div>
        <div class="quick-steps" id="credit-quick"></div>
      </div>
    </div>
  </section>

  <aside class="summary" id="summary">
    <div class="summary__inner">
      <header>
        <p class="eyebrow">Resumo</p>
        <h3>Seu pedido</h3>
      </header>
      <div class="summary__list" id="summary-list"></div>

      <div class="summary__total">
        <span>Total</span>
        <strong id="summary-total">R$ 0,00</strong>
      </div>

      <button class="btn btn-primary btn-full" id="cta-buy">Garantir minha entrada</button>
      <p class="summary__footnote">Pagamento seguro no próximo passo. Você poderá revisar antes de confirmar.</p>
    </div>
  </aside>
</main>

<section class="faq" id="faq">
  <div class="pillars">
    <div class="pillar">
      <span class="pillar__icon material-symbols-outlined">bolt</span>
      <h4>Ingresso digital</h4>
      <p>QR Code instantâneo pronto para carteira digital. Sem filas, sem impressão.</p>
    </div>
    <div class="pillar">
      <span class="pillar__icon material-symbols-outlined">moving</span>
      <h4>Fila zero</h4>
      <p>Validação rápida com staff dedicado e pistas sinalizadas.</p>
    </div>
    <div class="pillar">
      <span class="pillar__icon material-symbols-outlined">theaters</span>
      <h4>Experiência premium</h4>
      <p>Bares temáticos, lounges confortáveis e ativações exclusivas.</p>
    </div>
    <div class="pillar">
      <span class="pillar__icon material-symbols-outlined">local_bar</span>
      <h4>Consumo prático</h4>
      <p>Compre créditos antes, evite filas no bar e acompanhe seu saldo pelo QR Code.</p>
    </div>
  </div>
  <div class="faq__card">
    <h3>Dúvidas rápidas</h3>
    <?php if (!empty($faq)): ?>
      <?php foreach ($faq as $item): ?>
        <?php
          $pergunta = htmlspecialchars($item['pergunta'] ?? '', ENT_QUOTES, 'UTF-8');
          $resposta = htmlspecialchars($item['resposta'] ?? '', ENT_QUOTES, 'UTF-8');
          $iconeHtml = icon_html($item['icone'] ?? 'help', '', 18);
        ?>
        <details>
          <summary>
            <span class="faq-question"><?= $iconeHtml ?><?= $pergunta ?></span>
            <span class="faq-caret material-symbols-outlined">expand_more</span>
          </summary>
          <div class="faq__answer"><?= $resposta ?></div>
        </details>
      <?php endforeach; ?>
    <?php else: ?>
      <details>
        <summary><span class="faq-question"><span class="material-symbols-outlined">help</span>Dúvidas frequentes</span><span class="faq-caret material-symbols-outlined">expand_more</span></summary>
        <div class="faq__answer">Em breve teremos respostas para suas dúvidas aqui. Fique ligado!</div>
      </details>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($evento['exibir_card_info'])): ?>
<?php
// Informações do produtor para o card
$produtorNome  = !empty($evento['produtor_nome'])      ? $evento['produtor_nome']      : '';
$produtorEmail = !empty($evento['produtor_email'])     ? $evento['produtor_email']     : '';
$produtorTel1  = !empty($evento['produtor_telefone1']) ? $evento['produtor_telefone1'] : '';

// Formata telefone
if ($produtorTel1 !== '') {
    $d = preg_replace('/\D/', '', $produtorTel1);
    if (strlen($d) === 11)      $produtorTel1 = sprintf('(%s) %s-%s', substr($d,0,2), substr($d,2,5), substr($d,7));
    elseif (strlen($d) === 10)  $produtorTel1 = sprintf('(%s) %s-%s', substr($d,0,2), substr($d,2,4), substr($d,6));
}

$anoEvento = date('Y');
if (!empty($evento['data_inicio'])) {
    try { $anoEvento = (new DateTime($evento['data_inicio']))->format('Y'); } catch (Throwable $e) {}
}
?>
<section class="event-info-card" id="sobre">
  <div class="event-info-card__inner">
    <details class="event-info-card__details">
      <summary class="event-info-card__header">
        <div>
          <p class="eyebrow">Sobre o evento</p>
        </div>
        <span class="material-symbols-outlined event-info-card__chevron">expand_more</span>
      </summary>

      <div class="event-info-card__grid">
        <?php if ($metaOnde1 !== '' || $metaOnde2 !== ''): ?>
        <div class="event-info-item">
          <span class="material-symbols-outlined">location_on</span>
          <div>
            <span class="event-info-item__label">Local</span>
            <?php if ($metaOnde1 !== ''): ?><span class="event-info-item__value"><?= e($metaOnde1) ?></span><?php endif; ?>
            <?php if ($metaOnde2 !== ''): ?><span class="event-info-item__sub"><?= e($metaOnde2) ?></span><?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($metaComeça1 !== ''): ?>
        <div class="event-info-item">
          <span class="material-symbols-outlined">calendar_today</span>
          <div>
            <span class="event-info-item__label">Data</span>
            <span class="event-info-item__value"><?= e($metaComeça1) ?><?= $metaTermina1 !== '' && $metaTermina1 !== $metaComeça1 ? ' – ' . e($metaTermina1) : '' ?></span>
            <?php if ($metaComeça2 !== ''): ?>
              <span class="event-info-item__sub"><?= e($metaComeça2) ?><?= $metaTermina2 !== '' ? ' – ' . e($metaTermina2) : '' ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($produtorEmail !== '' || $produtorTel1 !== ''): ?>
        <div class="event-info-item">
          <span class="material-symbols-outlined">support_agent</span>
          <div>
            <span class="event-info-item__label">Contato</span>
            <?php if ($produtorEmail !== ''): ?>
              <a class="event-info-item__value event-info-item__link" href="mailto:<?= e($produtorEmail) ?>"><?= e($produtorEmail) ?></a>
            <?php endif; ?>
            <?php if ($produtorTel1 !== ''): ?>
              <a class="event-info-item__sub event-info-item__link" href="tel:<?= e(preg_replace('/\D/', '', $produtorTel1)) ?>"><?= e($produtorTel1) ?></a>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($produtorNome !== ''): ?>
        <div class="event-info-item">
          <span class="material-symbols-outlined">groups</span>
          <div>
            <span class="event-info-item__label">Organização</span>
            <span class="event-info-item__value"><?= e($produtorNome) ?></span>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </details>
  </div>
</section>
<?php endif; ?>