<?php /** views/cron/index.php */ ?>
<?php $isAdmin = ($u['role'] ?? '') === 'admin'; ?>
<div class="section-header">
  <h3>Cron <span class="muted">— <?= e($ctx['user']) ?></span></h3>
  <?php if (!empty($users)): ?>
    <label class="inline-label">Usuario:
      <select class="form-control" onchange="location.href='<?= url('cron') ?>?u='+this.value">
        <?php foreach ($users as $x): ?>
          <option value="<?= (int)$x['id'] ?>" <?= (int)$x['id']===(int)$ctx['id']?'selected':'' ?>><?= e($x['user']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
</div>

<?php if (!$wrapper_ok): ?>
  <div class="alert"><p>El wrapper <code>miserver-ctl</code> no está instalado. Ejecuta <code>bash instalarserver.sh</code>.</p></div>
<?php else: ?>
  <form method="post" action="<?= url('cron') ?>" class="card">
    <?= csrf_field() ?>
    <p class="muted">Tabla <code>crontab</code> del usuario <b><?= e($ctx['user']) ?></b></p>
    <textarea class="form-control mono" name="texto" rows="12" spellcheck="false" placeholder="*/5 * * * * /usr/bin/php /ruta/script.php"><?= e($text) ?></textarea>
    <div class="row-form mt-1">
      <button class="btn btn-primary" type="submit"><svg class="ic"><use href="#i-check"/></svg> Guardar cron</button>
    </div>
  </form>
<?php endif; ?>