<?php /** views/jobs/show.php — Detalle completo de una tarea */ ?>
<a class="btn btn-sm" href="<?= url('jobs') ?>">← Volver</a>
<div class="card mt-1">
  <h3>Tarea #<?= (int)$job['id'] ?> · <?= e($job['kind']) ?> · <?= e($job['target']) ?></h3>
  <p>
    <span class="badge badge-<?= $job['status']==='done'?'ok':($job['status']==='failed'?'err':'warn') ?>"><?= e($job['status']) ?></span>
    <span class="muted">Creada: <?= e($job['created_at']) ?> · Inicio: <?= e($job['started_at'] ?? '—') ?> · Fin: <?= e($job['finished_at'] ?? '—') ?></span>
  </p>
  <?php if ($job['output']): ?>
    <textarea class="form-control mono" rows="32" readonly style="width:100%;font-family:Consolas,monospace;background:#111;color:#0f0"><?= e($job['output']) ?></textarea>
  <?php else: ?>
    <p class="muted">Aún no hay salida.</p>
  <?php endif; ?>
</div>