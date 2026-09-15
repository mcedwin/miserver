<?php

declare(strict_types=1);

function ctrl_setup_index(): void
{
    if (current_user() !== null && panel_installed()) {
        redirect('home');
    }
    $data = ['title' => 'Configuración inicial'];
    if (!db_schema_exists()) {
        $data['error'] = 'El esquema de base de datos no está instalado. '
            . 'Ejecuta el script de instalación <code>bash instalarserver.sh</code> en el servidor.';
    } elseif (panel_installed()) {
        $data['done'] = true;
    }
    render_plain('setup/setup', $data);
}

function ctrl_setup_run(): void
{
    csrf_check();
    if (!db_schema_exists()) {
        respond(false, 'El esquema de base de datos no existe. Ejecuta instalarserver.sh.');
    }
    if (panel_installed()) {
        respond(false, 'El panel ya está configurado.');
    }

    $user = require_match(RE_USERNAME, post('user'), 'Usuario: 3-32 letras minúsculas, números y _');
    $domain = require_match(RE_DOMAIN, strtolower(post('domain')), 'Dominio no válido (ej: miserver.com)');
    $pass = post('password');
    if (strlen($pass) < 8) {
        respond(false, 'La contraseña debe tener al menos 8 caracteres.');
    }
    if ($pass !== post('password2')) {
        respond(false, 'Las contraseñas no coinciden.');
    }
    $doToken = trim(post('do_token'));

    if (db_one('SELECT id FROM user WHERE user = ?', [$user])) {
        respond(false, 'Ese usuario ya existe.');
    }
    if (db_one('SELECT id FROM domain WHERE domain = ?', [$domain])) {
        respond(false, 'Ese dominio ya está en uso.');
    }

    // Cuenta raíz del panel
    db_run('INSERT INTO user (user, name, description, domain, password, role, active) VALUES (?, ?, ?, ?, ?, ?, 1)', [
        $user, 'Administrador', 'Cuenta principal', $domain, password_hash($pass, PASSWORD_DEFAULT), 'admin',
    ]);
    $uid = (int) db_last_id();

    // Configuración
    db_run('INSERT INTO config (id, domain, do_token, panel_name, le_email) VALUES (1, ?, ?, ?, ?)', [
        $domain, $doToken, 'Mi Server', post('le_email'),
    ]);

    // Sitio principal del usuario
    db_run('INSERT INTO domain (user_id, domain, folder, ssl, enabled) VALUES (?, ?, ?, 0, 1)', [
        $uid, $domain, 'public_html',
    ]);

    // Crear cuenta linux + vhost + usuario MySQL (via wrapper privilegiado)
    if ($dom = ctl_run(['user:add', $user, $pass, $domain])) {
        if ($dom['exit'] !== 0) {
            respond(false, 'El usuario no se pudo crear en el sistema: ' . e($dom['out']));
        }
    }

    // DNS automático en DigitalOcean (opcional)
    $r = do_create_domain($domain);
    if (!$r['ok']) {
        flash('warn', 'Dominio creado pero DNS no: ' . $r['msg']);
    }

    login_as(['id' => $uid, 'user' => $user, 'name' => 'Administrador', 'role' => 'admin']);
    csrf_token();
    respond(true, 'Panel configurado correctamente.', url('home'));
}