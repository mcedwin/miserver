<div class="container pt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between border-bottom pb-2 my-3">
                <h5 class="lh-base mb-0">
                    Dominios
                </h5>
                <a href="<?php echo base_url('domains/crear') ?>" class="btn new btn-sm btn-success ms-2"><i class="fa-solid fa-plus"></i> Nuevo</a>
            </div>
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
            <?php echo genDataTable('mitabla', $columns, true); ?>
        </div>
    </div>
</div>