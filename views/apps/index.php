<?php /** views/apps/index.php */ ?>
<?php $isAdmin = ($u['role'] ?? '') === 'admin'; ?>
<div class="section-header">
  <h3>Aplicaciones</h3>
  <a class="btn btn-primary" href="<?= url('apps/create') ?>">+ Nueva aplicación</a>
</div>

<?php if ($isAdmin && !empty($users)): ?>
  <div class="row-form mb-1">
    <label class="inline-label">Usuario:
      <select class="form-control" onchange="location.href='<?= url('apps') ?>?u='+this.value">
        <option value="all" <?= query('u')==='all'?'selected':'' ?>>Todos</option>
        <?php foreach ($users as $x): ?>
          <option value="<?= (int)$x['id'] ?>" <?= query('u')!=='' && (int)$x['id']===(int)$ctx['id']?'selected':'' ?>><?= e($x['user']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
<?php endif; ?>

<div class="table-wrap">
<table class="table">
  <thead>
    <tr><th>Nombre</th><th>Usuario</th><th>Carpeta</th><th>Tipo</th><th>DocumentRoot</th><th>PHP</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $a): ?>
      <tr>
        <td><?= e($a['name']) ?></td>
        <td><?= e($a['uname']) ?></td>
        <td class="mono"><?= e($a['folder']) ?></td>
        <td><span class="badge badge-sm <?= $a['project_type'] ? 'badge-ok' : 'badge-off' ?>"><?= e($a['project_type'] ?: '—') ?></span></td>
        <td class="mono"><?= e($a['document_root'] ?: '(raíz)') ?></td>
        <td><?= e($a['php_version'] ?: 'sistema') ?></td>
        <td class="actions" style="justify-content:flex-end">
          <button class="btn btn-xs btn-info" data-post="<?= url('apps/'.$a['id'].'/deploy') ?>" data-csrf="<?= csrf_token() ?>" title="Composer + caches">Desplegar</button>
          <button class="btn btn-xs btn-info" data-post="<?= url('apps/'.$a['id'].'/pull') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Hacer git pull de <?= e($a['name']) ?> y desplegar?" title="git pull + despliegue">Pull</button>
          <button class="btn btn-xs" data-post="<?= url('apps/'.$a['id'].'/migrate') ?>" data-csrf="<?= csrf_token() ?>">Migrar</button>
          <a class="btn btn-xs" href="<?= url('apps/'.$a['id'].'/env') ?>">.env</a>
          <button class="btn btn-xs" data-post="<?= url('apps/'.$a['id'].'/env-from-example') ?>" data-csrf="<?= csrf_token() ?>">Crear .env desde .env.example</button>
          <button class="btn btn-xs" data-post="<?= url('apps/'.$a['id'].'/reinstall') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Borrar y volver a clonar <?= e($a['name']) ?>? Se conserva el .env.">Reinstalar</button>
          <a class="btn btn-xs" href="<?= url('apps/'.$a['id'].'/edit') ?>">Editar</a>
          <button class="btn btn-xs btn-danger" data-post="<?= url('apps/'.$a['id'].'/delete') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar la aplicación? Los archivos NO se borrarán.">Eliminar</button>
          <button class="btn btn-xs btn-danger" data-post="<?= url('apps/'.$a['id'].'/delete') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar <?= e($a['name']) ?> Y borrar /home/<?= e($a['uname']) ?>/<?= e($a['folder']) ?>?" data-extra-delete_files="1">Eliminar todo</button>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="muted mt-1">Una aplicación es un proyecto clonado que puede servirse desde uno o varios dominios/subdominios.</p>