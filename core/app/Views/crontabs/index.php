<div class="container pt-4">

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <h5 class="lh-base mb-0">
        Crontabs
      </h5>
    </div>
    <div class="card-body">

      <form class="form-horizontal needs-validation formu" action="<?= base_url("crontabs/guardar") ?>" method="post" enctype="multipart/form-data" novalidate>

        <textarea class="form-control" name="texto" rows="10"><?php echo htmlspecialchars($text); ?></textarea>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </form>
    </div>
  </div>

</div>