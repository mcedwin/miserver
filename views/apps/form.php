<?php /** views/apps/form.php */ ?>
<?php $isAdmin = ($u['role'] ?? '') === 'admin'; ?>
<?php $id = $row ? (int)$row['id'] : 0; ?>
<a class="btn btn-sm" href="<?= url('apps') ?>">← Volver</a>
<div class="card mt-1">
  <h3><?= $row ? 'Editar' : 'Nueva' ?> aplicación</h3>
  <form method="post" action="<?= url($row ? 'apps/'.$id.'/update' : 'apps') ?>" data-ajax="1">
    <?= csrf_field() ?>
    <div class="grid-2">
      <?php if ($isAdmin): ?>
        <div class="form-row">
          <label for="f_user">Usuario</label>
          <select class="form-control" id="f_user" name="user_id" <?= $row ? 'disabled' : '' ?>
            onchange="document.getElementById('f_folder').placeholder = '/home/' + this.options[this.selectedIndex].text + '/...'">
            <?php $selUid = $row ? (int)$row['user_id'] : (int)$ctx['id']; ?>
            <?php foreach ($users as $x): ?>
              <option value="<?= (int)$x['id'] ?>" <?= $selUid === (int)$x['id'] ? 'selected' : '' ?>><?= e($x['user']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($row): ?><input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>"><?php endif; ?>
        </div>
      <?php else: ?>
        <input type="hidden" name="user_id" value="<?= (int)$ctx['id'] ?>">
      <?php endif; ?>

      <div class="form-row">
        <label for="f_name">Nombre</label>
        <input class="form-control" id="f_name" name="name" value="<?= e((string)($row['name'] ?? '')) ?>" required placeholder="mi-app">
      </div>

      <div class="form-row">
        <label for="f_folder">Carpeta dentro del home</label>
        <input class="form-control" id="f_folder" name="folder" value="<?= e((string)($row['folder'] ?? '')) ?>" <?= $row ? 'readonly' : 'required' ?> placeholder="mi-app">
        <?php if ($row): ?><span class="muted" style="font-size:.8rem">No se puede mover la carpeta desde el panel.</span><?php endif; ?>
      </div>

      <div class="form-row">
        <label for="f_git">URL del repositorio</label>
        <input class="form-control" id="f_git" name="git_url" value="<?= e((string)($row['git_url'] ?? '')) ?>" required placeholder="https://github.com/usuario/proyecto">
      </div>

      <div class="form-row">
        <label for="f_branch">Rama</label>
        <input class="form-control" id="f_branch" name="git_branch" value="<?= e((string)($row['git_branch'] ?? 'main')) ?>">
      </div>

      <div class="form-row">
        <label for="f_token">Token (solo repos privados)</label>
        <input class="form-control" id="f_token" name="git_token" type="password" value="" placeholder="<?= ($row['git_token'] ?? '') !== '' ? '(guardado — deja vacío para conservarlo)' : 'vacío si el repo es público' ?>" autocomplete="new-password">
      </div>

      <div class="form-row">
        <label for="f_php">Versión PHP</label>
        <input class="form-control" id="f_php" name="php_version" value="<?= e((string)($row['php_version'] ?? '')) ?>" placeholder="vacío = PHP del sistema">
      </div>
    </div>
    <div class="row-form mt-1">
      <button class="btn btn-primary" type="submit">Guardar</button>
      <a class="btn" href="<?= url('apps') ?>">Cancelar</a>
    </div>
  </form>
</div>
<p class="muted mt-1">Al crear una app se clona el repo, se detecta el tipo (Laravel/WordPress/PHP/etc.) y se lanza el despliegue en segundo plano.</p>