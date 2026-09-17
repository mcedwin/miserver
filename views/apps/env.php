<?php /** views/apps/env.php — editor seguro del .env de la aplicación */ ?>
<a class="btn btn-sm" href="<?= url('apps') ?>">← Volver</a>
<?php
$debugPath = '/home/' . ($owner['user'] ?? '') . '/' . ($rel ?? '');
$debugStat = @stat($debugPath);
$debugPerms = $debugStat ? sprintf('%o', $debugStat['mode'] & 07777) : 'n/a';
$debugSize = $debugStat ? (int) $debugStat['size'] : 'n/a';
$debugReadable = @is_readable($debugPath) ? '1' : '0';
$debugFgc = @file_get_contents($debugPath);
$debugFgcType = gettype($debugFgc);
$debugFgcLen = is_string($debugFgc) ? strlen($debugFgc) : 'n/a';
?>
<pre class="card" style="background:#222;color:#0f0;padding:1rem">DEBUG: content_len=<?= strlen($content ?? '') ?> existed=<?= $existed ? '1' : '0' ?> source=<?= e($source ?? '') ?> path=<?= e($debugPath) ?> perms=<?= e($debugPerms) ?> size=<?= e((string)$debugSize) ?> is_readable=<?= e($debugReadable) ?> fgc_type=<?= e($debugFgcType) ?> fgc_len=<?= e((string)$debugFgcLen) ?></pre>
<div class="card mt-1">
  <h3>Editar <span class="mono">.env</span> · <?= e($row['name']) ?></h3>
  <p class="muted mb-1">
    Archivo <code class="mono">/home/<?= e($owner['user']) ?>/<?= e($rel) ?></code>.
  </p>
  <?php if (!$existed && $source !== ''): ?>
    <div class="alert-ok alert mb-1">No existe .env. Se precargó el contenido de <code><?= e($source) ?></code>. Pulsa Guardar para crearlo.</div>
  <?php elseif (!$existed): ?>
    <div class="alert alert-warn mb-1">No existe .env ni .env.example en esta aplicación. Escribí el contenido y guardá para crearlo.</div>
  <?php endif; ?>
  <form method="post" action="<?= url('apps/'.$row['id'].'/env') ?>" class="card" style="box-shadow:none;border:0;padding:0">
    <?= csrf_field() ?>
    <textarea class="form-control mono" name="content" rows="24" spellcheck="false" style="width:100%;font-family:Consolas,monospace"><?= e($content) ?></textarea>
    <div class="row-form mt-1">
      <button class="btn btn-primary" type="submit"><svg class="ic"><use href="#i-check"/></svg> Guardar .env</button>
      <button class="btn btn-info" data-post="<?= url('apps/'.$row['id'].'/deploy') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Ejecutar Composer + caches ahora?">Desplegar</button>
      <button class="btn" data-post="<?= url('apps/'.$row['id'].'/migrate') ?>" data-csrf="<?= csrf_token() ?>" data-confirm="¿Ejecutar migraciones ahora?">Migrar BD</button>
    </div>
  </form>
</div>