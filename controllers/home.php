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
        'jobs' => db_all("SELECT id, kind, target, status, created_at FROM job ORDER BY id DESC LIMIT 6"),
    ];
    render('home/index', $data);
}

