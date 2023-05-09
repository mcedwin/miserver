<div class="modal-dialog modal-lg">
    <div class="modal-content">
        <form class="form-horizontal needs-validation" action="<?= base_url("databases/guardar3/" . $id) ?>" method="post" enctype="multipart/form-data" novalidate>
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $titulo; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                            <?php
                            echo myinput($fields->idShema, '6', '', '', $shemas,'',$user->user.'_');
                            echo myinput($fields->idUser, '6', '', '', $users,'',$user->user.'_');
                             ?>
                     
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cerrar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>
