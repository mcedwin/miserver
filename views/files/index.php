<?php /** views/files/index.php — gestor de archivos */ ?>
<?php $isAdmin = ($u['role'] ?? '') === 'admin'; ?>
<div class="section-header">
  <h3>Archivos <span class="muted">— <?= e($ctx['user']) ?> (<?= e($dir) ?>)</span></h3>
  <?php if (!empty($users)): ?>
    <label class="inline-label">Usuario:
      <select class="form-control" onchange="location.href='<?= url('files') ?>?u='+this.value">
        <?php foreach ($users as $x): ?>
          <option value="<?= (int)$x['id'] ?>" <?= (int)$x['id']===(int)$ctx['id']?'selected':'' ?>><?= e($x['user']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
</div>

<nav class="crumbs">
  <a href="<?= url('files?u='.$ctx['id']) ?>">home</a>
  <?php foreach ($crumbs as $c): ?>
    <span>/</span>
    <a href="<?= url('files?u='.$ctx['id'].'&p='.urlencode($c['path'])) ?>"><?= e($c['label']) ?></a>
  <?php endforeach; ?>
</nav>

<div class="row-form mb-1">
  <form class="inline mr-1" method="post" action="<?= url('files/mkdir') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="u" value="<?= (int)$ctx['id'] ?>">
    <input type="hidden" name="p" value="<?= e($rel) ?>">
    <input class="form-control" name="name" placeholder="Nueva carpeta" required>
    <button class="btn" type="submit"><svg class="ic"><use href="#i-plus"/></svg> Crear</button>
  </form>
  <form class="inline" method="post" action="<?= url('files/upload') ?>" enctype="multipart/form-data" data-ajax="1">
    <?= csrf_field() ?>
    <input type="hidden" name="u" value="<?= (int)$ctx['id'] ?>">
    <input type="hidden" name="p" value="<?= e($rel) ?>">
    <input class="form-control" type="file" name="up" required>
    <button class="btn" type="submit"><svg class="ic"><use href="#i-upload"/></svg> Subir</button>
  </form>
  <a class="btn btn-ghost btn-sm mr-1" href="<?= url('files?u='.$ctx['id'].'&p='.urlencode($rel).($showHidden?'':'&h=1')) ?>"><?= $showHidden ? 'Ocultar archivos ocultos' : 'Mostrar ocultos' ?></a>
</div>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Nombre</th><th>Tamaño</th><th>Modificado</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($entries['dirs'] as $d): ?>
      <tr>
        <td><a href="<?= url('files?u='.$ctx['id'].'&p='.urlencode($rel=== '' ? $d['name'] : $rel.'/'.$d['name'])) ?>"><?= e($d['name']) ?>/</a></td>
        <td class="muted">—</td>
        <td class="muted"><?= $d['mtime'] ? e(date('Y-m-d H:i', (int)$d['mtime'])) : '' ?></td>
        <td class="actions">
          <button class="btn btn-sm" data-prompt="Nuevo nombre para <?= e($d['name']) ?>:" data-post="<?= url('files/rename') ?>" data-extra-u="<?= (int)$ctx['id'] ?>" data-extra-p="<?= e($rel === '' ? $d['name'] : $rel.'/'.$d['name']) ?>">Renombrar</button>
          <button class="btn btn-danger btn-sm" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar carpeta <?= e($d['name']) ?>/? (solo si está vacía)" data-post="<?= url('files/delete') ?>" data-extra-u="<?= (int)$ctx['id'] ?>" data-extra-p="<?= e($rel === '' ? $d['name'] : $rel.'/'.$d['name']) ?>">Eliminar</button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php foreach ($entries['files'] as $f): ?>
      <tr>
        <td class="mono"><?= e($f['name']) ?></td>
        <td><?= e(bytes_human((int)$f['size'])) ?></td>
        <td class="muted"><?= $f['mtime'] ? e(date('Y-m-d H:i', (int)$f['mtime'])) : '' ?></td>
        <td class="actions">
          <button class="btn btn-sm" data-prompt="Nuevo nombre para <?= e($f['name']) ?>:" data-post="<?= url('files/rename') ?>" data-extra-u="<?= (int)$ctx['id'] ?>" data-extra-p="<?= e($rel === '' ? $f['name'] : $rel.'/'.$f['name']) ?>"><svg class="ic-sm"><use href="#i-edit"/></svg> Renombrar</button>
          <?php if ($f['editable']): ?>
            <a class="btn btn-sm" href="<?= url('files/edit?u='.$ctx['id'].'&p='.urlencode($rel=== '' ? $f['name'] : $rel.'/'.$f['name'])) ?>"><svg class="ic-sm"><use href="#i-edit"/></svg> Editar</a>
          <?php endif; ?>
          <a class="btn btn-sm" href="<?= url('files/raw?u='.$ctx['id'].'&p='.urlencode($rel=== '' ? $f['name'] : $rel.'/'.$f['name'])) ?>"><svg class="ic-sm"><use href="#i-download"/></svg> Bajar</a>
          <button class="btn btn-danger btn-sm" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar <?= e($f['name']) ?>?" data-post="<?= url('files/delete') ?>" data-extra-u="<?= (int)$ctx['id'] ?>" data-extra-p="<?= e($rel === '' ? $f['name'] : $rel.'/'.$f['name']) ?>">Eliminar</button>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>