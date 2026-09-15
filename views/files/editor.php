<?php /** views/files/editor.php — editor de texto */ ?>
<div class="section-header">
  <h3>Editar <span class="mono"><?= e($rel) ?></span></h3>
  <a class="btn btn-sm" href="<?= url('files?p='.urlencode(dirname($rel)==='.'?'':dirname($rel))) ?>">← Volver</a>
</div>
<form method="post" action="<?= url('files/save') ?>" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="p" value="<?= e($rel) ?>">
  <textarea class="form-control mono" name="content" rows="24" spellcheck="false" style="width:100%;font-family:Consolas,monospace"><?= e($content) ?></textarea>
  <div class="row-form mt-1">
    <button class="btn btn-primary" type="submit"><svg class="ic"><use href="#i-check"/></svg> Guardar</button>
  </div>
</form>