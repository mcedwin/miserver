<div class="container pt-4">

<a href="<?php echo base_url('databases/phpmyadmin') ?>" target="_blank" class="btn btn-warning">phpmyadmin</a>
<br><br>
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="lh-base mb-0">
                Bases de datos
            </h5>
            <a href="<?php echo base_url('databases/crear1') ?>" class="btn new1 btn-sm btn-success ms-2"><i class="fa-solid fa-plus"></i> Agregar</a>
        </div>
        <div class="card-body">

            <div class="border-bottom1 pb-2">
                <form class="ocform form-horizontal">

                    <div class="input-group input-group">
                        <input class="form-control " type="search" id="s" name="search[value]" placeholder="Buscar" value="" autocomplete="off">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php echo genDataTable('mitabla1', $columns1, true); ?>
        </div>
    </div>
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between">
            <h5 class="lh-base mb-0">
                Usuarios
            </h5>
            <a href="<?php echo base_url('databases/crear2') ?>" class="btn new2 btn-sm btn-success ms-2"><i class="fa-solid fa-plus"></i> Agregar</a>
        </div>
        <div class="card-body">

            <div class="border-bottom1 pb-2">
                <form class="ocform form-horizontal">
                    <div class="input-group input-group">
                        <input class="form-control " type="search" id="s" name="search[value]" placeholder="Buscar" value="" autocomplete="off">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php echo genDataTable('mitabla2', $columns2, true); ?>
        </div>

    </div>
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between">
            <h5 class="lh-base mb-0">
                Usuario con bases de datos
            </h5>
            <a href="<?php echo base_url('databases/crear3') ?>" class="btn new3 btn-sm btn-success ms-2"><i class="fa-solid fa-plus"></i> Agregar</a>
        </div>
        <div class="card-body">

            <div class="border-bottom1 pb-2">
                <form class="ocform form-horizontal">
                    <div class="input-group input-group">
                        <input class="form-control " type="search" id="s" name="search[value]" placeholder="Buscar" value="" autocomplete="off">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php echo genDataTable('mitabla3', $columns3, true); ?>
        </div>
    </div>
</div>