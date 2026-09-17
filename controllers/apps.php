<?php

declare(strict_types=1);

function ctrl_apps_index(): void
{
    $u = require_login();
    $ctx = ctx_user($u);
    $isAdmin = ($u['role'] ?? '') === 'admin';
    if ($isAdmin && query('u') === 'all') {
        $rows = db_all('SELECT a.*, u.user AS uname FROM app a JOIN user u ON u.id = a.user_id ORDER BY a.user_id, a.name');
    } else {
        $rows = db_all('SELECT a.*, u.user AS uname FROM app a JOIN user u ON u.id = a.user_id WHERE a.user_id = ? ORDER BY a.name', [$ctx['id']]);
    }
    foreach ($rows as $k => $row) {
        $rows[$k]['path'] = '/home/' . $row['uname'] . '/' . $row['folder'];
    }
    render('apps/index', [
        'title' => 'Aplicaciones',
        'active' => 'apps',
        'rows' => $rows,
        'ctx' => $ctx,
        'users' => $isAdmin ? users_for_select() : [],
    ]);
}

function ctrl_apps_create(): void
{
    $u = require_login();
    render('apps/form', [
        'title' => 'Nueva aplicación',
        'active' => 'apps',
        'row' => null,
        'ctx' => ctx_user($u),
        'users' => ($u['role'] ?? '') === 'admin' ? users_for_select() : [],
    ]);
}

function ctrl_apps_store(): void
{
    $u = require_login();
    csrf_check();
    [$name, $folder, $gitUrl, $gitBranch, $gitToken, $phpVersion] = app_validate_input();

    $targetId = ($u['role'] ?? '') === 'admin' ? post_int('user_id', (int) $u['id']) : (int) $u['id'];
    $owner = db_one('SELECT id, user FROM user WHERE id = ?', [$targetId]);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }

    if (db_one('SELECT id FROM app WHERE user_id = ? AND folder = ?', [$owner['id'], $folder])) {
        respond(false, 'Ya existe una aplicación en esa carpeta.');
    }

    $gitTokenCipher = $gitToken !== '' ? enc($gitToken) : '';

    // Clonar repositorio.
    $r = ctl_run(['git:clone', $owner['user'], $folder, $gitUrl, $gitBranch, $gitToken]);
    if ($r['exit'] !== 0) {
        respond(false, 'Error al clonar: ' . e($r['out']));
    }

    // Detectar tipo y document_root.
    $det = domain_detect($owner['user'], $folder, '');
    $type = (string) ($det['type'] ?? 'other');
    $docroot = domain_docroot_proposal($folder, '', (string) ($det['webroot'] ?? '.'));
    $documentRoot = str_replace($folder . '/', '', $docroot);
    if ($documentRoot === $folder) {
        $documentRoot = '';
    }

    db_run('INSERT INTO app (user_id, name, folder, project_type, git_url, git_branch, document_root, php_version, git_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $owner['id'], $name, $folder, $type, $gitUrl, $gitBranch, $documentRoot, $phpVersion, $gitTokenCipher,
    ]);
    $id = (int) db_last_id();

    // Despliegue inicial.
    $jid = job_create('deploy', $name, (int) $owner['id']);
    job_spawn($jid, ['app:deploy', $owner['user'], $folder, $phpVersion, 'all']);

    respond(true, 'Aplicación creada (tipo: ' . $type . '). DocumentRoot: ' . ($documentRoot ?: '(raíz)') . '. Despliegue en segundo plano.', url('apps'));
}

function ctrl_apps_edit(array $p): void
{
    $u = require_login();
    $row = app_row_or_fail($p[0], $u);
    render('apps/form', [
        'title' => 'Editar aplicación',
        'active' => 'apps',
        'row' => $row,
        'ctx' => ctx_user($u),
        'users' => ($u['role'] ?? '') === 'admin' ? users_for_select() : [],
    ]);
}

