<?php /** views/disk/index.php - Backups + uso de disco con desglose por carpetas */ ?>
<div class="section-header">
  <h3>Backups</h3>
</div>

<section class="card mb-1">
  <div class="section-header">
    <h4>Backups del servidor</h4>
    <?php if ($isAdmin): ?>
      <div>
        <button class="btn btn-primary" data-post="<?= url('backup') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Crear un backup completo (archivos + carpetas + bases de datos)?">
          <svg class="ic"><use href="#i-upload"/></svg> Backup completo
        </button>
        <button class="btn btn-info" data-post="<?= url('backup/db') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Crear un backup de todas las bases de datos de usuario?">
          <svg class="ic"><use href="#i-database"/></svg> Backup BD
        </button>
      </div>
    <?php endif; ?>
  </div>
  <?php if (!empty($backups)): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Archivo</th><th>Tamaño</th><th>Fecha</th><th class="actions" style="justify-content:flex-end"></th></tr>
        </thead>
        <tbody>
          <?php foreach ($backups as $b): ?>
            <tr>
              <td class="mono"><?= e($b['name']) ?></td>
              <td><?= e(bytes_human($b['size'])) ?></td>
              <td class="muted"><?= e($b['date']) ?></td>
              <td class="actions" style="justify-content:flex-end">
                <a class="btn btn-xs" href="<?= url('download?f=' . urlencode($b['name'])) ?>">descargar</a>
                <button class="btn btn-danger btn-xs" data-csrf="<?= csrf_token() ?>" data-confirm="¿Eliminar este backup?" data-post="<?= url('backup/delete?f=' . urlencode($b['name'])) ?>">Eliminar</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <?php if ($isAdmin): ?>
      <p class="muted">Aún no hay backups. Pulsa <b>Backup completo</b> o <b>Backup BD</b> para generar uno. Cuando termine, aparecerá aquí.</p>
    <?php else: ?>
      <p class="muted">Aún no hay backups. Puedes generar uno desde la sección <b>Bases de datos</b> o desde <b>Uso de disco</b>.</p>
    <?php endif; ?>
  <?php endif; ?>
  <p class="muted mt-1">Los backups se guardan en <code><?= e(env('BACKUP_DIR', '/var/backups/miserver')) ?></code>. El progreso lo ves en <a href="<?= url('jobs') ?>">Tareas</a>.</p>
</section>

<section class="card mb-1">
  <div class="section-header">
    <h4>Bases de datos</h4>
  </div>
  <?php if (!empty($db_by_user)): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Cuenta</th><th>Base de datos</th><th>Tablas</th><th>Tamaño</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($db_by_user as $g): ?>
            <?php $first = true; ?>
            <?php foreach ($g['dbs'] as $db): ?>
              <tr>
                <?php if ($first): ?>
                  <td rowspan="<?= count($g['dbs']) ?>"><strong><?= e($g['user']) ?></strong><br><span class="muted"><?= e(format_size_kb($g['total_kb'])) ?></span></td>
                  <?php $first = false; ?>
                <?php endif; ?>
                <td class="mono"><?= e($db['full']) ?></td>
                <td class="muted"><?= (int) $db['tables'] ?></td>
                <td><?= e(format_size_kb($db['size_kb'])) ?></td>
                <td class="actions">
                  <button class="btn btn-xs" data-csrf="<?= csrf_token() ?>" data-confirm="¿Crear backup de <?= e($db['full']) ?>?" data-post="<?= url('backup/dbone') ?>" data-extra-db="<?= e($db['full']) ?>">Backup</button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="muted">No se detectaron bases de datos de usuario.</p>
  <?php endif; ?>
</section>

