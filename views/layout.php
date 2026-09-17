<?php
/**
 * Layout principal con sidebar + topbar.
 * Variables requeridas: $title, $active, $u (current_user), $content.
 */
$flashes = flash_pull();
$cfg = db_config();
$siteName = $cfg['panel_name'] ?: 'Mi Server';
$items = menu_items();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> &middot; <?= e($siteName) ?></title>
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
<!-- iconos SVG escondidos -->
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute;clip:rect(0 0 0 0);overflow:hidden">
  <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 12l9-8 9 8"/><path d="M5 10v9a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1v-9"/></symbol>
  <symbol id="i-users" viewBox="0 0 24 24"><circle cx="8" cy="7" r="3"/><circle cx="17" cy="7" r="3"/><path d="M2 21v-1a4 4 0 014-4h6a4 4 0 014 4v1"/></symbol>
  <symbol id="i-database" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v5c0 1.7 3.6 3 8 3s8-1.3 8-3v-5"/></symbol>
  <symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M2 12h20M12 2a15 15 0 010 20M12 2a15 15 0 000 20"/></symbol>
  <symbol id="i-folder" viewBox="0 0 24 24"><path d="M2 5a1 1 0 011-1h6l2 2h10a1 1 0 011 1v11a1 1 0 01-1 1H3a1 1 0 01-1-1V5z"/></symbol>
  <symbol id="i-disk" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2" fill="currentColor"/><path d="M4 12h2M18 12h2M12 4v2M12 18v2"/></symbol>
  <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></symbol>
  <symbol id="i-tasks" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h10"/><circle cx="7" cy="6" r="1" fill="currentColor"/><circle cx="7" cy="12" r="1" fill="currentColor"/><circle cx="7" cy="18" r="1" fill="currentColor"/></symbol>
  <symbol id="i-cog" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1l2.1-2.1M17 7l2.1-2.1"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
  <symbol id="i-trash" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M5 6l1 14h12l1-14"/><path d="M10 11v5M14 11v5"/></symbol>
  <symbol id="i-edit" viewBox="0 0 24 24"><path d="M15 3l6 6L9 21H3v-6L15 3z"/></symbol>
  <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 3v12M5 12l7 7 7-7"/><path d="M5 20h14"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></symbol>
  <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 005.64 5.64L1 10M23 14l-4.64 4.36A9 9 0 013.51 15"/></symbol>
  <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 17V5M5 10l7-7 7 7"/><path d="M5 20h14"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.4-4.4"/></symbol>
</svg>

<div class="shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand"><svg class="ic-sm"><use href="#i-cog"/></svg> <?= e($siteName) ?></div>
    <nav>
      <?php foreach ($items as $it): ?>
        <a class="nav-item <?= $active === $it['active'] ? 'on' : '' ?>"
           href="<?= url($it['url']) ?>">
          <svg class="ic"><use href="#i-<?= $it['icon'] ?>"/></svg>
          <?= e($it['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">&copy; <?= e(date('Y')) ?> <?= e($siteName) ?></div>
  </aside>
  <main class="main">
    <header class="topbar">
      <button class="icon-btn menu-toggle" data-toggle="#sidebar">☰</button>
      <div class="topbar-title"><?= e($title) ?></div>
      <div class="topbar-right">
        <span class="topbar-user"><?= e($u['user']) ?> (<?= e($u['role']) ?>)</span>
        <form method="post" action="<?= url('logout') ?>" class="inline">
          <?= csrf_field() ?>
          <button class="btn btn-ghost btn-sm" type="submit">Salir</button>
        </form>
      </div>
    </header>
    <?php if (!empty($flashes)): ?>
      <div class="flash-wrap">
        <?php foreach ($flashes as $f): ?>
          <div class="flash flash-<?= e($f['type'] ?? 'ok') ?>"><?= $f['msg'] ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="content"><?= $content ?></div>
  </main>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>