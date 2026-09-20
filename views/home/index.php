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

<?php if (!empty($partitions)): ?>
<section class="mb-2">
  <h3>Disco y particiones</h3>
  <div class="card">
    <?php foreach ($partitions as $part):
      $pct = (int) ($part['use_percent'] ?? 0);
      $barClass = $pct >= 90 ? 'err' : ($pct >= 75 ? 'warn' : '');
      $total = format_size_kb((int) ($part['size_kb'] ?? 0));
      $used = format_size_kb((int) ($part['used_kb'] ?? 0));
      $avail = format_size_kb((int) ($part['avail_kb'] ?? 0));
    ?>
      <div class="disk-row">
        <div class="disk-info">
          <div class="mono"><?= e($part['mount']) ?></div>
          <div class="muted" style="font-size:.82rem"><?= e($part['filesystem']) ?></div>
          <div class="disk-bar <?= $barClass ?>"><div style="width:<?= min(100, $pct) ?>%"></div></div>
        </div>
        <div class="disk-meta">
          <span class="disk-pct"><?= $pct ?>%</span> ·
          <?= $used ?> / <?= $total ?>
          <br><span class="muted">libre <?= $avail ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

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
