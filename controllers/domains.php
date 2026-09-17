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
    $folder = trim(post('folder', '') ?? '');
    $folder = $folder === '' ? $domain : $folder; // carpeta propia por dominio (evita chocar con public_html)
    $folder = require_match(RE_FOLDER, $folder, 'Carpeta no válida.');

    $gitUrl = trim(post('git_url', '') ?? '');
    $gitBranch = trim(post('git_branch', '') ?? '');
    if ($gitBranch === '') {
        $gitBranch = 'main';
    }
    $gitToken = trim(post('git_token', '') ?? '');
    if ($gitToken !== '' && !preg_match(RE_GITTOKEN, $gitToken)) {
        respond(false, 'Token no válido (8-150 caracteres; solo letras, números, . _ : -).');
    }
    $gitTokenCipher = $gitToken !== '' ? enc($gitToken) : '';
    $projectPath = trim(post('project_path', '') ?? '');
    $documentRoot = trim(post('document_root', '') ?? '');
    $phpVersion = trim(post('php_version', '') ?? '');

    if ($gitUrl !== '') {
        require_match(RE_GITURL, $gitUrl, 'URL de repositorio no válida (solo https y sin credenciales).');
        if (!preg_match(RE_GITBRANCH, $gitBranch)) {
            respond(false, 'Rama no válida.');
        }
        if ($projectPath !== '' && !relpath_ok($projectPath)) {
            respond(false, 'Ruta del proyecto no válida.');
        }
        if ($documentRoot !== '' && !relpath_ok($documentRoot)) {
            respond(false, 'DocumentRoot no válido.');
        }
        if ($phpVersion !== '' && !preg_match(RE_PHPVER, $phpVersion)) {
            respond(false, 'Versión de PHP no válida.');
        }
    }

    if (db_one('SELECT id FROM domain WHERE domain = ?', [$domain])) {
        respond(false, 'Ese dominio ya está registrado.');
    }
    $targetId = ($u['role'] ?? '') === 'admin' ? post_int('user_id', (int) $u['id']) : (int) $u['id'];
    $owner = db_one('SELECT id, user FROM user WHERE id = ?', [$targetId]);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }

    db_run('INSERT INTO domain (user_id, domain, folder, project_type, git_url, git_branch, git_token, project_path, document_root, php_version, `ssl`, enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)', [
        $owner['id'], $domain, $folder, '', $gitUrl, $gitBranch, $gitTokenCipher, $projectPath, $documentRoot, $phpVersion,
    ]);
    $id = (int) db_last_id();

    $type = '';
    if ($gitUrl !== '') {
        // 1) clonar -> 2) detectar tipo -> 3) proponer/confirmar DocumentRoot
        $r = ctl_run(['git:clone', $owner['user'], $folder, $gitUrl, $gitBranch, $gitToken]);
        if ($r['exit'] !== 0) {
            db_run('DELETE FROM domain WHERE id = ?', [$id]); // rollback: poder reintentar
            respond(false, 'Error al clonar el repositorio: ' . e($r['out']));
        }
        $det = domain_detect($owner['user'], $folder, $projectPath);
        $type = (string) ($det['type'] ?? 'other');
        if ($documentRoot === '') {
            $documentRoot = domain_docroot_proposal($folder, $projectPath, (string) ($det['webroot'] ?? '.'));
        }
        db_run('UPDATE domain SET project_type = ?, document_root = ? WHERE id = ?', [$type, $documentRoot, $id]);
    } elseif ($documentRoot === '') {
        $documentRoot = $folder; // comportamiento previo: la carpeta es el DocumentRoot
    }

    $r = ctl_run(['vhost:add', $owner['user'], $domain, $folder, $documentRoot, $phpVersion]);
    if ($r['exit'] !== 0) {
        db_run('DELETE FROM domain WHERE id = ?', [$id]);
        respond(false, 'Error al crear el vhost: ' . e($r['out']));
    }

    $do = do_create_domain($domain);
    if (!$do['ok']) {
        flash('warn', 'Dominio creado pero DNS: ' . $do['msg']);
    }

    if (post('ssl') !== '0') {
        $jid = job_create('cert', $domain, (int) $owner['id']);
        job_spawn($jid, ['cert:issue', $domain, db_config()['le_email'] ?? '']);
    }

    // Despliegue automático tras clonar: Composer + caches (sin migraciones;
    // estas son un paso manual con el botón Migrar, cuando la BD esté lista).
    if ($gitUrl !== '') {
        $appDir = $folder . ($projectPath !== '' ? '/' . $projectPath : '');
        $jid = job_create('deploy', $domain, (int) $owner['id']);
        job_spawn($jid, ['app:deploy', $owner['user'], $appDir, $phpVersion, 'all']);
    }

    respond(true, $gitUrl !== ''
        ? 'Aplicación creada (tipo: ' . $type . ', DocumentRoot: /home/' . $owner['user'] . '/' . $documentRoot . '). Composer + caches en segundo plano; migraciones con el botón Migrar cuando conectes la BD.'
        : 'Dominio creado.', url('domains'));
}

