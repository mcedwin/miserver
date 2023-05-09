<div class="container content mt-3">
    <div class="row">
        <div class="col-md-4 offset-md-4">
            <div class="card gedf-card">
                <div class="card-header">
                    Cambiar contraseña
                </div>
                <div class="card-body">

                    <form action="<?php echo base_url("login/proc_cambiar/{$password2}") ?>" class="form-login needs-validation mr-2" method="post" novalidate>
                        <div class="form-group mb-2">
                            <label for="">Email</label>
                            <input type="text" class="form-control" name="email" value="<?php echo $email?>" readonly>
                        </div>
                        <div class="form-group mb-2">
                            <label for="">Contraseña</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-sign-in-alt"></i> Cambiar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

</div>