<section class="card mb-1">
  <div class="section-header">
    <h4>Estado del servidor</h4>
  </div>
  <div class="grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
    <?php if (!empty($disk_total)): ?>
      <?php
        $dpct = (int) ($disk_total['use_percent'] ?? 0);
        $dbar = $dpct >= 90 ? 'err' : ($dpct >= 75 ? 'warn' : '');
      ?>
      <div class="card stat">
        <div class="stat-lbl">Disco <?= e($disk_total['mount']) ?></div>
        <div class="stat-val" style="font-size:1.6rem"><?= $dpct ?>%</div>
        <div class="disk-bar <?= $dbar ?>"><div style="width:<?= min(100, $dpct) ?>%"></div></div>
        <div class="muted" style="font-size:.8rem">
          <?= e(format_size_kb((int) ($disk_total['used_kb'] ?? 0))) ?> /
          <?= e(format_size_kb((int) ($disk_total['size_kb'] ?? 0))) ?> ·
          libre <?= e(format_size_kb((int) ($disk_total['avail_kb'] ?? 0))) ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (($memory['total_mb'] ?? 0) > 0): ?>
      <?php
        $mused = (int) ($memory['used_mb'] ?? 0);
        $mtotal = (int) ($memory['total_mb'] ?? 0);
        $mpct = $mtotal > 0 ? (int) round(($mused / $mtotal) * 100) : 0;
        $mbar = $mpct >= 90 ? 'err' : ($mpct >= 75 ? 'warn' : '');
      ?>
      <div class="card stat">
        <div class="stat-lbl">Memoria</div>
        <div class="stat-val" style="font-size:1.6rem"><?= $mpct ?>%</div>
        <div class="disk-bar <?= $mbar ?>"><div style="width:<?= min(100, $mpct) ?>%"></div></div>
        <div class="muted" style="font-size:.8rem">
          <?= e(format_size_kb($mused * 1024)) ?> /
          <?= e(format_size_kb($mtotal * 1024)) ?> ·
          libre <?= e(format_size_kb(((int) ($memory['free_mb'] ?? 0)) * 1024)) ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (($load['1m'] ?? 0) > 0 || ($load['5m'] ?? 0) > 0): ?>
      <div class="card stat">
        <div class="stat-lbl">Carga CPU</div>
        <div class="stat-val" style="font-size:1.4rem"><?= number_format((float) ($load['1m'] ?? 0), 2) ?></div>
        <div class="muted" style="font-size:.8rem">
          1m: <?= number_format((float) ($load['1m'] ?? 0), 2) ?> ·
          5m: <?= number_format((float) ($load['5m'] ?? 0), 2) ?> ·
          15m: <?= number_format((float) ($load['15m'] ?? 0), 2) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="card mb-1">
  <div class="section-header">
    <h4>Uso de disco</h4>
  </div>
  <?php if ($isAdmin): ?>
    <div class="row-form">
      <select id="du-user" class="form-control">
        <?php if ($users): ?>
          <?php foreach ($users as $x): ?>
            <option value="<?= e($x['user']) ?>"><?= e($x['user']) ?> — <?= bytes_human($x['bytes']) ?></option>
          <?php endforeach; ?>
        <?php else: ?>
          <option value="">Sin carpetas de usuario</option>
        <?php endif; ?>
      </select>
      <button type="button" class="btn btn-primary" id="du-load">Ver uso</button>
      <p class="muted">El tamaño de cada carpeta se calcula con <code>du</code> al expandirla. Los enlaces simbólicos se muestran pero no se recorren.</p>
    </div>
  <?php else: ?>
    <p class="muted">Pulsa <b>Ver uso</b> para ver el desglose de tu carpeta de usuario.</p>
  <?php endif; ?>

  <div class="du-top">
    <span id="du-path" class="mono"></span>
    <span id="du-status" class="muted"></span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Nombre</th><th>Tipo</th><th>Tamaño</th><th>Modificado</th><th></th></tr>
      </thead>
      <tbody id="du-body">
        <tr><td colspan="5" class="muted">Cargando…</td></tr>
      </tbody>
    </table>
  </div>
</section>

