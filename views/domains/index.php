<?php /** views/domains/index.php */ ?>
<?php $isAdmin = ($u['role'] ?? '') === 'admin'; ?>
<div class="section-header">
  <h3>Dominios</h3>
</div>

<?php /* ---- Dominio desde aplicación existente ---- */ ?>
<?php if (!empty($apps)): ?>
<form class="card mb-1" method="post" action="<?= url('domains') ?>" data-ajax="1">
  <?= csrf_field() ?>
  <h4>Crear dominio desde una aplicación</h4>
  <div class="row-form">
    <select class="form-control" name="app_id" required style="flex:2">
      <option value="">— Selecciona una aplicación —</option>
      <?php foreach ($apps as $a): ?>
        <option value="<?= (int)$a['id'] ?>"><?= $a['uname'] ? e($a['uname']) . ' / ' : '' ?><?= e($a['name']) ?> (<?= e($a['folder']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <input class="form-control" name="domain" placeholder="dominio o subdominio" required>
    <label class="check-line"><input type="checkbox" name="ssl" value="1" checked> &nbsp;SSL</label>
    <button class="btn btn-primary" type="submit">Crear dominio</button>
  </div>
  <p class="muted">Usa una <a href="<?= url('apps') ?>">aplicación</a> ya clonada. Para cambiar la carpeta o DocumentRoot después, editá el dominio (pasará a configuración manual).</p>
</form>
<?php endif; ?>

<?php /* ---- Dominio manual (sin repo) ---- */ ?>
<form class="card mb-1" method="post" action="<?= url('domains') ?>" data-ajax="1">
  <?= csrf_field() ?>
  <h4>Añadir dominio manual</h4>
  <div class="row-form">
    <?php if ($isAdmin): ?>
      <select class="form-control" name="user_id">
        <?php foreach ($users as $x): ?>
          <option value="<?= (int)$x['id'] ?>" <?= (int)$x['id']===(int)$ctx['id']?'selected':'' ?>><?= e($x['user']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <input class="form-control" name="domain" placeholder="ej: proyecto.com" required>
    <input class="form-control" name="folder" placeholder="carpeta (default: dominio)" value="">
    <input class="form-control" name="document_root" placeholder="DocumentRoot (vacío = carpeta base)">
    <input class="form-control" name="php_version" placeholder="versión PHP (ej: 8.1)">
    <label class="check-line"><input type="checkbox" name="ssl" value="1" checked> &nbsp;SSL</label>
    <button class="btn btn-primary" type="submit">Crear</button>
  </div>
  <p class="muted">Apunta a una carpeta dentro de <code>/home/&lt;usuario&gt;</code>.</p>
</form>

<div class="table-wrap">
<table class="table">
  <thead>
    <tr><th>Dominio</th><th>Usuario</th><th>Carpeta</th><th>DocumentRoot</th><th>PHP</th><th>SSL</th><th>Estado</th><th class="actions" style="justify-content:flex-end"></th></tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $d): ?>
      <tr>
        <td><a href="http://<?= e($d['domain']) ?>" target="_blank"><?= e($d['domain']) ?></a></td>
        <td><?= e($d['uname'] ?? '') ?></td>
        <td class="mono">
          <?php if (!empty($d['app_id'])): ?>
            <span class="badge badge-sm badge-ok">app</span> <?= e($d['app_name'] ?? '') ?>
          <?php endif; ?>
          <?= e($d['folder']) ?>
        </td>
        <td class="mono"><?= e((string)($d['document_root'] ?: $d['folder'])) ?></td>
        <td>
          <?= e($d['php_version'] ?: 'sistema') ?>
          <?php if (!empty($d['php_upload_max']) || !empty($d['php_post_max']) || !empty($d['php_memory_limit'])): ?>
            <span class="badge badge-sm badge-ok" title="Límites PHP personalizados">PHP lim</span>
          <?php endif; ?>
        </td>
        <td>
          <?php $ssi = $d['ssl_info'] ?? ['status' => 'missing', 'exp' => '', 'days' => 0]; ?>
          <button class="btn btn-xs <?= $d['ssl'] && ($ssi['status'] ?? '') === 'ok' ? 'btn-info' : 'btn-light' ?>" data-post="<?= url('domains/'.$d['id'].'/ssl') ?>" data-csrf="<?= csrf_token() ?>">
            <?php if (($ssi['status'] ?? '') === 'ok'): ?>
              SSL <?= (int)($ssi['days'] ?? 0) ?>d
            <?php else: ?>
              <?= e($d['ssl'] ? 'SSL pendiente' : 'Activar SSL') ?>
            <?php endif; ?>
          </button>
          <?php if (($ssi['status'] ?? '') === 'ok' && ($ssi['exp'] ?? '') !== ''): ?>
            <span class="muted" style="font-size:.75rem;display:block">vence <?= e($ssi['exp']) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <button class="btn btn-xs <?= $d['enabled']?'btn-info':'btn-light' ?>" data-post="<?= url('domains/'.$d['id'].'/toggle') ?>" data-csrf="<?= csrf_token() ?>">
            <?= $d['enabled'] ? 'Activo' : 'Inactivo' ?>
          </button>
        </td>
        <td class="actions" style="justify-content:flex-end">
          <a class="btn btn-xs" href="<?= url('domains/'.$d['id'].'/edit') ?>" title="Editar configuración">Editar</a>
          <a class="btn btn-xs" href="<?= url('domains/'.$d['id'].'/logs') ?>" title="Ver logs de Apache">Logs</a>
          <button class="btn btn-xs" data-post="<?= url('domains/'.$d['id'].'/dns') ?>" data-csrf="<?= csrf_token() ?>" title="Re-registrar DNS">DNS</button>
          <button class="btn btn-xs btn-danger" data-post="<?= url('domains/'.$d['id'].'/delete') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar dominio, vhost y certificado?">Eliminar</button>
          <button class="btn btn-xs btn-danger" data-post="<?= url('domains/'.$d['id'].'/delete') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar dominio, vhost, certificado Y carpeta /home/<?= e($d['uname']) ?>/<?= e($d['folder']) ?>?" data-extra-delete_files="1" title="Eliminar también los archivos">Eliminar todo</button>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
