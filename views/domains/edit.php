<?php /** views/domains/edit.php — Configuración de dominio */ ?>
<a class="btn btn-sm" href="<?= url('domains') ?>">← Volver</a>
<div class="card mt-1">
  <h3>Editar dominio <span class="mono"><?= e($row['domain']) ?></span></h3>
  <?php $ssi = $ssl_info ?? ['status' => 'missing', 'exp' => '', 'days' => 0]; ?>
  <?php if (($ssi['status'] ?? '') === 'ok'): ?>
    <div class="alert-ok alert mb-1">Certificado SSL válido · vence el <?= e($ssi['exp']) ?> (<?= (int)($ssi['days'] ?? 0) ?> días restantes).</div>
  <?php elseif (($ssi['status'] ?? '') === 'invalid'): ?>
    <div class="alert alert-warn mb-1">Certificado SSL presente pero inválido. <button class="btn btn-sm" data-post="<?= url('domains/'.(int)$row['id'].'/ssl') ?>" data-csrf="<?= csrf_token() ?>">Reemitir</button></div>
  <?php else: ?>
    <div class="alert mb-1">Sin certificado SSL. <button class="btn btn-sm" data-post="<?= url('domains/'.(int)$row['id'].'/ssl') ?>" data-csrf="<?= csrf_token() ?>">Activar SSL</button></div>
  <?php endif; ?>

  <form method="post" action="<?= url('domains/'.(int)$row['id'].'/update') ?>" data-ajax="1">
    <?= csrf_field() ?>
    <div class="grid-2">
      <div class="form-row">
        <label for="f_domain">Dominio</label>
        <input class="form-control" id="f_domain" value="<?= e($row['domain']) ?>" readonly>
      </div>
      <div class="form-row">
        <label for="f_folder">Carpeta base</label>
        <input class="form-control" id="f_folder" name="folder" value="<?= e((string)($row['folder'] ?? '')) ?>">
      </div>
      <div class="form-row">
        <label for="f_docroot">DocumentRoot (relativo al home)</label>
        <input class="form-control" id="f_docroot" name="document_root" value="<?= e((string)($row['document_root'] ?? '')) ?>">
        <span class="muted" style="font-size:.8rem">Ej: <code>midominio/public</code> para Laravel.</span>
      </div>
      <div class="form-row">
        <label for="f_php">Versión PHP</label>
        <input class="form-control" id="f_php" name="php_version" value="<?= e((string)($row['php_version'] ?? '')) ?>" placeholder="vacío = PHP del sistema">
      </div>
    </div>
    <div class="row-form mt-1">
      <button class="btn btn-primary" type="submit">Guardar</button>
    </div>
  </form>
</div>
