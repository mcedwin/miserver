<?php /** views/dbs/index.php */ ?>
<?php $isAdmin = ($u['role'] ?? '') === 'admin'; ?>
<div class="section-header">
  <h3>Bases de datos <span class="muted">— <?= e($ctx['user']) ?></span></h3>
  <?php if (!empty($users)): ?>
    <label class="inline-label">Usuario:
      <select class="form-control" onchange="location.href='<?= url('dbs') ?>?u='+this.value">
        <option value="all" <?= $scope==='all'?'selected':'' ?>>Todos los usuarios</option>
        <?php foreach ($users as $x): ?>
          <option value="<?= (int)$x['id'] ?>" <?= $scope!=='all' && (int)$x['id']===(int)$ctx['id']?'selected':'' ?>><?= e($x['user']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
</div>

<div class="tabs">
  <button class="tab on" data-tab="bases">Bases de datos</button>
  <button class="tab" data-tab="usuarios">Usuarios de BD</button>
  <button class="tab" data-tab="relaciones">Relaciones</button>
</div>

<div class="tab-pane" id="pane-bases">
  <form class="row-form" method="post" action="<?= url('dbs/db') ?>" data-ajax="1">
    <?= csrf_field() ?>
    <input class="form-control" name="name" placeholder="Nombre de la base (ej: web)" required pattern="[a-z0-9_]{1,64}">
    <button class="btn btn-primary" type="submit">Crear BD</button>
  </form>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Nombre real</th><?php if ($scope==='all'): ?><th>Dueño</th><?php endif; ?><th></th></tr></thead>
    <tbody>
      <?php foreach ($shemas as $s): ?>
        <tr>
          <td class="mono"><?= e(db_prefix($s['uname'], $s['name'])) ?></td>
          <?php if ($scope==='all'): ?><td><?= e($s['uname']) ?></td><?php endif; ?>
          <td class="actions">
          <?php if ($scope==='all'): ?>
            <a class="btn btn-sm" href="<?= url('dbs') ?>?u=<?= (int)$s['user_id'] ?>">Gestionar</a>
          <?php else: ?>
            <button class="btn btn-danger btn-sm" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar la base y sus datos?" data-post="<?= url('dbs/db/'.$s['id'].'/delete') ?>">Eliminar</button>
          <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="tab-pane hidden" id="pane-usuarios">
  <form class="row-form" method="post" action="<?= url('dbs/dbu') ?>" data-ajax="1">
    <?= csrf_field() ?>
    <input class="form-control" name="user" placeholder="Usuario BD (ej: app)" required pattern="[a-z0-9_]{1,64}">
    <input class="form-control" name="password" type="password" placeholder="Contraseña" required minlength="8">
    <button class="btn btn-primary" type="submit">Crear usuario</button>
  </form>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Usuario</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($dbusers as $w): ?>
        <tr>
          <td class="mono"><?= e(db_prefix($ctx['user'], $w['user'])) ?></td>
          <td class="actions">
            <button class="btn btn-sm" data-csrf="<?= csrf_token() ?>" data-post="<?= url('dbs/dbu/'.$w['id'].'/pass') ?>">Restablecer clave</button>
            <button class="btn btn-danger btn-sm" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar este usuario de BD?" data-post="<?= url('dbs/dbu/'.$w['id'].'/delete') ?>">Eliminar</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="tab-pane hidden" id="pane-relaciones">
  <form class="row-form" method="post" action="<?= url('dbs/rel') ?>" data-ajax="1">
    <?= csrf_field() ?>
    <select class="form-control" name="iduser" required>
      <option value="">Usuario BD</option>
      <?php foreach ($dbusers as $w): ?>
        <option value="<?= (int)$w['id'] ?>"><?= e($w['user']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-control" name="idshema" required>
      <option value="">Base de datos</option>
      <?php foreach ($shemas as $s): ?>
        <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit">Relacionar</button>
  </form>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Base</th><th>Usuario</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($relations as $r): ?>
        <tr>
          <td class="mono"><?= e(db_prefix($ctx['user'], $r['sname'])) ?></td>
          <td class="mono"><?= e(db_prefix($ctx['user'], $r['dbu'])) ?></td>
          <td class="actions">
            <button class="btn btn-danger btn-sm" data-csrf="<?= csrf_token() ?>" data-confirm="¿Quitar el acceso a esa base?" data-post="<?= url('dbs/rel/'.$r['iduser'].'/'.$r['idshema'].'/delete') ?>">Quitar</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<script>
(function(){document.querySelectorAll('.tab').forEach(t=>t.addEventListener('click',()=>{document.querySelectorAll('.tab').forEach(x=>x.classList.remove('on'));t.classList.add('on');document.querySelectorAll('.tab-pane').forEach(p=>p.classList.add('hidden'));document.getElementById('pane-'+t.dataset.tab).classList.remove('hidden');}));})();
</script>