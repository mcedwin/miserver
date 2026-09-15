<?php

declare(strict_types=1);

function ctrl_dbs_index(): void
{
    $u = require_login();
    $ctx = ctx_user($u);
    $data = [
        'title' => 'Bases de datos',
        'active' => 'dbs',
        'ctx' => $ctx,
        'shemas' => db_all('SELECT * FROM db_shema WHERE user_id = ? ORDER BY name', [$ctx['id']]),
        'dbusers' => db_all('SELECT * FROM db_user WHERE user_id = ? ORDER BY `user`', [$ctx['id']]),
        'relations' => db_all('SELECT r.iduser, r.idshema, s.name AS sname, du.user AS dbu
            FROM db_relation r
            JOIN db_shema s ON s.id = r.idshema
            JOIN db_user du ON du.id = r.iduser
            WHERE s.user_id = ? AND du.user_id = ?
            ORDER BY s.name', [$ctx['id'], $ctx['id']]),
        'users' => ($u['role'] ?? '') === 'admin' ? users_for_select() : [],
    ];
    render('dbs/index', $data);
}

function ctrl_dbs_db_store(): void
{
    $u = require_login();
    csrf_check();
    $ctx = ctx_user($u);
    $name = require_match(RE_DBNAME, post('name'), 'Nombre de BD: solo minúsculas, números y _');
    if (db_one('SELECT id FROM db_shema WHERE user_id = ? AND name = ?', [$ctx['id'], $name])) {
        respond(false, 'Esa base de datos ya existe.');
    }
    db_run('INSERT INTO db_shema (user_id, name) VALUES (?, ?)', [$ctx['id'], $name]);
    $r = ctl_run(['db:add', db_prefix($ctx['user'], $name)]);
    if ($r['exit'] !== 0) { respond(false, 'Error del sistema: ' . e($r['out'])); }
    respond(true, 'Base de datos creada.', url('dbs'));
}

function ctrl_dbs_db_delete(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = db_one('SELECT s.*, u.user AS uname FROM db_shema s JOIN user u ON u.id = s.user_id WHERE s.id = ? AND s.user_id = ?', [
        (int) $p[0], ctx_user_id($u),
    ]);
    if (!$row) { respond(false, 'Base de datos no encontrada.'); }
    ctl_run(['db:del', db_prefix($row['uname'], $row['name'])]);
    db_run('DELETE FROM db_shema WHERE id = ?', [(int) $p[0]]);
    respond(true, 'Base de datos eliminada.', url('dbs'));
}

function ctrl_dbs_dbu_store(): void
{
    $u = require_login();
    csrf_check();
    $ctx = ctx_user($u);
    $name = require_match(RE_DBUSER, post('user'), 'Usuario BD: solo minúsculas, números y _');
    $pass = post('password');
    if (strlen($pass) < 8) { respond(false, 'Contraseña mínima 8 caracteres.'); }
    require_match(RE_PASSWORD, $pass, 'Contraseña con caracteres no permitidos (solo letras, números y !@#$%^&*()_+-=[]{};:,.<>?~).');
    if (db_one('SELECT id FROM db_user WHERE user_id = ? AND user = ?', [$ctx['id'], $name])) {
        respond(false, 'Ese usuario ya existe.');
    }
    db_run('INSERT INTO db_user (user_id, user, password) VALUES (?, ?, ?)', [$ctx['id'], $name, enc($pass)]);
    $r = ctl_run(['dbu:add', db_prefix($ctx['user'], $name), $pass]);
    if ($r['exit'] !== 0) {
        db_run('DELETE FROM db_user WHERE id = ?', [(int) db_last_id()]);
        respond(false, 'Error del sistema: ' . e($r['out']));
    }
    respond(true, 'Usuario de base de datos creado.', url('dbs'));
}

function ctrl_dbs_dbu_delete(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = db_one('SELECT w.*, u.user AS uname FROM db_user w JOIN user u ON u.id = w.user_id WHERE w.id = ? AND w.user_id = ?', [
        (int) $p[0], ctx_user_id($u),
    ]);
    if (!$row) { respond(false, 'Usuario BD no encontrado.'); }
    ctl_run(['dbu:del', db_prefix($row['uname'], $row['user'])]);
    db_run('DELETE FROM db_user WHERE id = ?', [(int) $p[0]]);
    respond(true, 'Usuario de BD eliminado.', url('dbs'));
}

function ctrl_dbs_dbu_pass(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = db_one('SELECT w.*, u.user AS uname FROM db_user w JOIN user u ON u.id = w.user_id WHERE w.id = ? AND w.user_id = ?', [
        (int) $p[0], ctx_user_id($u),
    ]);
    if (!$row) { respond(false, 'Usuario BD no encontrado.'); }
    $new = random_password(16);
    $prefix = db_prefix($row['uname'], $row['user']);
    $r = ctl_run(['dbu:setpw', $prefix, $new]);
    if ($r['exit'] !== 0) { respond(false, 'Error del sistema: ' . e($r['out'])); }
    db_run('UPDATE db_user SET password = ? WHERE id = ?', [enc($new), (int) $p[0]]);
    respond(true, 'Nueva contraseña de la BD: <code>' . e($new) . '</code> (guárdala ahora)', url('dbs'));
}

function ctrl_dbs_rel_store(): void
{
    $u = require_login();
    csrf_check();
    $ctx = ctx_user($u);
    $iduser = post_int('iduser');
    $idshema = post_int('idshema');
    $du = db_one('SELECT user FROM db_user WHERE id = ? AND user_id = ?', [$iduser, $ctx['id']]);
    $ds = db_one('SELECT name FROM db_shema WHERE id = ? AND user_id = ?', [$idshema, $ctx['id']]);
    if (!$du || !$ds) { respond(false, 'Selecciona un usuario y una base válidos.'); }
    if (db_one('SELECT 1 FROM db_relation WHERE iduser = ? AND idshema = ?', [$iduser, $idshema])) {
        respond(false, 'Esa relación ya existe.');
    }
    db_run('INSERT INTO db_relation (iduser, idshema) VALUES (?, ?)', [$iduser, $idshema]);
    $r = ctl_run(['db:grant', db_prefix($ctx['user'], $ds['name']), db_prefix($ctx['user'], $du['user'])]);
    if ($r['exit'] !== 0) { respond(false, 'Error del sistema: ' . e($r['out'])); }
    respond(true, 'Relación creada.', url('dbs'));
}

function ctrl_dbs_rel_delete(array $p): void
{
    $u = require_login();
    csrf_check();
    $iduser = (int) $p[0];
    $idshema = (int) $p[1];
    $row = db_one('SELECT s.name AS sname, du.user AS dbu, u.user AS uname
        FROM db_relation r
        JOIN db_shema s ON s.id = r.idshema
        JOIN db_user du ON du.id = r.iduser
        JOIN user u ON u.id = s.user_id
        WHERE r.iduser = ? AND r.idshema = ? AND s.user_id = ?', [$iduser, $idshema, ctx_user_id($u)]);
    if ($row) {
        ctl_run([
            'db:revoke',
            db_prefix($row['uname'], $row['sname']),
            db_prefix($row['uname'], $row['dbu']),
        ]);
    }
    db_run('DELETE FROM db_relation WHERE iduser = ? AND idshema = ?', [$iduser, $idshema]);
    respond(true, 'Relación eliminada.', url('dbs'));
}