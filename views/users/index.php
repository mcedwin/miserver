<?php /** views/users/index.php */ ?>
<div class="section-header">
  <h3>Usuarios</h3>
  <a class="btn btn-primary" href="<?= url('users/new') ?>"><svg class="ic"><use href="#i-plus"/></svg> Nuevo</a>
</div>
<input class="form-control mb-1" id="search" placeholder="Buscar..." oninput="filterTable(this.value)">
<div class="table-wrap">
<table class="table" id="tbl">
  <thead>
    <tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Dominio</th><th>Rol</th><th>Activo</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($users as $r): ?>
      <tr data-search="<?= e(strtolower($r['user'].' '.$r['name'].' '.$r['domain'])) ?>">
        <td><?= (int)$r['id'] ?></td>
        <td class="mono"><?= e($r['user']) ?></td>
        <td><?= e($r['name']) ?></td>
        <td><a href="http://<?= e($r['domain']) ?>" target="_blank"><?= e($r['domain']) ?></a></td>
        <td><?= e($r['role']) ?></td>
        <td>
          <button class="btn btn-xs <?= $r['active']?'btn-info':'btn-light' ?>"
                  data-post="<?= url('users/'.$r['id'].'/toggle') ?>"
                  data-csrf="<?= csrf_token() ?>">
            <?= $r['active'] ? '<svg class="ic"><use href="#i-check"/></svg> Activo' : 'Inactivo' ?>
          </button>
        </td>
        <td class="actions">
          <a class="btn btn-sm" href="<?= url('users/'.$r['id'].'/edit') ?>">Editar</a>
          <?php if ((int)$r['id'] !== 1): ?>
            <button class="btn btn-danger btn-sm"
                    data-post="<?= url('users/'.$r['id'].'/delete') ?>"
                    data-csrf="<?= csrf_token() ?>"
                    data-confirm="¿Eliminar este usuario y todo su contenido?">Eliminar</button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<script>
function filterTable(q){document.querySelectorAll('#tbl tbody tr').forEach(r=>{r.hidden=q&&!r.dataset.search.includes(q.toLowerCase())});}
</script>