<?php

declare(strict_types=1);

function ctrl_domains_index(): void
{
    $u = require_login();
    if (($u['role'] ?? '') === 'admin') {
        $rows = db_all('SELECT d.*, u.user AS uname, u.domain AS udomain FROM domain d JOIN user u ON u.id = d.user_id ORDER BY d.domain');
    } else {
        $rows = db_all('SELECT d.*, u.user AS uname FROM domain d JOIN user u ON u.id = d.user_id WHERE d.user_id = ? ORDER BY d.domain', [$u['id']]);
    }
    $data = [
        'title' => 'Dominios',
        'active' => 'domains',
        'rows' => $rows,
        'users' => ($u['role'] ?? '') === 'admin' ? users_for_select() : [],
        'ctx' => user_row(ctx_user_id($u)),
    ];
    render('domains/index', $data);
}

function ctrl_domains_store(): void
{
    $u = require_login();
    csrf_check();
    $domain = require_match(RE_DOMAIN, strtolower(post('domain')), 'Dominio no válido.');
    $folder = require_match(RE_FOLDER, post('folder', 'public_html') !== '' ? post('folder') : 'public_html', 'Carpeta no válida.');

    if (db_one('SELECT id FROM domain WHERE domain = ?', [$domain])) {
        respond(false, 'Ese dominio ya está registrado.');
    }
    $targetId = ($u['role'] ?? '') === 'admin' ? post_int('user_id', (int) $u['id']) : (int) $u['id'];
    $owner = db_one('SELECT id, user FROM user WHERE id = ?', [$targetId]);
    if (!$owner) { respond(false, 'Usuario no válido.'); }

    db_run('INSERT INTO domain (user_id, domain, folder, ssl, enabled) VALUES (?, ?, ?, 0, 1)', [
        $owner['id'], $domain, $folder,
    ]);
    $id = (int) db_last_id();

    $r = ctl_run(['vhost:add', $owner['user'], $domain, $folder]);
    if ($r['exit'] !== 0) { respond(false, 'Error al crear el vhost: ' . e($r['out'])); }

    $do = do_create_domain($domain);
    if (!$do['ok']) {
        flash('warn', 'Dominio creado pero DNS: ' . $do['msg']);
    }

    if (post('ssl') === '1') {
        $jid = job_create('cert', $domain, (int) $owner['id']);
        job_spawn($jid, ['cert:issue', $domain, db_config()['le_email'] ?? '']);
    }
    respond(true, 'Dominio creado.', url('domains'));
}

function ctrl_domains_destroy(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    ctl_run(['vhost:del', $row['domain']]);
    do_delete_domain($row['domain']);
    db_run('DELETE FROM domain WHERE id = ?', [(int) $p[0]]);
    respond(true, 'Dominio eliminado.', url('domains'));
}

function ctrl_domains_toggle(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    $new = (int) $row['enabled'] === 1 ? 0 : 1;
    $r = ctl_run(['vhost:toggle', $row['domain'], $new === 1 ? 'on' : 'off']);
    if ($r['exit'] !== 0) { respond(false, 'Error al activar/desactivar: ' . e($r['out'])); }
    db_run('UPDATE domain SET enabled = ? WHERE id = ?', [$new, (int) $p[0]]);
    respond(true, 'Dominio ' . ($new ? 'activado' : 'desactivado') . '.', url('domains'));
}

function ctrl_domains_ssl(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    $email = db_config()['le_email'] ?? '';
    $id = job_create('cert', $row['domain'], (int) $row['user_id']);
    job_spawn($id, ['cert:issue', $row['domain'], $email]);
    respond(true, 'Certificado en proceso. Puedes verlo en Tareas.', url('jobs'));
}

function ctrl_domains_dns(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    $r = do_create_domain($row['domain']);
    respond($r['ok'], $r['msg'], url('domains'));
}

function domain_row_or_fail(int $id, array $u): array
{
    if (($u['role'] ?? '') === 'admin') {
        $row = db_one('SELECT * FROM domain WHERE id = ?', [$id]);
    } else {
        $row = db_one('SELECT * FROM domain WHERE id = ? AND user_id = ?', [$id, $u['id']]);
    }
    if (!$row) { respond(false, 'Dominio no encontrado.'); }
    return $row;
}