<script>
(function () {
  'use strict';

  var isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
  var select = document.getElementById('du-user');
  var table = document.getElementById('du-body');
  var pathEl = document.getElementById('du-path');
  var statusEl = document.getElementById('du-status');
  var user = isAdmin ? (select && select.value || '') : <?= json_encode($u['user'] ?? '') ?>;
  var rel = '';

  function fmt(b) {
    var n = +b || 0, units = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return n.toFixed(n >= 10 || i === 0 ? 0 : 1) + ' ' + units[i];
  }

  function typeLabel(t) {
    return t === 'D' ? 'directorio' : t === 'L' ? 'enlace' : 'archivo';
  }

  function rows(entries) {
    table.innerHTML = '';
    if (!entries.length) {
      var tr = document.createElement('tr');
      var td = document.createElement('td');
      td.colSpan = 5;
      td.className = 'muted';
      td.textContent = 'Carpeta vacía.';
      tr.appendChild(td);
      table.appendChild(tr);
      return;
    }
    entries.forEach(function (ent) {
      var tr = document.createElement('tr');
      var tdName = document.createElement('td');
      if (ent.t === 'D') {
        var open = document.createElement('button');
        open.type = 'button';
        open.className = 'btn btn-xs';
        open.textContent = '▶';
        open.title = 'Expandir';
        open.addEventListener('click', function () {
          rel = rel ? rel + '/' + ent.n : ent.n;
          load();
        });
        tdName.appendChild(open);
      }
      var nm = document.createElement('span');
      nm.className = 'mono';
      nm.textContent = ent.n;
      tdName.appendChild(document.createTextNode(' '));
      tdName.appendChild(nm);
      var tdT = document.createElement('td');
      tdT.textContent = typeLabel(ent.t);
      var tdS = document.createElement('td');
      tdS.textContent = fmt(ent.b);
      var tdM = document.createElement('td');
      tdM.className = 'muted';
      if (ent.t === 'F' && ent.m) tdM.textContent = new Date(ent.m * 1000).toLocaleString();
      var tdA = document.createElement('td');
      if (ent.t === 'D') {
        var bk = document.createElement('button');
        bk.type = 'button';
        bk.className = 'btn btn-xs';
        bk.textContent = 'backup';
        var fpath = rel ? rel + '/' + ent.n : ent.n;
        bk.setAttribute('data-post', <?= json_encode(url('disk/backup')) ?>);
        bk.setAttribute('data-csrf', <?= json_encode(csrf_token()) ?>);
        bk.setAttribute('data-extra-user', user);
        bk.setAttribute('data-extra-rel', fpath);
        bk.setAttribute('data-confirm', '¿Crear backup de ' + user + '/' + fpath + '?');
        tdA.appendChild(bk);
      }
      tr.appendChild(tdName);
      tr.appendChild(tdT);
      tr.appendChild(tdS);
      tr.appendChild(tdM);
      tr.appendChild(tdA);
      table.appendChild(tr);
    });
  }

  function load() {
    if (!user) { statusEl.textContent = '…'; return; }
    table.innerHTML = '<tr><td colspan="5" class="muted">Calculando tamaño…</td></tr>';
    var body = new URLSearchParams({ user: user, rel: rel });
    fetch(<?= json_encode(url('disk/browse')) ?>, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: body,
      credentials: 'same-origin'
    }).then(function (res) { return res.json(); })
      .then(function (d) {
        if (!d.ok) {
          table.innerHTML = '';
          statusEl.textContent = d.msg || 'Error al escanear.';
          return;
        }
        if (d.up !== '' && d.up !== undefined) {
          var back = document.createElement('button');
          back.type = 'button';
          back.className = 'btn btn-xs';
          back.textContent = '◀ subir';
          back.title = 'Carpeta anterior';
          back.addEventListener('click', function () { rel = d.up; load(); });
          pathEl.textContent = '';
          pathEl.appendChild(back);
          pathEl.appendChild(document.createTextNode(' /home/' + user + (d.up || '')));
        } else {
          pathEl.textContent = '/home/' + user;
        }
        statusEl.textContent = d.total !== null ? 'Total: ' + fmt(d.total) : '';
        rows(d.entries || []);
      })
      .catch(function () { statusEl.textContent = 'Error de red.'; });
  }

  var loadBtn = document.getElementById('du-load');
  if (loadBtn) loadBtn.addEventListener('click', function () { rel = ''; user = select.value; load(); });
  if (user) load();
})();
</script>
