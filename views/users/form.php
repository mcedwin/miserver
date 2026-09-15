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