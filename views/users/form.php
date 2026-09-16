<?php /** views/users/form.php — Crear / editar usuario */ ?>
<a class="btn btn-sm" href="<?= url('users') ?>">← Volver</a>
<div class="card mt-1">
  <h3><?= $editing ? 'Editar usuario' : 'Nuevo usuario' ?></h3>
  <form method="post" action="<?= $editing ? url('users/'.$id) : url('users') ?>" data-ajax="1">
    <?= csrf_field() ?>
    <div class="grid-2">
      <?php foreach ($fields as $key => $f): ?>
        <div class="form-row">
          <label for="<?= $key ?>"><?= e($f['label']) ?> <?= (!empty($f['required'])?'<strong class="req">*</strong>':'') ?></label>
          <?php if (isset($f['readonly']) && $f['readonly']): ?>
            <input class="form-control" id="<?= $key ?>" name="<?= $key ?>" value="<?= e((string)$f['value']) ?>" readonly>
          <?php else: ?>
            <input class="form-control" id="<?= $key ?>" name="<?= $key ?>" type="<?= $key==='password'||$key==='password2'?'password':'text' ?>"
                   value="<?= e((string)$f['value']) ?>" <?= !empty($f['required'])?'required':'' ?>>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-primary" type="submit">Guardar</button>
  </form>
</div>
<?php if ($editing): ?>
<?php $lu = e($fields['user']['value']); ?>
<div class="card mt-1">
  <h3>Acceso MySQL del usuario principal</h3>
  <p class="muted">Permisos sobre la cuenta Linux/MySQL <span class="mono"><?= $lu ?></span> (no afecta a los usuarios de BD con prefijo). Se aplican a las cuentas <span class="mono">@localhost</span> y <span class="mono">@%</span> (remoto).</p>
  <div class="actions">
    <button class="btn btn-sm" data-post="<?= url('users/'.$id.'/db/own') ?>" data-csrf="<?= csrf_token() ?>">
      <svg class="ic"><use href="#i-check"/></svg> Acceso solo a sus BD</button>
    <button class="btn btn-sm" data-post="<?= url('users/'.$id.'/db/unown') ?>" data-csrf="<?= csrf_token() ?>">
      <svg class="ic"><use href="#i-x"/></svg> Quitar acceso a sus BD</button>
    <button class="btn btn-sm btn-info" data-post="<?= url('users/'.$id.'/db/admin') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Conceder acceso a TODAS las bases de datos?">
      <svg class="ic"><use href="#i-check"/></svg> Acceso total (todas las BD)</button>
    <button class="btn btn-sm btn-danger" data-post="<?= url('users/'.$id.'/db/unadmin') ?>" data-csrf="<?= csrf_token() ?>">
      <svg class="ic"><use href="#i-x"/></svg> Quitar acceso total</button>
  </div>
</div>
<?php endif; ?>