function ctrl_apps_update(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = app_row_or_fail($p[0], $u);
    [$name, $folder, $gitUrl, $gitBranch, $gitToken, $phpVersion] = app_validate_input(false);

    // folder no se puede cambiar desde aquí (implica mover archivos).
    $update = [
        'name' => $name,
        'git_url' => $gitUrl,
        'git_branch' => $gitBranch,
        'php_version' => $phpVersion,
    ];
    $params = [$name, $gitUrl, $gitBranch, $phpVersion];
    if ($gitToken !== '') {
        $params[] = enc($gitToken);
        $update['git_token'] = enc($gitToken);
    }
    $params[] = (int) $p[0];

    if ($gitToken !== '') {
        db_run('UPDATE app SET name = ?, git_url = ?, git_branch = ?, php_version = ?, git_token = ? WHERE id = ?', $params);
    } else {
        db_run('UPDATE app SET name = ?, git_url = ?, git_branch = ?, php_version = ? WHERE id = ?', $params);
    }

    // Actualizar dominios vinculados para que reflejen cambios.
    app_sync_linked_domains((int) $p[0]);

    respond(true, 'Aplicación actualizada.', url('apps'));
}

function ctrl_apps_deploy(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = app_row_or_fail($p[0], $u);
    $owner = app_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $jid = job_create('deploy', $row['name'], (int) $owner['id']);
    job_spawn($jid, ['app:deploy', $owner['user'], $row['folder'], (string) $row['php_version'], 'all']);
    respond(true, 'Despliegue iniciado.', url('jobs'));
}

function ctrl_apps_migrate(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = app_row_or_fail($p[0], $u);
    $owner = app_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $jid = job_create('deploy', $row['name'], (int) $owner['id']);
    job_spawn($jid, ['app:deploy', $owner['user'], $row['folder'], (string) $row['php_version'], 'migrate']);
    respond(true, 'Migraciones iniciadas.', url('jobs'));
}

function ctrl_apps_pull(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = app_row_or_fail($p[0], $u);
    $owner = app_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $gitToken = '';
    if (!empty($row['git_token'])) {
        $gitToken = dec($row['git_token']);
    }

    $jid = job_create('pull', $row['name'], (int) $owner['id']);
    job_spawn($jid, ['app:pull', $owner['user'], $row['folder'], (string) ($row['git_branch'] ?: 'main'), $gitToken, (string) $row['php_version']]);

    respond(true, 'Pull + despliegue iniciados en secuencia.', url('jobs'));
}

function ctrl_apps_reinstall(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = app_row_or_fail($p[0], $u);
    $owner = app_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $folder = $row['folder'];
    $gitUrl = $row['git_url'];
    $gitBranch = (string) ($row['git_branch'] ?: 'main');
    $gitToken = !empty($row['git_token']) ? dec($row['git_token']) : '';

    // Backup .env
    $envRel = $folder . '/.env';
    $envBackup = '';
    $re = ctl_run_exec(['fs:cat', $owner['user'], $envRel, '2097152']);
    if ($re['exit'] === 0) {
        $envBackup = $re['out'];
    }

    ctl_run(['fs:rmtree', $owner['user'], $folder]);
    $cr = ctl_run(['git:clone', $owner['user'], $folder, $gitUrl, $gitBranch, $gitToken]);
    if ($cr['exit'] !== 0) {
        respond(false, 'Error al clonar: ' . e($cr['out']));
    }
    if ($envBackup !== '') {
        ctl_run(['fs:write', $owner['user'], $envRel], $envBackup);
    }

    $jid = job_create('deploy', $row['name'], (int) $owner['id']);
    job_spawn($jid, ['app:deploy', $owner['user'], $folder, (string) $row['php_version'], 'all']);

    respond(true, 'Reinstalación iniciada. Se conservó el .env anterior.', url('apps'));
}

