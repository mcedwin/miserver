<?php /** views/settings/index.php */ ?>
<div class="grid-2">

  <div class="card">
    <h3>Panel</h3>
    <form method="post" action="<?= url('settings') ?>" data-ajax="1">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="panel_name">Nombre del panel</label>
        <input class="form-control" id="panel_name" name="panel_name" value="<?= e($cfg['panel_name']) ?>">
      </div>
      <div class="form-row">
        <label for="domain">Dominio principal</label>
        <input class="form-control" id="domain" name="domain" value="<?= e($cfg['domain']) ?>">
      </div>
      <div class="form-row">
        <label for="le_email">Correo Let's Encrypt (para SSL)</label>
        <input class="form-control" id="le_email" name="le_email" type="email" value="<?= e($cfg['le_email']) ?>">
      </div>
      <div class="form-row">
        <label for="do_token">Token DigitalOcean (DNS automático)</label>
        <input class="form-control" id="do_token" name="do_token" value="<?= e($cfg['do_token']) ?>" placeholder="Vacíalo para desactivar">
      </div>
      <button class="btn btn-primary" type="submit">Guardar</button>
    </form>
  </div>

  <div class="card">
    <h3>Mi contraseña</h3>
    <form method="post" action="<?= url('settings/password') ?>" data-ajax="1">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="current">Contraseña actual</label>
        <input class="form-control" id="current" type="password" name="current" required>
      </div>
      <div class="form-row">
        <label for="new">Nueva contraseña</label>
        <input class="form-control" id="new" type="password" name="new" required minlength="8">
      </div>
      <div class="form-row">
        <label for="new2">Repite la nueva</label>
        <input class="form-control" id="new2" type="password" name="new2" required minlength="8">
      </div>
      <button class="btn btn-primary" type="submit">Cambiar contraseña</button>
      <p class="muted mt-1">Al cambiar tu contraseña del panel también se cambia tu clave de cuenta linux (SSH/FTP).</p>
    </form>
  </div>

</div>