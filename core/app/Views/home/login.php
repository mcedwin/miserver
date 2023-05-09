<div class="container mt-4">
    <div class="card">
        <div class="card-header">Mi Servidor</div>

        <div class="card-body">
            <form action="<?php echo base_url('home/crear') ?>" class="needs-validation form-login" method="post" novalidate>
                <div class="form-group mb-2">
                    <label for="">Dominio</label>
                    <input type="text" class="form-control" name="domain" required>
                </div>
                <div class="form-group mb-2">
                    <label for="">Usuario</label>
                    <input type="text" class="form-control" name="user" required>
                </div>
                <div class="form-group mb-2">
                    <label for="">Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <div class="form-group mb-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt"></i> Crear mi Servidor</button>
                </div>
            </form>
        </div>
    </div>
</div>