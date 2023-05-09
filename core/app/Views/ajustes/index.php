<div class="container pt-4">
    <div class="row">
        <div class="col-md-3">
            <?php include(APPPATH . 'Views/templates/menu_perfil.php'); ?>
        </div>

        <div class="col-md-9">
            <div class="card mb-3">
                <div class="card-header">
                    Datos de la Institución
                </div>
                <div class="card-body">
                    <form class="formu form-horizontal needs-validation" action="<?php echo base_url('/ajustes/guardar') ?>" method="post" enctype="multipart/form-data" novalidate>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-2 text-center">
                                    <div class="mb-2 text-center">
                                        <img class="img-fluid img-thumbnail" width="200" src="<?php echo $fields->conf_logo->value; ?>?r=<?php echo rand(0, 1000); ?>" id="viewfoto">
                                    </div>
                                    <a href="" class="changephoto btn btn-success btn-sm"><i class="fas fa-edit"></i> Cambiar logo</a>
                                    <input type="file" class="inputfile" id="conf_logo" name="foto">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <?php
                                    echo myinput($fields->conf_institucion, '12');
                                    echo myinput($fields->conf_direccion, '12');
                                    echo myinput($fields->conf_telefono, '6');
                                    echo myinput($fields->conf_email, '6');
                                    echo myinput($fields->conf_periodo, '6');
                                    echo myinput($fields->conf_descripcion, '12');
                                    ?>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>


            <div class="card mb-3">
                <div class="card-header">
                    Correo electrónico
                </div>
                <div class="card-body">
                    <form class="formu form-horizontal needs-validation" action="<?php echo base_url('/ajustes/guardar') ?>" method="post" enctype="multipart/form-data" novalidate>
                        <div class="row mb-4">
                            <?php
                            echo myinput($fields->conf_mail_reply, '12');
                            echo myinput($fields->conf_mail_nreply, '12');
                            ?>
                        </div>
                        <div class="row">
                            <?php
                            echo myinput($fields->conf_mail_activo, '12');
                            echo myinput($fields->conf_mail_host, '6');
                            echo myinput($fields->conf_mail_user, '6');
                            echo myinput($fields->conf_mail_pass, '6');
                            echo myinput($fields->conf_mail_port, '6');
                            echo myinput($fields->conf_mail_crypto, '6');
                            ?>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>

                    </form>
                </div>
            </div>


            <div class="card">
                <div class="card-header">
                    Presentación
                </div>
                <div class="card-body">
                    <form id="form" class="form form-horizontal needs-validation" action="<?php echo base_url('/ajustes/guardargeneral') ?>" method="post" enctype="multipart/form-data" novalidate>
                        <div class="row">
                            <?php
                            echo myinput($fields->conf_portada_presentacion, '12','', 'rows=15');
                            echo myinput($fields->conf_portada_alerta, '12');
                            echo myinput($fields->conf_acceso_registrar, '12');
                            echo myinput($fields->conf_acceso_enviar, '12');
                            echo myinput($fields->conf_acceso_recursos, '12');
                            ?>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>