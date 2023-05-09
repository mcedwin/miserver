<div class="container pt-4">

    <div class="card">
        <div class="card-header">
            Editar perfil
        </div>
        <div class="card-body">
            <form class="formu form-horizontal needs-validation" action="<?php echo base_url('/users/guardar_perfil') ?>" method="post" enctype="multipart/form-data" novalidate>
                <div class="row">
                    <?php
                    echo myinput($fields->user, '6','','disabled');
                    echo myinput($fields->domain, '6','','disabled');
                    $fields->password->type = 'password';
                    echo myinput($fields->password, '6');
                    echo myinput($fields->description, '12');
                    ?>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
            </form>
        </div>
    </div>

</div>