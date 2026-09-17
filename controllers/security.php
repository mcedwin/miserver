<?php

declare(strict_types=1);

function ctrl_security_index(): void
{
    $u = require_login();
    if (($u['role'] ?? '') !== 'admin') {
        redirect('home');
    }
    $rows = db_all('SELECT ip, COUNT(*) AS total, MAX(attempted_at) AS last_at
        FROM login_attempts
        WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY ip
        ORDER BY total DESC, last_at DESC');
    render('security/index', [
        'title' => 'Seguridad',
        'active' => 'security',
        'rows' => $rows,
    ]);
}

function ctrl_security_ban(): void
{
    $u = require_login();
    csrf_check();
    if (($u['role'] ?? '') !== 'admin') {
        respond(false, 'No autorizado.');
    }
    $ip = query('ip', '');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        respond(false, 'IP no válida.');
    }
    $r = ctl_run(['security:ban', $ip]);
    if ($r['exit'] !== 0) {
        respond(false, 'Error al bloquear: ' . e($r['out']));
    }
    db_run('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    respond(true, 'IP ' . e($ip) . ' bloqueada en el firewall.', url('security'));
}

function ctrl_security_unban(): void
{
    $u = require_login();
    csrf_check();
    if (($u['role'] ?? '') !== 'admin') {
        respond(false, 'No autorizado.');
    }
    $ip = query('ip', '');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        respond(false, 'IP no válida.');
    }
    $r = ctl_run(['security:unban', $ip]);
    if ($r['exit'] !== 0) {
        respond(false, 'Error al desbloquear: ' . e($r['out']));
    }
    respond(true, 'IP ' . e($ip) . ' desbloqueada.', url('security'));
}

function ctrl_security_clear(): void
{
    require_login();
    csrf_check();
    db_run('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    respond(true, 'Intentos fallidos antiguos eliminados.', url('security'));
}