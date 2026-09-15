<?php /** views/home/index.php — Panel principal */ ?>
<?php
$u = current_user();
?>
<div class="grid-cards">
  <div class="card stat">
    <div class="stat-val"><?= (int)($stats['usuarios'] ?? 0) ?></div>
    <div class="stat-lbl">Usuarios</div>
  </div>
  <div class="card stat">
    <div class="stat-val"><?= (int)($stats['dominios'] ?? 0) ?></div>
    <div class="stat-lbl">Dominios</div>
  </div>
  <div class="card stat">
    <div class="stat-val"><?= (int)($stats['bases'] ?? 0) ?></div>
    <div class="stat-lbl">Bases de datos</div>
  </div>
  <div class="card stat <?= ($stats['tareas'] ?? 0) > 0 ? 'stat-warn' : '' ?>">
    <div class="stat-val"><?= (int)($stats['tareas'] ?? 0) ?></div>
    <div class="stat-lbl">Tareas activas</div>
  </div>
</div>

<?php if (!empty($sites)): ?>
<section class="mb-2">
  <h3>Sitios</h3>
  <div class="list-group">
    <?php foreach ($sites as $s): ?>
      <div class="list-row">
        <span class="mono"><?= e($s['domain']) ?></span>
        <span class="muted">/home/<?= e($s['uname']) ?>/<?= e($s['folder']) ?></span>
        <span class="badge badge-sm <?= $s['ssl'] ? 'badge-ok' : 'badge-off' ?>"><?= $s['ssl'] ? 'SSL' : 'sin SSL' ?></span>
        <span class="badge badge-sm <?= $s['enabled'] ? 'badge-ok' : 'badge-off' ?>"><?= $s['enabled'] ? 'on' : 'off' ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($jobs)): ?>
<section class="mb-2">
  <h3>Últimas tareas</h3>
  <div class="list-group">
    <?php foreach ($jobs as $j): ?>
      <div class="list-row">
        <span class="badge badge-sm <?= ($j['status']==='done')?'badge-ok':($j['status']==='failed'?'badge-err':'badge-warn') ?>"><?= e($j['status']) ?></span>
        <span class="mono"><?= e($j['kind']) ?></span>
        <span><?= e($j['target']) ?></span>
        <span class="muted"><?= e($j['created_at']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <a class="btn btn-sm" href="<?= url('jobs') ?>">Ver todas</a>
</section>
<?php endif; ?>

<?php if (!empty($backups)): ?>
<section class="mb-2">
  <div class="section-header">
    <h3>Backups</h3>
    <button class="btn btn-primary" data-post="<?= url('backup') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Crear un backup completo (archivos + carpetas + bases de datos)?">
      <svg class="ic"><use href="#i-upload"/></svg> Crear backup
    </button>
  </div>
  <div class="list-group scroll-y" style="max-height:180px">
    <?php foreach ($backups as $b): ?>
      <div class="list-row">
        <span class="mono" title="<?= e(implode(' ',$b)) ?>"><?= e($b[1] ?? '?') ?></span>
        <span><?= e($b[2] ?? '') ?></span>
        <a class="btn btn-sm" href="<?= url('download?f=' . urlencode($b[count($b)-1] ?? '')) ?>">descargar</a>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted mt-1">Cada click crea un respaldo con los homes (archivos y carpetas) y las bases de datos en <code><?= e(env('BACKUP_DIR', '/var/backups/miserver')) ?></code>. El progreso lo ves en Tareas.</p>
</section>
<?php else: ?>
<section class="mb-2">
  <div class="section-header">
    <h3>Backups</h3>
    <button class="btn btn-primary" data-post="<?= url('backup') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Crear un backup completo (archivos + carpetas + bases de datos)?">
      <svg class="ic"><use href="#i-upload"/></svg> Crear backup
    </button>
  </div>
  <p class="muted">Aún no hay backups. Pulsa <b>Crear backup</b> para respaldar archivos, carpetas y bases de datos.</p>
</section>
<?php endif; ?>