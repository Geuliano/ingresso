<?php
/**
 * footer.php — Rodapé do site
 * $evento é injetado pelo render() via extract($data) em helpers.php.
 * Todos os campos têm fallback seguro.
 */

// Fallback caso o partial seja incluído fora do ciclo normal de render()
$evento = $evento ?? [];

$nomeEvento     = !empty($evento['nome_evento'])     ? $evento['nome_evento']     : 'Pulse Festival';
$produtorNome   = !empty($evento['produtor_nome'])   ? $evento['produtor_nome']   : '';
$produtorEmail  = !empty($evento['produtor_email'])  ? $evento['produtor_email']  : '';
$produtorTel1   = !empty($evento['produtor_telefone1']) ? $evento['produtor_telefone1'] : '';

// Ano do evento (ou ano corrente como fallback)
$anoEvento = date('Y');
if (!empty($evento['data_inicio'])) {
    try { $anoEvento = (new DateTime($evento['data_inicio']))->format('Y'); } catch (Throwable $e) {}
}

// Linha de contato do produtor
$contatoPartes = [];
if ($produtorNome  !== '') $contatoPartes[] = 'Produzido por ' . $produtorNome;
if ($produtorEmail !== '') $contatoPartes[] = $produtorEmail;
if ($produtorTel1  !== '') {
    // Formata o telefone: (DD) XXXXX-XXXX
    $d = preg_replace('/\D/', '', $produtorTel1);
    if (strlen($d) === 11) {
        $d = sprintf('(%s) %s-%s', substr($d,0,2), substr($d,2,5), substr($d,7));
    } elseif (strlen($d) === 10) {
        $d = sprintf('(%s) %s-%s', substr($d,0,2), substr($d,2,4), substr($d,6));
    }
    $contatoPartes[] = $d;
}
$linhaContato = implode(' · ', $contatoPartes);
?>
<footer class="footer">
  <div>
    <strong><?= e($nomeEvento) ?> <?= e($anoEvento) ?></strong>
    <?php if ($linhaContato !== ''): ?>
      <p><?= e($linhaContato) ?></p>
    <?php endif; ?>
  </div>
  <div class="footer__links">
    <a href="#">Termos</a>
    <a href="#">Privacidade</a>
    <a href="#">Suporte</a>
  </div>
</footer>
