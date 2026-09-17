<?php /** views/domains/index.php */ ?>
<?php $isAdmin = ($u['role'] ?? '') === 'admin'; ?>
<div class="section-header">
  <h3>Dominios</h3>
</div>

<?php /* ---- Formulario manual (sin repositorio) ---- */ ?>
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
    <input class="form-control" name="folder" placeholder="carpeta (default: dominio)" value="">
    <input class="form-control" name="document_root" placeholder="DocumentRoot (vacío = carpeta base)">
    <input class="form-control" name="php_version" placeholder="versión PHP (ej: 8.1)">
    <p class="muted">El sitio se sirve desde <code>/home/&lt;usuario&gt;/&lt;carpeta&gt;</code> (DocumentRoot de Apache). Puedes cambiar el DocumentRoot a una subcarpeta p. ej. <code>public</code>.</p>
    <label class="check-line"><input type="checkbox" name="ssl" value="1" checked> &nbsp;Instalar SSL (Let's Encrypt)</label>
    <button class="btn btn-primary" type="submit">Crear</button>
  </div>
</form>

<?php /* ---- Crear aplicación desde GitHub ---- */ ?>
<form class="card mb-1" method="post" action="<?= url('domains') ?>" data-ajax="1">
  <?= csrf_field() ?>
  <h4>Crear aplicación desde GitHub</h4>
  <div class="row-form">
    <?php if ($isAdmin): ?>
      <select class="form-control" name="user_id">
        <?php foreach ($users as $x): ?>
          <option value="<?= (int)$x['id'] ?>"><?= e($x['user']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <input class="form-control" name="git_url" placeholder="URL repositorio (https://github.com/usuario/proyecto)" required style="flex:2">
    <input class="form-control" name="git_branch" placeholder="rama (default: main)" value="main">
    <input class="form-control" name="domain" placeholder="dominio o subdominio (ej: app.midominio.com)" required>
    <input class="form-control" name="folder" placeholder="carpeta destino (default: dominio)">
    <input class="form-control" name="project_path" placeholder="ruta proyecto dentro del repo (opcional, p.ej. src)">
    <input class="form-control" name="document_root" placeholder="DocumentRoot (vacío = auto: Laravel → public, WordPress/PHP → raíz)">
    <input class="form-control" name="php_version" placeholder="versión PHP (ej: 8.1, vacío = sistema)">
    <p class="muted">Se clonará en <code>/home/&lt;usuario&gt;/&lt;carpeta&gt;</code>. Tipo detectado: <b>Laravel</b> (artisan + public/index.php → <code>/public</code>), <b>WordPress</b> (wp-config.php), <b>PHP</b> o <b>Otro</b>.</p>
    <label class="check-line"><input type="checkbox" name="ssl" value="1" checked> &nbsp;SSL</label>
    <button class="btn btn-primary" type="submit">Clonar y crear</button>
  </div>
</form>

<div class="table-wrap">
<table class="table">
  <thead>
    <tr><th>Dominio</th><th>Usuario</th><th>Carpeta</th><th>DocumentRoot</th><th>Tipo</th><th>SSL</th><th>Estado</th><th class="actions" style="justify-content:flex-end"></th></tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $d): ?>
      <?php $isApp = ($d['git_url'] ?? '') !== '' || ($d['project_type'] ?? '') !== ''; ?>
      <tr>
        <td>
          <a href="http://<?= e($d['domain']) ?>" target="_blank"><?= e($d['domain']) ?></a>
          <?php if (($d['git_url'] ?? '') !== ''): ?>
            <span class="mono muted" style="font-size:.75rem;display:block;max-width:260px;overflow:hidden;text-overflow:ellipsis" title="<?= e($d['git_url']) ?>"><?= e($d['git_url']) ?></span>
          <?php endif; ?>
        </td>
        <td><?= e($d['uname'] ?? '') ?></td>
        <td class="mono"><?= e($d['folder']) ?></td>
        <td class="mono"><?= e(domain_docroot($d)) ?></td>
        <td><span class="badge badge-sm <?= ($d['project_type'] ?? '') ? 'badge-ok' : 'badge-off' ?>"><?= e($d['project_type'] ?: '—') ?></span></td>
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
        <td class="actions" style="justify-content:flex-end">
          <?php if ($isApp): ?>
            <button class="btn btn-xs btn-info" data-post="<?= url('domains/'.$d['id'].'/deploy') ?>" data-csrf="<?= csrf_token() ?>" title="Composer + caches + migraciones (Laravel u otra app con composer)">Desplegar</button>
            <a class="btn btn-xs" href="<?= url('domains/'.$d['id'].'/env') ?>" title="Editar archivo .env">.env</a>
            <button class="btn btn-xs" data-post="<?= url('domains/'.$d['id'].'/detect') ?>" data-csrf="<?= csrf_token() ?>" title="Re-detectar tipo de proyecto y actualizar DocumentRoot">Detectar</button>
          <?php endif; ?>
          <a class="btn btn-xs" href="<?= url('domains/'.$d['id'].'/edit') ?>" title="Editar configuración de aplicación">Editar</a>
          <button class="btn btn-xs" data-post="<?= url('domains/'.$d['id'].'/dns') ?>" data-csrf="<?= csrf_token() ?>" title="Re-registrar DNS en DigitalOcean">DNS</button>
          <button class="btn btn-xs btn-danger" data-post="<?= url('domains/'.$d['id'].'/delete') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar dominio, vhost y certificado?">Eliminar</button>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
