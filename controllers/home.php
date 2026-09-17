<?php

declare(strict_types=1);

function ctrl_home_index(): void
{
    $u = require_login();

    $stats = [
        'usuarios'   => db_count('user'),
        'dominios'   => db_count('domain'),
        'bases'      => db_count('db_shema'),
        'tareas'     => db_count('job', "status = 'running'"),
    ];

    if (($u['role'] ?? '') === 'admin') {
        $sites = db_all('SELECT d.*, u.user AS uname, u.domain AS udomain FROM domain d JOIN user u ON u.id = d.user_id ORDER BY d.domain');
    } else {
        $sites = db_all('SELECT d.*, u.user AS uname FROM domain d JOIN user u ON u.id = d.user_id WHERE d.user_id = ? ORDER BY d.domain', [$u['id']]);
    }

    $info = sys_info();

    $data = [
        'title' => 'Inicio',
        'active' => 'home',
        'stats' => $stats,
        'sites' => $sites,
        'info' => $info,
        'homes' => parse_pipe_lines($info['homes']['raw'] ?? ''),
        'backups' => parse_pipe_lines($info['backups']['raw'] ?? ''),
        'jobs' => db_all("SELECT id, kind, target, status, created_at FROM job ORDER BY id DESC LIMIT 6"),
    ];
    render('home/index', $data);
}

function ctrl_home_backup(): void
{
    require_login();
    csrf_check();
    $uid = (int) current_user()['id'];
    $jid = job_create('backup', 'todo el servidor', $uid);
    job_spawn($jid, ['backup:run']);
    respond(true, 'Backup en curso. Cuando termine podrás descargarlo desde Inicio.', url('jobs'));
}

function ctrl_home_download(): void
{
    require_login();
    $f = query('f', '');
    $dir = rtrim(env('BACKUP_DIR', '/var/backups/miserver'), '/');
    if ($f === '' || !preg_match('/^[a-zA-Z0-9._-]+\.(tar\.gz|sql\.gz|gz)$/', $f)) {
        respond(false, 'Archivo no válido.');
    }
    $full = $dir . '/' . $f;
    $real = realpath($full);
    if ($real === false || strpos(str_replace('\\', '/', $real), rtrim(str_replace('\\', '/', realpath($dir) ?: $dir), '/') . '/') !== 0) {
        respond(false, 'Archivo no encontrado.');
    }
    if (!is_readable($real)) {
        respond(false, 'El archivo de backup no es legible por el panel (permisos).');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $f . '"');
    header('Content-Length: ' . (string) filesize($real));
    readfile($real);
    exit;
}

function ctrl_home_backup_delete(): void
{
    require_login();
    csrf_check();
    $f = query('f', '');
    if ($f === '' || !preg_match('/^[a-zA-Z0-9._-]+\.(tar\.gz|sql\.gz|gz)$/', $f)) {
        respond(false, 'Archivo no válido.');
    }
    $r = ctl_run(['backup:del', $f]);
    if ($r['exit'] !== 0) {
        respond(false, 'Error al eliminar el backup: ' . e($r['out']));
    }
    respond(true, 'Backup eliminado.', url('disk'));
}