function ctrl_apps_env(array $p): void
{
    $u = require_login();
    $row = app_row_or_fail($p[0], $u);
    $owner = app_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $rel = $row['folder'] . '/.env';
    $content = '';
    $existed = false;
    $source = '';
    $r = ctl_run_exec(['fs:cat', $owner['user'], $rel, '2097152']);
    $directPath = '/home/' . $owner['user'] . '/' . $rel;
    if (($r['exit'] !== 0 || ($r['out'] ?? '') === '') && is_readable($directPath)) {
        $direct = @file_get_contents($directPath);
        if ($direct !== false && $direct !== '') {
            $r = ['exit' => 0, 'out' => $direct];
        }
    }
    $logDir = '/home/miserver/panel/tmp';
    @mkdir($logDir, 0770, true);
    $logData = json_encode(['time' => date('c'), 'exit' => $r['exit'], 'out_len' => strlen($r['out'] ?? ''), 'out' => $r['out'] ?? '']);
    $written = file_put_contents($logDir . '/miserver_env_debug.log', $logData . "\n", FILE_APPEND | LOCK_EX);
    error_log('MISERVER_ENV_DEBUG app_id=' . (int) $p[0] . ' written=' . ($written === false ? 'false' : (string) $written) . ' data=' . $logData);
    if ($r['exit'] === 0) {
        $content = $r['out'];
        $existed = true;
    } elseif (ctl_run(['fs:exists', $owner['user'], $rel])['exit'] === 0) {
        respond(false, 'No se pudo leer .env: ' . e($r['out']));
    } else {
        $exampleRel = app_env_example_path($row, $owner);
        if ($exampleRel !== null) {
            $re = ctl_run_exec(['fs:cat', $owner['user'], $exampleRel, '2097152']);
            if ($re['exit'] === 0) {
                $content = $re['out'];
                $source = basename($exampleRel);
            }
        }
    }
    render('apps/env', [
        'title' => '.env de ' . $row['name'],
        'active' => 'apps',
        'row' => $row,
        'owner' => $owner,
        'rel' => $rel,
        'content' => $content,
        'existed' => $existed,
        'source' => $source,
    ]);
}

function ctrl_apps_env_save(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = app_row_or_fail($p[0], $u);
    $owner = app_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $rel = $row['folder'] . '/.env';
    $content = (string) ($_POST['content'] ?? '');
    if (strlen($content) > 2 * 1024 * 1024) {
        respond(false, 'Contenido demasiado grande.');
    }
    $r = ctl_run(['fs:write', $owner['user'], $rel], $content);
    if ($r['exit'] !== 0) {
        respond(false, 'Error al guardar .env: ' . e($r['out']));
    }
    respond(true, '.env guardado.', url('apps/' . (int) $p[0] . '/env'));
}

function ctrl_apps_env_from_example(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = app_row_or_fail($p[0], $u);
    $owner = app_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $envRel = $row['folder'] . '/.env';

    $r = ctl_run(['fs:exists', $owner['user'], $envRel]);
    if ($r['exit'] === 0) {
        respond(true, '.env ya existe.', url('apps/' . (int) $p[0] . '/env'));
    }

    $exampleRel = app_env_example_path($row, $owner);
    if ($exampleRel === null) {
        respond(false, 'No se encontró .env.example, .env.local ni .env.dist en /home/' . e($owner['user']) . '/' . e($row['folder']) . '.');
    }

    $re = ctl_run_exec(['fs:cat', $owner['user'], $exampleRel, '2097152']);
    if ($re['exit'] !== 0) {
        respond(false, 'No se pudo leer ' . basename($exampleRel) . '. Error: ' . e($re['out']));
    }
    if ($re['out'] === '') {
        respond(false, basename($exampleRel) . ' existe pero está vacío.');
    }

    $w = ctl_run(['fs:write', $owner['user'], $envRel], $re['out']);
    if ($w['exit'] !== 0) {
        respond(false, 'Error al crear .env: ' . e($w['out']));
    }
    respond(true, '.env creado desde ' . basename($exampleRel) . '.', url('apps/' . (int) $p[0] . '/env'));
}

