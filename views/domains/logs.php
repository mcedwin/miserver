<?php /** views/domains/logs.php — Logs de Apache para un dominio */ ?>
<a class="btn btn-sm" href="<?= url('domains') ?>">← Volver</a>
<div class="card mt-1">
  <h3>Logs de <span class="mono"><?= e($row['domain']) ?></span></h3>
  <div class="row-form mb-1">
    <a class="btn btn-sm <?= $type === 'error' ? 'btn-info' : '' ?>" href="<?= url('domains/'.$row['id'].'/logs?type=error&lines='.$lines) ?>">Error log</a>
    <a class="btn btn-sm <?= $type === 'access' ? 'btn-info' : '' ?>" href="<?= url('domains/'.$row['id'].'/logs?type=access&lines='.$lines) ?>">Access log</a>
    <span class="muted">Últimas <?= (int)$lines ?> líneas · archivo: <code>/var/log/apache2/<?= e($row['domain']) ?>-<?= e($type) ?>.log</code></span>
  </div>
  <textarea class="form-control mono" rows="28" readonly style="width:100%;font-family:Consolas,monospace;background:#111;color:#0f0"><?= e($log) ?></textarea>
</div>