function ctrl_domains_edit(array $p): void
{
    $u = require_login();
    $row = domain_row_or_fail($p[0], $u);
    $data = [
        'title' => 'Editar aplicación',
        'active' => 'domains',
        'row' => $row,
        'owner' => domain_owner($row),
    ];
    render('domains/edit', $data);
}

function ctrl_domains_update(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);

    $type = post('project_type', '');
    if (!in_array($type, ['', 'laravel', 'wordpress', 'php', 'node', 'other'], true)) {
        respond(false, 'Tipo de proyecto no válido.');
    }
    $gitUrl = trim(post('git_url', '') ?? '');
    if ($gitUrl !== '' && !preg_match(RE_GITURL, $gitUrl)) {
        respond(false, 'URL de repositorio no válida.');
    }
    $gitBranch = trim(post('git_branch', '') ?? '');
    if ($gitBranch === '') {
        $gitBranch = 'main';
    }
    if (!preg_match(RE_GITBRANCH, $gitBranch)) {
        respond(false, 'Rama no válida.');
    }
    $projectPath = trim(post('project_path', '') ?? '');
    if ($projectPath !== '' && !relpath_ok($projectPath)) {
        respond(false, 'Ruta del proyecto no válida.');
    }
    $folder = (string) ($row['folder'] ?? '');
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
    $gitToken = trim(post('git_token', '') ?? '');
    if ($gitToken !== '' && !preg_match(RE_GITTOKEN, $gitToken)) {
        respond(false, 'Token no válido.');
    }

    if ($gitToken !== '') {
        db_run('UPDATE domain SET project_type = ?, git_url = ?, git_branch = ?, project_path = ?, document_root = ?, php_version = ?, git_token = ? WHERE id = ?', [
            $type, $gitUrl, $gitBranch, $projectPath, $documentRoot, $phpVersion, enc($gitToken), (int) $p[0],
        ]);
    } else {
        db_run('UPDATE domain SET project_type = ?, git_url = ?, git_branch = ?, project_path = ?, document_root = ?, php_version = ? WHERE id = ?', [
            $type, $gitUrl, $gitBranch, $projectPath, $documentRoot, $phpVersion, (int) $p[0],
        ]);
    }

    $owner = domain_owner($row);
    if ($owner) {
        $r = ctl_run(['vhost:add', $owner['user'], $row['domain'], $folder, $documentRoot, $phpVersion]);
        if ($r['exit'] !== 0) {
            respond(false, 'Configuración guardada pero no se pudo regenerar el vhost: ' . e($r['out']));
        }
    }
    respond(true, 'Aplicación actualizada y VirtualHost regenerado.', url('domains'));
}

function ctrl_domains_env(array $p): void
{
    $u = require_login();
    $row = domain_row_or_fail($p[0], $u);
    $owner = domain_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $rel = domain_app_dir($row) . '/.env';
    $content = '';
    $r = ctl_run_raw(['fs:cat', $owner['user'], $rel, '2097152']);
    if ($r['exit'] !== 0 || $r['err'] !== '') {
        $content = ''; // aún no existe: el editor lo creará al guardar
    } else {
        $content = $r['out'];
    }
    $data = [
        'title' => 'Editar .env',
        'active' => 'domains',
        'row' => $row,
        'owner' => $owner,
        'rel' => $rel,
        'content' => $content,
        'existed' => $content !== '' || ($r['exit'] === 0 && $r['err'] === ''),
    ];
    render('domains/env', $data);
}

