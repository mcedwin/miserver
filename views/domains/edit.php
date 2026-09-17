<?php /** views/domains/edit.php — Configuración de dominio */ ?>
<?php $linkedApp = (int)($row['app_id'] ?? 0); ?>
<a class="btn btn-sm" href="<?= url('domains') ?>">← Volver</a>
<div class="card mt-1">
  <h3>Editar dominio <span class="mono"><?= e($row['domain']) ?></span></h3>
  <p class="muted">La configuración se guarda para regenerar el VirtualHost de Apache. <a href="<?= url('domains/'.(int)$row['id'].'/env') ?>">Editar .env</a> · este editor no toca el sistema de contraseñas del panel.</p>
  <?php $ssi = $ssl_info ?? ['status' => 'missing', 'exp' => '', 'days' => 0]; ?>
  <?php if (($ssi['status'] ?? '') === 'ok'): ?>
    <div class="alert-ok alert mb-1">Certificado SSL válido · vence el <?= e($ssi['exp']) ?> (<?= (int)($ssi['days'] ?? 0) ?> días restantes).</div>
  <?php elseif (($ssi['status'] ?? '') === 'invalid'): ?>
    <div class="alert alert-warn mb-1">Certificado SSL presente pero inválido. <button class="btn btn-sm" data-post="<?= url('domains/'.(int)$row['id'].'/ssl') ?>" data-csrf="<?= csrf_token() ?>">Reemitir</button></div>
  <?php else: ?>
    <div class="alert mb-1">Sin certificado SSL. <button class="btn btn-sm" data-post="<?= url('domains/'.(int)$row['id'].'/ssl') ?>" data-csrf="<?= csrf_token() ?>">Activar SSL</button></div>
  <?php endif; ?>

  <form method="post" action="<?= url('domains/'.(int)$row['id'].'/update') ?>" data-ajax="1">
    <?= csrf_field() ?>
    <div class="grid-2">
      <div class="form-row">
        <label for="f_domain">Dominio</label>
        <input class="form-control" id="f_domain" value="<?= e($row['domain']) ?>" readonly>
      </div>

      <div class="form-row">
        <label for="f_app">Aplicación vinculada</label>
        <select class="form-control" id="f_app" name="app_id">
          <option value="0" <?= $linkedApp === 0 ? 'selected' : '' ?>>— Ninguna (configuración manual) —</option>
          <?php foreach ($apps as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $linkedApp === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?> (<?= e($a['folder']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <span class="muted" style="font-size:.8rem">Si seleccionas una app, se usan su carpeta, DocumentRoot y PHP. Edita la app para cambiarlos.</span>
      </div>

      <?php if ($linkedApp === 0): ?>
        <div class="form-row">
          <label for="f_folder">Carpeta base</label>
          <input class="form-control" id="f_folder" value="<?= e($row['folder']) ?>" readonly>
        </div>
        <div class="form-row">
          <label for="f_type">Tipo de proyecto</label>
          <select class="form-control" id="f_type" name="project_type">
            <?php $t = (string)($row['project_type'] ?? ''); ?>
            <?php foreach (['' => '— automático / manual', 'laravel' => 'Laravel', 'wordpress' => 'WordPress', 'php' => 'PHP', 'node' => 'Node.js', 'other' => 'Otra'] as $v => $label): ?>
              <option value="<?= $v ?>" <?= $t === $v ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label for="f_git">URL del repositorio</label>
          <input class="form-control" id="f_git" name="git_url" value="<?= e((string)($row['git_url'] ?? '')) ?>" placeholder="https://github.com/usuario/proyecto">
        </div>
        <div class="form-row">
          <label for="f_branch">Rama</label>
          <input class="form-control" id="f_branch" name="git_branch" value="<?= e((string)($row['git_branch'] ?? 'main')) ?>">
        </div>
        <div class="form-row">
          <label for="f_token">Token (solo repos privados)</label>
          <input class="form-control" id="f_token" name="git_token" value="" placeholder="<?= ($row['git_token'] ?? '') !== '' ? '(guardado — deja vacío para conservarlo)' : 'vacío si el repo es público' ?>" autocomplete="new-password">
        </div>
        <div class="form-row">
          <label for="f_path">Ruta del proyecto dentro del repo</label>
          <input class="form-control" id="f_path" name="project_path" value="<?= e((string)($row['project_path'] ?? '')) ?>" placeholder="vacío = raíz">
        </div>
        <div class="form-row">
          <label for="f_docroot">DocumentRoot (relativo al home)</label>
          <input class="form-control" id="f_docroot" name="document_root" value="<?= e(domain_docroot($row)) ?>">
          <span class="muted" style="font-size:.8rem">Laravel: <code>&lt;carpeta&gt;/public</code> · WordPress/PHP: raíz del proyecto</span>
        </div>
        <div class="form-row">
          <label for="f_php">Versión PHP</label>
          <input class="form-control" id="f_php" name="php_version" value="<?= e((string)($row['php_version'] ?? '')) ?>" placeholder="vacío = PHP del sistema">
        </div>
      <?php else: ?>
        <div class="form-row">
          <label>Carpeta / DocumentRoot / PHP</label>
          <input class="form-control" value="<?= e(domain_docroot($row)) ?> · PHP <?= e((string)($row['php_version'] ?: 'sistema')) ?>" readonly>
        </div>
      <?php endif; ?>
    </div>
    <div class="row-form mt-1">
      <button class="btn btn-primary" type="submit">Guardar</button>
      <a class="btn" href="<?= url('domains/'.(int)$row['id'].'/env') ?>">Editar .env</a>
    </div>
  </form>

  <div class="row-form mt-1">
    <?php if ($owner): ?>
      <button class="btn btn-info" data-post="<?= url('domains/'.(int)$row['id'].'/deploy') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Ejecutar Composer + caches ahora? (las migraciones van aparte)">Desplegar (Composer + caches)</button>
      <button class="btn" data-post="<?= url('domains/'.(int)$row['id'].'/migrate') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Ejecutar las migraciones ahora?">Migrar BD</button>
    <?php endif; ?>
    <?php if ($linkedApp === 0): ?>
      <button class="btn" data-post="<?= url('domains/'.(int)$row['id'].'/detect') ?>" data-csrf="<?= csrf_token() ?>" title="Re-detectar tipo y actualizar DocumentRoot">Re-detectar tipo</button>
    <?php endif; ?>
  </div>
</div>