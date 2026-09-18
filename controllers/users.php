<?php

declare(strict_types=1);

function ctrl_users_index(): void
{
    require_admin();
    $users = db_all('SELECT u.*, COUNT(DISTINCT d.id) AS n_domains, COUNT(DISTINCT s.id) AS n_dbs
        FROM user u
        LEFT JOIN domain d ON d.user_id = u.id
        LEFT JOIN db_shema s ON s.user_id = u.id
        GROUP BY u.id ORDER BY u.user');
    $data = [
        'title' => 'Usuarios',
        'active' => 'users',
        'users' => $users,
    ];
    render('users/index', $data);
}

function ctrl_users_create(): void
{
    require_admin();
    $data = [
        'title' => 'Nuevo usuario',
        'active' => 'users',
        'editing' => false,
        'fields' => [
            'user' => ['label' => 'Usuario (cuenta linux)', 'value' => '', 'required' => true],
            'name' => ['label' => 'Nombre visible', 'value' => ''],
            'description' => ['label' => 'Descripción', 'value' => ''],
            'domain' => ['label' => 'Dominio principal', 'value' => '', 'required' => true],
            'password' => ['label' => 'Contraseña', 'value' => '', 'required' => true],
            'password2' => ['label' => 'Repite contraseña', 'value' => '', 'required' => true],
        ],
    ];
    render('users/form', $data);
}

function ctrl_users_store(): void
{
    require_admin();
    csrf_check();
    $user = require_match(RE_USERNAME, post('user'), 'Usuario: 3-32 caracteres minúsculas (a-z y 0-9_)');
    $domain = require_match(RE_DOMAIN, strtolower(post('domain')), 'Dominio no válido.');
    $pass = valid_panel_password(post('password'), 'Contraseña');
    if ($pass !== post('password2')) { respond(false, 'Las contraseñas no coinciden.'); }
    if (db_one('SELECT id FROM user WHERE user = ?', [$user])) { respond(false, 'Usuario ya existe.'); }
    if (db_one('SELECT id FROM domain WHERE domain = ?', [$domain])) { respond(false, 'Dominio ya registrado.'); }

    db_run('INSERT INTO user (user, name, description, domain, password, role, active) VALUES (?, ?, ?, ?, ?, ?, 1)', [
        $user, post('name'), post('description'), $domain, password_hash($pass, PASSWORD_DEFAULT), 'user',
    ]);
    $uid = (int) db_last_id();
    db_run('INSERT INTO domain (user_id, domain, folder, `ssl`, enabled) VALUES (?, ?, ?, 0, 1)', [$uid, $domain, 'public_html']);

    $r = ctl_run(['user:add', $user, $pass, $domain]);
    if ($r['exit'] !== 0) {
        db_run('DELETE FROM domain WHERE user_id = ?', [$uid]);
        db_run('DELETE FROM user WHERE id = ?', [$uid]);
        respond(false, 'Error del sistema: ' . e($r['out']));
    }

    $do = do_create_domain($domain);
    if (!$do['ok']) {
        flash('warn', $do['msg']);
    }

    respond(true, 'Usuario creado.', url('users'));
}

function ctrl_users_edit(array $p): void
{
    require_admin();
    $row = user_row($p[0]);
    if (!$row) { redirect('users'); }
    $row['password'] = '';
    $row['password2'] = '';
    $data = [
        'title' => 'Editar usuario',
        'active' => 'users',
        'editing' => true,
        'id' => $p[0],
        'fields' => [
            'user'        => ['label' => 'Usuario linux', 'value' => $row['user'], 'required' => true, 'readonly' => true],
            'name'        => ['label' => 'Nombre', 'value' => $row['name']],
            'description' => ['label' => 'Descripción', 'value' => $row['description']],
            'domain'      => ['label' => 'Dominio', 'value' => $row['domain'], 'required' => true],
            'password'    => ['label' => 'Contraseña nueva (dejar vacío si no cambia)', 'value' => '', 'required' => false],
            'password2'   => ['label' => 'Repite contraseña', 'value' => '', 'required' => false],
        ],
    ];
    render('users/form', $data);
}

function ctrl_users_update(array $p): void
{
    require_admin();
    csrf_check();
    $id = (int) $p[0];
    $row = user_row($id);
    if (!$row) { respond(false, 'Usuario no encontrado.'); }
    if ($id === 1 && post('user') !== $row['user']) { respond(false, 'No se puede modificar el usuario principal.'); }

    $domain = require_match(RE_DOMAIN, strtolower(post('domain')), 'Dominio no válido.');
    if ($domain !== $row['domain'] && db_one('SELECT id FROM domain WHERE domain = ?', [$domain])) {
        respond(false, 'Ese dominio ya está registrado.');
    }
    $pass = post('password');
    if ($pass !== '') {
        $pass = valid_panel_password($pass, 'Contraseña');
        db_run('UPDATE user SET password = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
        $role = ($row['role'] ?? '') === 'admin' ? 'admin' : '';
        $rset = ctl_run(['user:setpw', $row['user'], $pass, $role]);
        if ($rset['exit'] !== 0) {
            respond(false, 'Error del sistema: ' . e($rset['out']));
        }
    }
    db_run('UPDATE user SET name = ?, description = ?, domain = ? WHERE id = ?', [
        post('name'), post('description'), $domain, $id,
    ]);
    if ($domain !== $row['domain']) {
        do_create_domain($domain);
    }
    respond(true, 'Usuario actualizado.', url('users'));
}

function ctrl_users_destroy(array $p): void
{
    require_admin();
    csrf_check();
    $id = (int) $p[0];
    if ($id === 1) { respond(false, 'No se puede eliminar la cuenta principal.'); }
    $row = user_row($id);
    if (!$row) { respond(false, 'Usuario no encontrado.'); }
    $prefix = $row['user'];

    // Borrar usuarios de BD, esquemas, dominios vía wrapper (best-effort)
    foreach (db_all('SELECT * FROM db_user WHERE user_id = ?', [$id]) as $u) {
        ctl_run(['dbu:del', $prefix . '_' . $u['user']]);
    }
    foreach (db_all('SELECT * FROM db_shema WHERE user_id = ?', [$id]) as $s) {
        ctl_run(['db:del', $prefix . '_' . $s['name']]);
    }
    foreach (db_all('SELECT * FROM domain WHERE user_id = ?', [$id]) as $d) {
        do_delete_domain($d['domain']);
    }
    $r = ctl_run(['user:del', $prefix]);
    if ($r['exit'] !== 0) {
        respond(false, 'Error del sistema: ' . e($r['out']));
    }
    db_run('DELETE FROM user WHERE id = ?', [$id]);
    respond(true, 'Usuario eliminado.', url('users'));
}

function ctrl_users_toggle(array $p): void
{
    require_admin();
    csrf_check();
    db_run('UPDATE user SET active = NOT active WHERE id = ?', [(int) $p[0]]);
    respond(true, 'Estado actualizado.', url('users'));
}

function ctrl_users_db_admin(array $p): void
{
    require_admin();
    csrf_check();
    $row = user_row((int) $p[0]);
    if (!$row) { respond(false, 'Usuario no encontrado.'); }
    $r = ctl_run(['db:admin', $row['user']]);
    if ($r['exit'] !== 0) { respond(false, 'Error del sistema: ' . e($r['out'])); }
    respond(true, 'Acceso total (todas las BD) concedido a ' . e($row['user']) . '.', url('users'));
}

function ctrl_users_db_unadmin(array $p): void
{
    require_admin();
    csrf_check();
    $row = user_row((int) $p[0]);
    if (!$row) { respond(false, 'Usuario no encontrado.'); }
    $r = ctl_run(['db:unadmin', $row['user']]);
    if ($r['exit'] !== 0) { respond(false, 'Error del sistema: ' . e($r['out'])); }
    respond(true, 'Acceso total revocado a ' . e($row['user']) . '.', url('users'));
}

function ctrl_users_db_own(array $p): void
{
    require_admin();
    csrf_check();
    $row = user_row((int) $p[0]);
    if (!$row) { respond(false, 'Usuario no encontrado.'); }
    $r = ctl_run(['db:own', $row['user']]);
    if ($r['exit'] !== 0) { respond(false, 'Error del sistema: ' . e($r['out'])); }
    respond(true, 'Acceso a todas las BD de ' . e($row['user']) . ' concedido.', url('users'));
}

function ctrl_users_db_unown(array $p): void
{
    require_admin();
    csrf_check();
    $row = user_row((int) $p[0]);
    if (!$row) { respond(false, 'Usuario no encontrado.'); }
    $r = ctl_run(['db:unown', $row['user']]);
    if ($r['exit'] !== 0) { respond(false, 'Error del sistema: ' . e($r['out'])); }
    respond(true, 'Acceso a las BD de ' . e($row['user']) . ' revocado.', url('users'));
}