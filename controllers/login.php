<?php

declare(strict_types=1);

function ctrl_login_login(): void
{
    if (current_user() !== null) {
        redirect('home');
    }
    $data = ['title' => 'Iniciar sesión', 'active' => ''];
    render_plain('login/login', $data);
}

function ctrl_login_attempt(): void
{
    csrf_check();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (is_locked_out($ip)) {
        respond(false, 'Demasiados intentos. Espera 10 minutos.');
    }
    $username = post('user', '');
    $password = post('password', '');
    $row = db_one('SELECT id, user, name, role, active, password FROM user WHERE user = ?', [$username]);

    if ($row
        && (int) $row['active'] === 1
        && $row['password'] !== ''
        && password_verify($password, $row['password'])
    ) {
        clear_failed_attempts($ip);
        login_as([
            'id'   => (int) $row['id'],
            'user' => $row['user'],
            'name' => $row['name'],
            'role' => $row['role'],
        ]);
        db_run('UPDATE user SET lastip = ?, last_login = NOW() WHERE id = ?', [$ip, $row['id']]);
        respond(true, 'Bienvenido.', url('home'));
    }

    register_failed_attempt($ip);
    respond(false, 'Usuario o contraseña incorrectos.');
}

function ctrl_login_logout(): void
{
    logout();
    redirect('login');
}