function ctrl_domains_env_save(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    $owner = domain_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $rel = domain_app_dir($row) . '/.env';
    $content = (string) ($_POST['content'] ?? '');
    if (strlen($content) > 2 * 1024 * 1024) {
        respond(false, 'Contenido demasiado grande.');
    }
    $r = ctl_run(['fs:write', $owner['user'], $rel], $content);
    if ($r['exit'] !== 0) {
        respond(false, 'Error al guardar .env: ' . e($r['out']));
    }
    respond(true, '.env guardado.', url('domains/' . (int) $p[0] . '/env'));
}

function ctrl_domains_deploy(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    $owner = domain_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $dir = domain_app_dir($row);
    $pv = (string) ($row['php_version'] ?? '');
    $jid = job_create('deploy', $row['domain'], (int) $owner['id']);
    job_spawn($jid, ['app:deploy', $owner['user'], $dir, $pv, 'all']);
    respond(true, 'Despliegue iniciado (Composer + caches). Migraciones: usa el botón Migrar.', url('jobs'));
}

function ctrl_domains_migrate(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    $owner = domain_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $dir = domain_app_dir($row);
    $pv = (string) ($row['php_version'] ?? '');
    $jid = job_create('deploy', $row['domain'], (int) $owner['id']);
    job_spawn($jid, ['app:deploy', $owner['user'], $dir, $pv, 'migrate']);
    respond(true, 'Migraciones iniciadas (artisan migrate / spark migrate). Estado en Tareas.', url('jobs'));
}

function ctrl_domains_detect(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    $owner = domain_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $folder = (string) ($row['folder'] ?? '');
    $projectPath = (string) ($row['project_path'] ?? '');
    $det = domain_detect($owner['user'], $folder, $projectPath);
    $type = (string) ($det['type'] ?? 'other');
    $docroot = domain_docroot_proposal($folder, $projectPath, (string) ($det['webroot'] ?? '.'));
    db_run('UPDATE domain SET project_type = ?, document_root = ? WHERE id = ?', [$type, $docroot, (int) $p[0]]);
    $r = ctl_run(['vhost:add', $owner['user'], $row['domain'], $folder, $docroot, (string) ($row['php_version'] ?? '')]);
    if ($r['exit'] !== 0) {
        respond(false, 'No se pudo regenerar el vhost: ' . e($r['out']));
    }
    respond(true, 'Tipo: ' . $type . ' — DocumentRoot: /home/' . $owner['user'] . '/' . $docroot, url('domains'));
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

/** Directorio con el código de la aplicación (relativa al home), para .env/despliegue. */
function domain_app_dir(array $row): string
{
    $dir = (string) ($row['folder'] ?? '');
    if (($row['project_path'] ?? '') !== '') {
        $dir .= '/' . $row['project_path'];
    }
    return $dir;
}

/** DocumentRoot efectivo: almacenado o la carpeta base (compatibilidad). */
function domain_docroot(array $row): string
{
    $dr = (string) ($row['document_root'] ?? '');
    return $dr !== '' ? $dr : (string) ($row['folder'] ?? '');
}

/** Detecta el tipo de proyecto y su carpeta web propuesta (vía wrapper). */
function domain_detect(string $user, string $folder, string $projectPath): array
{
    $rel = $folder;
    if ($projectPath !== '') {
        $rel = $folder . '/' . $projectPath;
    }
    $info = ['type' => 'other', 'webroot' => '.'];
    $r = ctl_run(['git:detect', $user, $rel]);
    if ($r['exit'] !== 0) {
        return $info;
    }
    foreach (preg_split('/\r?\n/', $r['out']) as $line) {
        if ($line === '') {
            continue;
        }
        [$k, $v] = array_pad(explode('|', $line, 2), 2, '');
        if ($k === 'type') {
            $info['type'] = $v;
        }
        if ($k === 'webroot') {
            $info['webroot'] = $v;
        }
    }
    return $info;
}

/** DocumentRoot propuesto: carpeta + ruta del proyecto + carpeta web (p.ej. Laravel /public). */
function domain_docroot_proposal(string $folder, string $projectPath, string $webroot): string
{
    $parts = [];
    if ($projectPath !== '') {
        $parts[] = $projectPath;
    }
    if ($webroot !== '' && $webroot !== '.') {
        $parts[] = $webroot;
    }
    return $folder . ($parts ? '/' . implode('/', $parts) : '');
}