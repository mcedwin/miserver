<?php

declare(strict_types=1);

function ctrl_domains_index(): void
{
    $u = require_login();
    $ctx = ctx_user($u);
    $isAdmin = ($u['role'] ?? '') === 'admin';
    if ($isAdmin) {
        $rows = db_all('SELECT d.*, u.user AS uname, u.domain AS udomain FROM domain d JOIN user u ON u.id = d.user_id ORDER BY d.domain');
    } else {
        $rows = db_all('SELECT d.*, u.user AS uname FROM domain d JOIN user u ON u.id = d.user_id WHERE d.user_id = ? ORDER BY d.domain', [$u['id']]);
    }
    foreach ($rows as $k => $row) {
        $rows[$k]['ssl_info'] = domain_ssl_info((string) $row['domain']);
    }
    render('domains/index', [
        'title' => 'Dominios',
        'active' => 'domains',
        'rows' => $rows,
        'users' => $isAdmin ? users_for_select() : [],
        'ctx' => user_row($ctx['id']),
    ]);
}

function ctrl_domains_store(): void
{
    $u = require_login();
    csrf_check();
    $domain = require_match(RE_DOMAIN, strtolower(post('domain')), 'Dominio no válido.');

    $targetId = ($u['role'] ?? '') === 'admin' ? post_int('user_id', (int) $u['id']) : (int) $u['id'];
    $owner = db_one('SELECT id, user FROM user WHERE id = ?', [$targetId]);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }

    $folder = trim(post('folder', '') ?? '');
    $folder = $folder === '' ? $domain : $folder;
    $folder = require_match(RE_FOLDER, $folder, 'Carpeta no válida.');
    foreach (explode('/', $folder) as $seg) {
        if ($seg === 'public_html') {
            respond(false, 'No se permite public_html dentro de la ruta del proyecto; usa otra carpeta.');
        }
    }

    $documentRoot = trim(post('document_root', '') ?? '');
    if ($documentRoot === '') {
        $documentRoot = $folder;
    }
    if (!relpath_ok($documentRoot)) {
        respond(false, 'DocumentRoot no válido.');
    }
    $phpVersion = trim(post('php_version', '') ?? '');
    if ($phpVersion !== '' && !preg_match(RE_PHPVER, $phpVersion)) {
        respond(false, 'Versión de PHP no válida.');
    }

    if (db_one('SELECT id FROM domain WHERE domain = ?', [$domain])) {
        respond(false, 'Ese dominio ya está registrado.');
    }

    db_run('INSERT INTO domain (user_id, domain, folder, document_root, php_version, `ssl`, enabled) VALUES (?, ?, ?, ?, ?, 0, 1)', [
        $owner['id'], $domain, $folder, $documentRoot, $phpVersion,
    ]);
    $id = (int) db_last_id();

    $r = ctl_run(['vhost:add', $owner['user'], $domain, $folder, $documentRoot, $phpVersion]);
    if ($r['exit'] !== 0) {
        domain_rollback($owner['user'], $folder, $domain, $id, 'Error al crear el vhost: ' . $r['out'], false);
    }

    $do = do_create_domain($domain);
    if (!$do['ok']) {
        flash('warn', 'Dominio creado pero DNS: ' . $do['msg']);
    }

    if (post('ssl') !== '0') {
        $jid = job_create('cert', $domain, (int) $owner['id']);
        job_spawn($jid, ['cert:issue', $domain, db_config()['le_email'] ?? '']);
    }

    respond(true, 'Dominio creado.', url('domains'));
}

function ctrl_domains_edit(array $p): void
{
    $u = require_login();
    $row = domain_row_or_fail($p[0], $u);
    $owner = domain_owner($row);
    render('domains/edit', [
        'title' => 'Editar dominio',
        'active' => 'domains',
        'row' => $row,
        'owner' => $owner,
        'ssl_info' => domain_ssl_info((string) $row['domain']),
    ]);
}

