<?php

declare(strict_types=1);

function ctrl_cron_index(): void
{
    $u = require_login();
    $ctx = ctx_user($u);
    $r = ctl_run(['cron:show', $ctx['user']]);
    $text = $r['exit'] === 0 ? $r['out'] : '';
    $data = [
        'title' => 'Cron',
        'active' => 'cron',
        'ctx' => $ctx,
        'text' => $text,
        'wrapper_ok' => ctl_available(),
        'users' => ($u['role'] ?? '') === 'admin' ? users_for_select() : [],
    ];
    render('cron/index', $data);
}

function ctrl_cron_save(): void
{
    $u = require_login();
    csrf_check();
    $ctx = ctx_user($u);
    $text = post('texto');
    $text = str_replace("\r", '', $text);
    if (strlen($text) > 100000) { respond(false, 'El cron es demasiado grande.'); }
    $r = ctl_run(['cron:set', $ctx['user']], $text);
    if ($r['exit'] !== 0) { respond(false, 'Error al guardar el cron: ' . e($r['out'])); }
    respond(true, 'Cron guardado.', url('cron'));
}