<?php /** views/domains/edit.php — Configuración de aplicación / dominio */ ?>
<a class="btn btn-sm" href="<?= url('domains') ?>">← Volver</a>
<div class="card mt-1">
  <h3>Editar aplicación <span class="mono"><?= e($row['domain']) ?></span></h3>
  <p class="muted">La configuración (tipo, ruta del proyecto, DocumentRoot, PHP y dominio) se guarda para regenerar el VirtualHost de Apache. <a href="<?= url('domains/'.(int)$row['id'].'/env') ?>">Editar .env</a> · este editor no toca el sistema de contraseñas del panel.</p>
  <form method="post" action="<?= url('domains/'.(int)$row['id'].'/update') ?>" data-ajax="1">
    <?= csrf_field() ?>
    <div class="grid-2">
      <div class="form-row">
        <label for="f_domain">Dominio</label>
        <input class="form-control" id="f_domain" value="<?= e($row['domain']) ?>" readonly>
      </div>
      <div class="form-row">
        <label for="f_folder">Carpeta base (clone)</label>
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
        <label for="f_path">Ruta del proyecto dentro del repo</label>
        <input class="form-control" id="f_path" name="project_path" value="<?= e((string)($row['project_path'] ?? '')) ?>" placeholder="vacío = raíz (p.ej. src)">
      </div>
      <div class="form-row">
        <label for="f_docroot">DocumentRoot (relativo al home)</label>
        <input class="form-control" id="f_docroot" name="document_root" value="<?= e(domain_docroot($row)) ?>">
        <span class="muted" style="font-size:.8rem">Laravel: <code>&lt;carpeta&gt;/public</code> · WordPress/PHP: raíz del proyecto</span>
      </div>
      <div class="form-row">
        <label for="f_php">Versión PHP</label>
        <input class="form-control" id="f_php" name="php_version" value="<?= e((string)($row['php_version'] ?? '')) ?>" placeholder="vacío = PHP del sistema (p.ej. 8.1)">
      </div>
    </div>
    <div class="row-form mt-1">
      <button class="btn btn-primary" type="submit">Guardar</button>
      <a class="btn" href="<?= url('domains/'.(int)$row['id'].'/env') ?>">Editar .env</a>
    </div>
  </form>
  <div class="row-form mt-1">
    <?php if ($owner): ?>
      <button class="btn btn-info" data-post="<?= url('domains/'.(int)$row['id'].'/deploy') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Ejecutar Composer + caches + migraciones ahora?">Desplegar (Composer + caches + migraciones)</button>
    <?php endif; ?>
    <button class="btn" data-post="<?= url('domains/'.(int)$row['id'].'/detect') ?>" data-csrf="<?= csrf_token() ?>" title="Re-detectar tipo y actualizar DocumentRoot">Re-detectar tipo</button>
  </div>
</div>