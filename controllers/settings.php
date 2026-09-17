<?php

declare(strict_types=1);

function ctrl_settings_index(): void
{
    $u = require_login();
    $cfg = db_config();
    $data = [
        'title' => 'Configuración',
        'active' => 'settings',
        'cfg' => $cfg,
        'me' => user_row((int) $u['id']),
    ];
    render('settings/index', $data);
}

function ctrl_settings_save(): void
{
    require_login();
    csrf_check();
    $panelName = trim(post('panel_name'));
    $domain = strtolower(trim(post('domain')));
    if ($panelName === '') { respond(false, 'Nombre del panel obligatorio.'); }
    $leEmail = trim(post('le_email'));
    if ($leEmail !== '' && !filter_var($leEmail, FILTER_VALIDATE_EMAIL)) {
        respond(false, "Correo de Let's Encrypt no válido.");
    }
    // Token: se guarda cifrado
    $doToken = trim(post('do_token'));
    db_run('UPDATE config SET panel_name = ?, domain = ?, le_email = ?, do_token = ? WHERE id = 1', [
        $panelName, $domain, $leEmail, $doToken,
    ]);
    respond(true, 'Configuración guardada.', url('settings'));
}

function ctrl_settings_password(): void
{
    $u = require_login();
    csrf_check();
    $me = user_row((int) $u['id']);
    $current = post('current');
    $new = post('new');
    if (!$me || !password_verify($current, $me['password'])) {
        respond(false, 'Contraseña actual incorrecta.');
    }
    if (strlen($new) < 8) { respond(false, 'Contraseña mínima 8 caracteres.'); }
    if ($new !== post('new2')) { respond(false, 'Las contraseñas no coinciden.'); }
    db_run('UPDATE user SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
    // Si el panel tiene cuenta linux, cambiar también la clave del sistema
    $role = ($me['role'] ?? '') === 'admin' ? 'admin' : '';
    $r = ctl_run(['user:setpw', $me['user'], $new, $role]);
    if ($r['exit'] !== 0) {
        respond(false, 'Se cambió la clave del panel pero no la de Linux: ' . e($r['out']));
    }
    respond(true, 'Contraseña actualizada. Vuelve a iniciar sesión.', url('logout'));
}