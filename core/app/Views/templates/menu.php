<?php if ($conmenu) : ?>
    <div class="">
        <div class="container">
            <div class="d-flex justify-content-between my-2">
                <div>
                    <a class="navbar-brand py-0" href="<?php echo base_url(); ?>">
                        <h5>MiServer</h5>
                    </a>
                </div>


                <div class="col-6 text-end d-none d-lg-block">
                    <a href="<?php echo base_url('users/perfil'); ?>" class="btn btn-light"><i class="fa-solid fa-user"></i> <?php echo empty($user->ofic) ? $user->name : $user->ofic . ' (' . $user->name . ')'  ?></a>
                    <?php if ($user->type == '1') : ?>
                        <a href="<?php echo base_url('users/perfil'); ?>" class="btn btn-light"><i class="fa-solid fa-gear"></i> Administrar</a>
                    <?php endif; ?>
                    <a href="<?php echo base_url('login/salir'); ?>" class="btn btn-outline-danger border-0"><i class="fa-solid fa-arrow-right-from-bracket"></i> Salir</a>
                </div>
                <div class="col-6 d-md-block d-lg-none text-end">
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fa fa-user" aria-hidden="true"></i>
                            <?php echo session()->get('user')  ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item" href="<?php echo base_url('users/perfil'); ?>"><i class="fa-solid fa-gear"></i> Perfil</a>
                            <a class="dropdown-item" href="<?php echo base_url('login/salir'); ?>"><i class="fa-solid fa-arrow-right-from-bracket"></i> Salir</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php if (!empty($user->id)) : ?>
        <nav class="bg-primary-subtle border border-primary-subtle p-2 shadow-sm">
            <div class="container">

                <div class="d-flex justify-content-between">

                    <div class="d-block d-lg-none">
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo empty($user->ofic) ? 'Menu' : $user->ofic; ?>

                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton1">
                                <?php
                                $uri = service('uri');
                                foreach ($menu_top as $mid => $m) :
                                    $active = "";
                                    if (preg_match("#{$m['base']}#i", $controller)) $active = "active";
                                ?>
                                    <li>
                                        <a class="dropdown-item <?php echo $active; ?>" href="<?php echo base_url($m['url'])  ?>">
                                            <i class="<?php echo $m['ico']; ?>"></i>
                                            <?php echo $m['name']; ?>
                                        </a>
                                    </li>
                                <?php
                                endforeach;
                                ?>
                            </ul>
                        </div>

                    </div>
                    <div class="d-none d-lg-block">
                        <?php
                        $uri = service('uri');
                        foreach ($menu_top as $mid => $m) :
                            $active = "light";
                            if (preg_match("#{$m['base']}#i", $uri->getPath())) $active = "primary";
                        ?>
                            <a class="btn btn-<?php echo $active; ?> rounded-1" href="<?php echo base_url($m['url'])  ?>">
                                <i class="<?php echo $m['ico']; ?>"></i>
                                <?php echo $m['name']; ?>
                            </a>
                        <?php
                        endforeach;
                        ?>
                    </div>
                </div>
            </div>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<main>