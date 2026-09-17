<?php /** views/apps/env.php — editor seguro del .env de la aplicación */ ?>
<a class="btn btn-sm" href="<?= url('apps') ?>">← Volver</a>
<div class="card mt-1">
  <h3>Editar <span class="mono">.env</span> · <?= e($row['name']) ?></h3>
  <p class="muted mb-1">
    Archivo <code class="mono">/home/<?= e($owner['user']) ?>/<?= e($rel) ?></code>.
  </p>
  <?php if (!$existed): ?>
    <div class="alert-ok alert mb-1">No existe todavía. Pulsa Guardar para crearlo.</div>
  <?php endif; ?>
  <form method="post" action="<?= url('apps/'.$row['id'].'/env') ?>" class="card" style="box-shadow:none;border:0;padding:0">
    <?= csrf_field() ?>
    <textarea class="form-control mono" name="content" rows="24" spellcheck="false" style="width:100%;font-family:Consolas,monospace"><?= e($content) ?></textarea>
    <div class="row-form mt-1">
      <button class="btn btn-primary" type="submit"><svg class="ic"><use href="#i-check"/></svg> Guardar .env</button>
      <button class="btn btn-info" data-post="<?= url('apps/'.$row['id'].'/deploy') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Ejecutar Composer + caches ahora?">Desplegar</button>
      <button class="btn" data-post="<?= url('apps/'.$row['id'].'/migrate') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Ejecutar migraciones ahora?">Migrar BD</button>
    </div>
  </form>
</div>