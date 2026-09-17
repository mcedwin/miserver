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
  <p class="muted">Usa una <a href="<?= url('apps') ?>">aplicación</a> ya clonada. Puedes crear varios dominios/subdominios que apunten a la misma app o a carpetas distintas.</p>
</form>
<?php endif; ?>

<?php /* ---- Crear aplicación desde GitHub (legacy) ---- */ ?>
<form class="card mb-1" method="post" action="<?= url('domains') ?>" data-ajax="1">
  <?= csrf_field() ?>
  <h4>Crear dominio + aplicación desde GitHub</h4>
  <div class="row-form">
    <?php if ($isAdmin): ?>
      <select class="form-control" name="user_id">
        <?php foreach ($users as $x): ?>
          <option value="<?= (int)$x['id'] ?>" <?= (int)$x['id']===(int)$ctx['id']?'selected':'' ?>><?= e($x['user']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <input class="form-control" name="git_url" placeholder="URL repositorio (https://github.com/usuario/proyecto)" required style="flex:2">
    <input class="form-control" name="git_branch" placeholder="rama (default: main)" value="main">
    <input class="form-control" name="git_token" type="password" placeholder="token (solo si el repo es PRIVADO)" autocomplete="new-password">
    <input class="form-control" name="domain" placeholder="dominio o subdominio (ej: app.midominio.com)" required>
    <input class="form-control" name="folder" placeholder="carpeta destino (default: dominio)">
    <input class="form-control" name="project_path" placeholder="ruta proyecto dentro del repo (opcional, p.ej. src)">
    <input class="form-control" name="document_root" placeholder="DocumentRoot (vacío = auto: Laravel → public, WordPress/PHP → raíz)">
    <input class="form-control" name="php_version" placeholder="versión PHP (ej: 8.1, vacío = sistema)">
    <p class="muted">Clona directamente en <code>/home/&lt;usuario&gt;/&lt;carpeta&gt;</code> y despliega. Para apps reutilizables, primero crea la app en <a href="<?= url('apps') ?>">Aplicaciones</a> y luego vincúlala aquí.</p>
    <label class="check-line"><input type="checkbox" name="ssl" value="1" checked> &nbsp;SSL</label>
    <button class="btn btn-primary" type="submit">Clonar y crear</button>
  </div>
</form>

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
    <p class="muted">Sitio estático o PHP manual desde <code>/home/&lt;usuario&gt;/&lt;carpeta&gt;</code>.</p>
    <label class="check-line"><input type="checkbox" name="ssl" value="1" checked> &nbsp;SSL</label>
    <button class="btn btn-primary" type="submit">Crear</button>
  </div>
</form>

<div class="table-wrap">
<table class="table">
  <thead>
    <tr><th>Dominio</th><th>Usuario</th><th>Aplicación / Carpeta</th><th>DocumentRoot</th><th>Tipo</th><th>SSL</th><th>Estado</th><th class="actions" style="justify-content:flex-end"></th></tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $d): ?>
      <?php $eff = domain_effective($d); ?>
      <?php $isApp = ($d['app_id'] ?? 0) > 0 || ($d['git_url'] ?? '') !== '' || ($d['project_type'] ?? '') !== ''; ?>
      <tr>
        <td>
          <a href="http://<?= e($d['domain']) ?>" target="_blank"><?= e($d['domain']) ?></a>
          <?php if (($eff['git_url'] ?? '') !== ''): ?>
            <span class="mono muted" style="font-size:.75rem;display:block;max-width:260px;overflow:hidden;text-overflow:ellipsis" title="<?= e($eff['git_url']) ?>"><?= e($eff['git_url']) ?></span>
          <?php endif; ?>
        </td>
        <td><?= e($d['uname'] ?? '') ?></td>
        <td class="mono">
          <?php if (!empty($d['app_id'])): ?>
            <span class="badge badge-sm badge-ok">app</span> <?= e($eff['app_name'] ?? '') ?>
          <?php else: ?>
            <?= e($eff['folder']) ?>
          <?php endif; ?>
        </td>
        <td class="mono"><?= e(domain_docroot($d)) ?></td>
        <td><span class="badge badge-sm <?= ($eff['project_type'] ?? '') ? 'badge-ok' : 'badge-off' ?>"><?= e($eff['project_type'] ?: '—') ?></span></td>
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
          <?php if ($isApp): ?>
            <button class="btn btn-xs btn-info" data-post="<?= url('domains/'.$d['id'].'/deploy') ?>" data-csrf="<?= csrf_token() ?>" title="Composer + caches (sin migraciones)">Desplegar</button>
            <button class="btn btn-xs btn-info" data-post="<?= url('domains/'.$d['id'].'/pull') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Hacer git pull y desplegar?" title="git pull + Composer + caches">Pull</button>
            <?php if (empty($d['app_id'])): ?>
              <button class="btn btn-xs" data-post="<?= url('domains/'.$d['id'].'/reinstall') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Borrar la carpeta y volver a clonar? Se conserva el .env.">Reinstalar</button>
            <?php endif; ?>
            <button class="btn btn-xs" data-post="<?= url('domains/'.$d['id'].'/migrate') ?>" data-csrf="<?= csrf_token() ?>" title="Ejecutar migraciones">Migrar</button>
            <a class="btn btn-xs" href="<?= url('domains/'.$d['id'].'/env') ?>" title="Editar archivo .env">.env</a>
            <?php if (empty($d['app_id'])): ?>
              <button class="btn btn-xs" data-post="<?= url('domains/'.$d['id'].'/detect') ?>" data-csrf="<?= csrf_token() ?>" title="Re-detectar tipo de proyecto y actualizar DocumentRoot">Detectar</button>
            <?php endif; ?>
          <?php endif; ?>
          <a class="btn btn-xs" href="<?= url('domains/'.$d['id'].'/edit') ?>" title="Editar configuración">Editar</a>
          <a class="btn btn-xs" href="<?= url('domains/'.$d['id'].'/logs') ?>" title="Ver logs de Apache">Logs</a>
          <button class="btn btn-xs" data-post="<?= url('domains/'.$d['id'].'/dns') ?>" data-csrf="<?= csrf_token() ?>" title="Re-registrar DNS">DNS</button>
          <button class="btn btn-xs btn-danger" data-post="<?= url('domains/'.$d['id'].'/delete') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar dominio, vhost y certificado?">Eliminar</button>
          <button class="btn btn-xs btn-danger" data-post="<?= url('domains/'.$d['id'].'/delete') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar dominio, vhost, certificado Y carpeta /home/<?= e($d['uname']) ?>/<?= e($eff['folder']) ?>?" data-extra-delete_files="1" title="Eliminar también los archivos">Eliminar todo</button>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>