<?php /** views/security/index.php — IPs con intentos fallidos */ ?>
<div class="section-header">
  <h3>Seguridad · intentos fallidos (últimas 24h)</h3>
  <form method="post" action="<?= url('security/clear') ?>" data-ajax="1">
    <?= csrf_field() ?>
    <button class="btn" type="submit">Limpiar antiguos</button>
  </form>
</div>

<?php if (empty($rows)): ?>
  <p class="muted">No hay intentos fallidos recientes.</p>
<?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>IP</th><th>Intentos</th><th>Último intento</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono"><?= e($r['ip']) ?></td>
          <td><?= (int)$r['total'] ?></td>
          <td class="muted"><?= e($r['last_at']) ?></td>
          <td class="actions">
            <button class="btn btn-danger btn-sm" data-csrf="<?= csrf_token() ?>" data-confirm="¿Bloquear <?= e($r['ip']) ?> en el firewall (HTTP/HTTPS/SSH)?" data-post="<?= url('security/ban?ip=' . urlencode($r['ip'])) ?>">Bloquear</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<p class="muted mt-1">El lockout automático bloquea una IP tras 6 intentos fallidos en 10 minutos. Desde aquí puedes bloquearla también a nivel de firewall.</p>