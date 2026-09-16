<?php /** views/domains/index.php */ ?>
<?php $isAdmin = ($u['role'] ?? '') === 'admin'; ?>
<div class="section-header">
  <h3>Dominios</h3>
</div>

<form class="card mb-1" method="post" action="<?= url('domains') ?>" data-ajax="1">
  <?= csrf_field() ?>
  <h4>Añadir dominio</h4>
  <div class="row-form">
    <?php if ($isAdmin): ?>
      <select class="form-control" name="user_id">
        <?php foreach ($users as $x): ?>
          <option value="<?= (int)$x['id'] ?>"><?= e($x['user']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <input class="form-control" name="domain" placeholder="ej: proyecto.com" required>
    <input class="form-control" name="folder" placeholder="carpeta (default: nombre del dominio)">
    <p class="muted">El sitio se sirve desde <code>/home/&lt;usuario&gt;/&lt;carpeta&gt;</code> (DocumentRoot de Apache).</p>
    <label class="check-line"><input type="checkbox" name="ssl" value="1" checked> &nbsp;Instalar SSL (Let's Encrypt)</label>
    <button class="btn btn-primary" type="submit">Crear</button>
  </div>
</form>

<div class="table-wrap">
<table class="table">
  <thead>
    <tr><th>Dominio</th><th>Usuario</th><th>Carpeta</th><th>SSL</th><th>Estado</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $d): ?>
      <tr>
        <td><a href="http://<?= e($d['domain']) ?>" target="_blank"><?= e($d['domain']) ?></a></td>
        <td><?= e($d['uname'] ?? '') ?></td>
        <td class="mono"><?= e($d['folder']) ?></td>
        <td>
          <button class="btn btn-xs <?= $d['ssl']?'btn-info':'btn-light' ?>" data-post="<?= url('domains/'.$d['id'].'/ssl') ?>" data-csrf="<?= csrf_token() ?>">
            <?= e($d['ssl'] ? 'SSL activo' : 'Activar SSL') ?>
          </button>
        </td>
        <td>
          <button class="btn btn-xs <?= $d['enabled']?'btn-info':'btn-light' ?>" data-post="<?= url('domains/'.$d['id'].'/toggle') ?>" data-csrf="<?= csrf_token() ?>">
            <?= $d['enabled'] ? 'Activo' : 'Inactivo' ?>
          </button>
        </td>
        <td class="actions">
          <button class="btn btn-sm" data-post="<?= url('domains/'.$d['id'].'/dns') ?>" data-csrf="<?= csrf_token() ?>" title="Re-registrar DNS en DigitalOcean">DNS</button>
          <button class="btn btn-danger btn-sm" data-post="<?= url('domains/'.$d['id'].'/delete') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar dominio, vhost y certificado?">Eliminar</button>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>