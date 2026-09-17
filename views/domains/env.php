<?php /** views/domains/env.php — editor seguro del .env de la aplicación */ ?>
<div class="section-header">
  <h3>Editar <span class="mono">.env</span> · <?= e($row['domain']) ?></h3>
  <div class="actions">
    <a class="btn btn-sm" href="<?= url('domains/'.(int)$row['id'].'/edit') ?>">← Configuración</a>
    <a class="btn btn-sm" href="<?= url('domains') ?>">← Dominios</a>
  </div>
</div>
<div class="card">
  <p class="muted mb-1">
    Archivo <code class="mono">/home/<?= e($owner['user']) ?>/<?= e($rel) ?></code>.
    Este editor escribe con los permisos privilegiados del panel y <b>no modifica el sistema de contraseñas</b> del panel ni de las bases de datos creadas aquí.
    Guarda las credenciales de la propia aplicación; son secretos y se almacenan en claro en disco (como es normal en un <code>.env</code>), protegido del acceso web por Apache.
    Debes añadir la variable <code>APP_KEY</code> a las aplicaciones Laravel cuando corresponda.
  </p>
  <?php if ($suggested): ?>
    <div class="alert-ok alert mb-1">No existe <code>.env</code> todavía. Se ha precargado el contenido de <code>.env.example</code> como sugerencia. Revisalo y pulsa Guardar.</div>
  <?php elseif (!$existed): ?>
    <div class="alert-ok alert mb-1">No existe todavía. Pulsa Guardar para crearlo.</div>
  <?php endif; ?>
  <form method="post" action="<?= url('domains/'.(int)$row['id'].'/env') ?>" class="card" style="box-shadow:none;border:0;padding:0">
    <?= csrf_field() ?>
    <textarea class="form-control mono" name="content" rows="24" spellcheck="false" style="width:100%;font-family:Consolas,monospace"><?= e($content) ?></textarea>
    <div class="row-form mt-1">
      <button class="btn btn-primary" type="submit"><svg class="ic"><use href="#i-check"/></svg> Guardar .env</button>
      <button class="btn btn-info" data-post="<?= url('domains/'.(int)$row['id'].'/deploy') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Ejecutar Composer + caches ahora?" title="Despliega el último estado guardado en el servidor">Desplegar</button>
      <button class="btn" data-post="<?= url('domains/'.(int)$row['id'].'/migrate') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Ejecutar las migraciones (artisan migrate / spark migrate) ahora?">Migrar BD</button>
    </div>
  </form>
</div>