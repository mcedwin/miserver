<?php
/**
 * Layout para páginas de autenticación y setup (sin menú lateral).
 * Variables: $title, $content.
 */
$flashes = flash_pull();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> &middot; Mi Server</title>
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body class="auth-page">
<div class="auth-wrap">
  <div class="auth-card">
    <h2>Mi Server</h2>
    <p class="auth-sub">Panel de control</p>
    <?php if (!empty($flashes)): ?>
      <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?= e($f['type'] ?? 'ok') ?>"><?= $f['msg'] ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?= $content ?>
  </div>
  <p class="auth-foot">&copy; <?= date('Y') ?> Mi Server</p>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>