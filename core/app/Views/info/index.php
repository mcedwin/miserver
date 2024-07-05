<div class="container pt-4">
  <h1>Disco</h1>
  <div class="row">

    <?php
    $colores = ['success', 'info', 'warning', 'danger'];
    foreach ($datos as $i => $row) :
      if ($i == 0) continue;
      $row = explode("\t", $row);
    ?>
      <div class="col-md-2"><?php echo $row[5] ?></div>
      <div class="col-md-8">
        <div class="progress mt-2" role="progressbar" aria-label="Info example " aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="height: 20px">
          <div class="progress-bar bg-<?php echo $colores[$i % 4] ?> text-dark" style="width: <?php echo $row[4] ?>"><?php echo $row[4].' - '.$row[2] ?></div>
        </div>
      </div>
      <div class="col-md-2"><?php echo $row[3].' / '.$row[1] ?></div>

    <?php endforeach; ?>
  </div>

  <h1>Homes</h1>
  <div class="row">
    <?php
    foreach ($homes as $row) :
      $row = explode("\t", $row);
    ?>
      <div class="col-md-4"><?php echo $row[1] ?></div>
      <div class="col-md-8"><?php echo $row[0] ?></div>
    <?php endforeach; ?>
  </div>
  <h1>MySQL</h1>
  <div class="row">
    <?php
    foreach ($infos as $row) :
    ?>
      <div class="col-md-4"><?php echo $row->database_name ?></div>
      <div class="col-md-8"><?php echo $row->size_mb ?></div>
    <?php endforeach; ?>
  </div>
</div>