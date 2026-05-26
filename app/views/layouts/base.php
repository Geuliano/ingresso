<?php send_security_headers(); ?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= e($title ?? 'Pulse Festival') ?></title>
  <meta name="csrf-token" content="<?= e(csrf_token()); ?>">
  <meta name="base-path" content="<?= e(url()); ?>">
  <!-- PWA / Mobile app feel -->
  <meta name="theme-color" content="#08080f">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Pulse">
  <meta name="mobile-web-app-capable" content="yes">
  <link rel="manifest" href="<?= url('manifest.json'); ?>">
  <link rel="apple-touch-icon" href="<?= asset('img/icon-192.png'); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@400,0..1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/icons.min.css'); ?>">
  <link rel="stylesheet" href="<?= asset('css/styles.css'); ?>">
</head>

<body>
  <div class="page">
    <?php if (!empty($showTopNav)) : ?>
      <?php include VIEWS_PATH . '/partials/top-nav.php'; ?>
    <?php endif; ?>

    <?= $content ?>

</div>

  <?php include VIEWS_PATH . '/partials/bottom-nav.php'; ?>
  <script defer src="<?= asset('js/common.js'); ?>"></script>
  <script defer src="<?= asset('js/nav.js'); ?>"></script>

  <?php if (!empty($scripts)) : ?>
    <?php foreach ($scripts as $script) : ?>
      <script defer src="<?= asset($script); ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>
</body>

</html>