function ctrl_apps_delete(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = app_row_or_fail($p[0], $u);
    $owner = app_owner($row);
    if ($owner && post('delete_files') === '1') {
        ctl_run(['fs:rmtree', $owner['user'], $row['folder']]);
    }
    db_run('UPDATE domain SET app_id = NULL WHERE app_id = ?', [(int) $p[0]]);
    db_run('DELETE FROM app WHERE id = ?', [(int) $p[0]]);
    respond(true, 'Aplicación eliminada.', url('apps'));
}

/* ------------------------------------------------------------------ */
/* Helpers                                                            */
/* ------------------------------------------------------------------ */

function app_row_or_fail(int $id, array $u): array
{
    if (($u['role'] ?? '') === 'admin') {
        $row = db_one('SELECT * FROM app WHERE id = ?', [$id]);
    } else {
        $row = db_one('SELECT * FROM app WHERE id = ? AND user_id = ?', [$id, $u['id']]);
    }
    if (!$row) {
        respond(false, 'Aplicación no encontrada.');
    }
    return $row;
}

function app_owner(array $row): ?array
{
    return db_one('SELECT id, user FROM user WHERE id = ?', [(int) $row['user_id']]) ?: null;
}

function app_validate_input(bool $requireFolder = true): array
{
    $name = trim(post('name', '') ?? '');
    if ($name === '') {
        respond(false, 'El nombre de la aplicación es obligatorio.');
    }
    $folder = trim(post('folder', '') ?? '');
    if ($requireFolder && $folder === '') {
        respond(false, 'La carpeta es obligatoria.');
    }
    if ($folder !== '' && !preg_match(RE_FOLDER, $folder)) {
        respond(false, 'Carpeta no válida.');
    }
    foreach (explode('/', $folder) as $seg) {
        if ($seg === 'public_html') {
            respond(false, 'No se permite public_html dentro de la ruta del proyecto.');
        }
    }
    $gitUrl = trim(post('git_url', '') ?? '');
    $gitBranch = trim(post('git_branch', '') ?? '');
    if ($gitBranch === '') {
        $gitBranch = 'main';
    }
    $gitToken = trim(post('git_token', '') ?? '');
    if ($gitUrl === '' || !preg_match(RE_GITURL, $gitUrl)) {
        respond(false, 'URL de repositorio no válida.');
    }
    if (!preg_match(RE_GITBRANCH, $gitBranch)) {
        respond(false, 'Rama no válida.');
    }
    if ($gitToken !== '' && !preg_match(RE_GITTOKEN, $gitToken)) {
        respond(false, 'Token no válido.');
    }
    $phpVersion = trim(post('php_version', '') ?? '');
    if ($phpVersion !== '' && !preg_match(RE_PHPVER, $phpVersion)) {
        respond(false, 'Versión de PHP no válida.');
    }
    return [$name, $folder, $gitUrl, $gitBranch, $gitToken, $phpVersion];
}

function app_sync_linked_domains(int $appId): void
{
    $app = db_one('SELECT * FROM app WHERE id = ?', [$appId]);
    if (!$app) {
        return;
    }
    $appDocroot = (string) $app['document_root'];
    $documentRoot = $appDocroot !== '' ? $app['folder'] . '/' . $appDocroot : $app['folder'];
    db_run('UPDATE domain SET folder = ?, document_root = ?, php_version = ?, project_type = ? WHERE app_id = ?', [
        $app['folder'],
        $documentRoot,
        $app['php_version'],
        $app['project_type'],
        $appId,
    ]);
}

/** Devuelve la ruta completa de document_root de una app (relativa al home). */
function app_docroot_path(array $app): string
{
    $dr = (string) ($app['document_root'] ?? '');
    return $dr !== '' ? $app['folder'] . '/' . $dr : $app['folder'];
}

/** Busca un archivo de ejemplo de .env y devuelve su ruta relativa, o null. */
function app_env_example_path(array $row, array $owner): ?string
{
    foreach (['.env.example', '.env.local', '.env.dist'] as $name) {
        $rel = $row['folder'] . '/' . $name;
        $r = ctl_run(['fs:exists', $owner['user'], $rel]);
        if ($r['exit'] === 0) {
            return $rel;
        }
    }
    return null;
}