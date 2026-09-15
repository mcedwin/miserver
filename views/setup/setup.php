<?php /** views/setup/setup.php */ ?>
<?php if (!empty($error)): ?>
  <div class="alert">
    <p><?= $error ?></p>
    <p>Asegúrate de ejecutar <code>bash instalarserver.sh</code> en el servidor antes de continuar.</p>
  </div>
<?php elseif (!empty($done)): ?>
  <div class="alert alert-ok">
    <p>El panel ya está configurado.</p>
    <a class="btn btn-primary" href="<?= url('home') ?>">Ir al panel</a>
  </div>
<?php else: ?>
  <p>Bienvenido. Configura tu panel y tu cuenta de administrador.</p>
  <form class="auth-form" method="post" action="<?= url('setup') ?>" data-ajax="1">
    <?= csrf_field() ?>
    <div class="form-row">
      <label for="user">Usuario (cuenta linux y panel)</label>
      <input class="form-control" id="user" name="user" required placeholder="ej: migente" pattern="[a-z][a-z0-9_]{2,31}">
    </div>
    <div class="form-row">
      <label for="domain">Dominio principal</label>
      <input class="form-control" id="domain" name="domain" required placeholder="ej: mismis.com">
    </div>
    <div class="form-row">
      <label for="pass">Contraseña</label>
      <input class="form-control" id="pass" type="password" name="password" required minlength="8">
    </div>
    <div class="form-row">
      <label for="pass2">Repite contraseña</label>
      <input class="form-control" id="pass2" type="password" name="password2" required minlength="8">
    </div>
    <hr>
    <details>
      <summary>Opciones avanzadas (DigitalOcean / Let's Encrypt)</summary>
      <div class="form-row">
        <label for="do">Token de DigitalOcean (opcional)</label>
        <input class="form-control" id="do" name="do_token" placeholder="Déjalo vacío si no usarás DNS automático">
      </div>
      <div class="form-row">
        <label for="le">Correo para Let's Encrypt (opcional)</label>
        <input class="form-control" id="le" name="le_email" type="email" placeholder=" correo para certificados SSL">
      </div>
    </details>
    <button class="btn btn-primary btn-block" type="submit">Instalar y crear admin</button>
  </form>
<?php endif; ?>