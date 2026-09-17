<?php /** views/disk/index.php - uso de disco con desglose por carpetas */ ?>
<div class="section-header">
  <h3>Discos</h3>
</div>

<?php if ($isAdmin): ?>
  <div class="card mb-1">
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
  </div>
<?php endif; ?>

<div class="card">
  <div class="du-top">
    <span id="du-path" class="mono"></span>
    <span id="du-status" class="muted"></span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Nombre</th><th>Tipo</th><th>Tamaño</th><th>Modificado</th></tr>
      </thead>
      <tbody id="du-body">
        <tr><td colspan="4" class="muted">Cargando…</td></tr>
      </tbody>
    </table>
  </div>
</div>

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
      td.colSpan = 4;
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
      tr.appendChild(tdName);
      tr.appendChild(tdT);
      tr.appendChild(tdS);
      tr.appendChild(tdM);
      table.appendChild(tr);
    });
  }

  function load() {
    if (!user) { statusEl.textContent = '…'; return; }
    table.innerHTML = '<tr><td colspan="4" class="muted">Calculando tamaño…</td></tr>';
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