/** Consulta el estado del certificado SSL vía wrapper (live/ no es legible por PHP). */
function domain_ssl_info(string $domain): array
{
    $info = ['status' => 'missing', 'exp' => '', 'days' => 0];
    if ($domain === '') {
        return $info;
    }
    $r = ctl_run(['cert:status', $domain]);
    if ($r['exit'] === 0) {
        $parts = explode('|', trim($r['out']));
        if (count($parts) >= 2 && $parts[0] === 'status') {
            $info['status'] = $parts[1];
            $info['exp'] = $parts[2] ?? '';
            $info['days'] = isset($parts[3]) ? (int) $parts[3] : 0;
        }
    }
    return $info;
}

function ctrl_domains_update(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    $owner = domain_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }

    $folder = trim(post('folder', '') ?? '');
    if ($folder === '') {
        $folder = (string) ($row['folder'] ?? '');
    }
    $documentRoot = trim(post('document_root', '') ?? '');
    if ($documentRoot === '') {
        $documentRoot = $folder;
    }
    if (!relpath_ok($documentRoot)) {
        respond(false, 'DocumentRoot no válido.');
    }
    $phpVersion = trim(post('php_version', '') ?? '');
    if ($phpVersion !== '' && !preg_match(RE_PHPVER, $phpVersion)) {
        respond(false, 'Versión de PHP no válida.');
    }

    // Desvinculamos cualquier app heredada: los dominios ahora se configuran manualmente.
    db_run('UPDATE domain SET app_id = NULL, folder = ?, document_root = ?, php_version = ? WHERE id = ?', [
        $folder, $documentRoot, $phpVersion, (int) $p[0],
    ]);

    $r = ctl_run(['vhost:add', $owner['user'], $row['domain'], $folder, $documentRoot, $phpVersion]);
    if ($r['exit'] !== 0) {
        respond(false, 'Configuración guardada pero no se pudo regenerar el vhost: ' . e($r['out']));
    }
    respond(true, 'Dominio actualizado y VirtualHost regenerado.', url('domains'));
}

function ctrl_domains_logs(array $p): void
{
    $u = require_login();
    $row = domain_row_or_fail($p[0], $u);
    $type = in_array(query('type', ''), ['error', 'access'], true) ? query('type') : 'error';
    $lines = min(max(query_int('lines', 200), 1), 10000);
    $r = ctl_run(['vhost:logs', (string) $row['domain'], $type, (string) $lines]);
    $log = $r['exit'] === 0 ? $r['out'] : 'Error al leer el log: ' . trim($r['out']);
    render('domains/logs', [
        'title' => 'Logs de ' . $row['domain'],
        'active' => 'domains',
        'row' => $row,
        'type' => $type,
        'lines' => $lines,
        'log' => $log,
    ]);
}

function ctrl_domains_destroy(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    $owner = domain_owner($row);
    ctl_run(['vhost:del', $row['domain']]);
    do_delete_domain($row['domain']);
    if (post('delete_files') === '1' && $owner) {
        // Solo se borra la carpeta base del dominio; public_html/home quedan protegidos.
        ctl_run(['fs:rmtree', $owner['user'], (string) $row['folder']]);
    }
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
    if ($r['exit'] !== 0) {
        respond(false, 'Error al activar/desactivar: ' . e($r['out']));
    }
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
    if (!$row) {
        respond(false, 'Dominio no encontrado.');
    }
    return $row;
}

function domain_owner(array $row): ?array
{
    return db_one('SELECT id, user FROM user WHERE id = ?', [(int) $row['user_id']]) ?: null;
}

/**
 * Rollback de una creación de dominio/vhost a medias (best-effort): elimina la
 * fila, el vhost (conf + reload de Apache) y, si $removeFolder (flujo GitHub),
 * la carpeta del proyecto clonada, para que el reintento no falle con
 * "carpeta ya existe" / "vhost existente".
 */
function domain_rollback(string $user, string $folder, string $domain, int $id, string $msg, bool $removeFolder = false): never
{
    ctl_run(['vhost:del', $domain]);        // quita conf y recarga Apache si existía
    if ($removeFolder) {
        ctl_run(['fs:rmtree', $user, $folder]); // quita restos del clon
    }
    db_run('DELETE FROM domain WHERE id = ?', [$id]);
    respond(false, $msg);
}