<?php

declare(strict_types=1);

function ctrl_jobs_index(): void
{
    require_login();
    $data = [
        'title' => 'Tareas',
        'active' => 'jobs',
        'jobs' => jobs_list(),
    ];
    render('jobs/index', $data);
}

function ctrl_jobs_poll(): void
{
    require_login();
    $rows = jobs_list();
    $out = array_map(static fn($j) => [
        'id' => (int) $j['id'],
        'status' => $j['status'],
        'target' => $j['target'],
        'output' => (string) $j['output'],
    ], $rows);
    json_out(['ok' => true, 'jobs' => $out]);
}

function ctrl_jobs_clear(): void
{
    require_login();
    csrf_check();
    db_run("DELETE FROM job WHERE status IN ('done','failed') AND finished_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    respond(true, 'Tareas antiguas eliminadas.', url('jobs'));
}