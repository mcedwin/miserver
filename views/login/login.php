<?php /** views/login/login.php */ ?>
<?php if (!empty($error)): ?>
  <div class="flash flash-err"><?= $error ?></div>
<?php endif; ?>
<form class="auth-form" method="post" action="<?= url('login') ?>" data-ajax="1">
  <?= csrf_field() ?>
  <div class="form-row">
    <label for="user">Usuario</label>
    <input class="form-control" id="user" name="user" required autofocus>
  </div>
  <div class="form-row">
    <label for="pass">Contraseña</label>
    <input class="form-control" id="pass" type="password" name="password" required>
  </div>
  <button class="btn btn-primary btn-block" type="submit">Entrar</button>
</form>