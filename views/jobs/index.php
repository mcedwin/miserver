<?php /** views/jobs/index.php */ ?>
<div class="section-header">
  <h3>Tareas en segundo plano</h3>
  <form method="post" action="<?= url('jobs/clear') ?>" data-ajax="1">
    <?= csrf_field() ?>
    <button class="btn" type="submit">Limpiar tarea antigua</button>
  </form>
</div>
<p class="muted mb-1">Las tareas como "SSL" se ejecutan sin bloquear el panel. Esta página se refresca sola cada 5 segundos.</p>
<div class="table-wrap">
<table class="table" id="jobtable">
  <thead><tr><th>#</th><th>Tipo</th><th>Objetivo</th><th>Estado</th><th>Inicio</th><th>Salida</th></tr></thead>
  <tbody>
    <?php foreach ($jobs as $j): ?>
      <tr data-job="<?= (int)$j['id'] ?>">
        <td><a href="<?= url('jobs/'.$j['id']) ?>">#<?= (int)$j['id'] ?></a></td>
        <td class="mono"><?= e($j['kind']) ?></td>
        <td><?= e($j['target']) ?></td>
        <td><span class="badge badge-<?= $j['status']==='done'?'ok':($j['status']==='failed'?'err':'warn') ?> jstatus"><?= e($j['status']) ?></span></td>
        <td class="muted"><?= e($j['started_at'] ?? $j['created_at']) ?></td>
        <td>
          <?php if ($j['output']): ?>
            <a class="btn btn-xs" href="<?= url('jobs/'.$j['id']) ?>">ver salida</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<script>
  setInterval(()=>{
    fetch('<?= url('jobs/poll') ?>', {headers:{'Accept':'application/json'}})
      .then(r=>r.json()).then(d=>{
        (d.jobs||[]).forEach(j=>{
          const tr=document.querySelector('#jobtable tr[data-job="'+j.id+'"]');
          if(!tr) return;
          const s=tr.querySelector('.jstatus');
          if(s){ s.textContent=j.status; s.className='badge badge-'+(j.status==='done'?'ok':(j.status==='failed'?'err':'warn'))+' jstatus'; }
        });
      }).catch(()=>{});
  },5000);
</script>