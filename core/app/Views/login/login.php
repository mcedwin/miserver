<form action="<?php echo base_url('login/ingresar') ?>" class="needs-validation form-login" method="post" novalidate>

    <?php
    $fields->password->required = true;
    $fields->password->type = 'password';
    echo myinput($fields->user, '12');
    echo myinput($fields->password, '12');
    ?>

    <div class="form-group mb-2">
        <button type="submit" class="btn btn-warning w-100"><i class="fas fa-sign-in-alt"></i> Ingresar</button>
    </div>

</form>