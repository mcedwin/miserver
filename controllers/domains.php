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
    $data = [
        'title' => 'Dominios',
        'active' => 'domains',
        'rows' => $rows,
        'users' => $isAdmin ? users_for_select() : [],
        'ctx' => user_row($ctx['id']),
        'apps' => $isAdmin
            ? db_all('SELECT a.id, a.name, a.folder, u.user AS uname FROM app a JOIN user u ON u.id = a.user_id ORDER BY u.user, a.name')
            : db_all('SELECT id, name, folder, NULL AS uname FROM app WHERE user_id = ? ORDER BY name', [$ctx['id']]),
    ];
    render('domains/index', $data);
}

function ctrl_domains_store(): void
{
    $u = require_login();
    csrf_check();
    $domain = require_match(RE_DOMAIN, strtolower(post('domain')), 'Dominio no válido.');
    $appId = post_int('app_id', 0);

    $owner = null;
    if ($appId > 0) {
        $app = db_one('SELECT * FROM app WHERE id = ?', [$appId]);
        if (!$app) {
            respond(false, 'Aplicación no válida.');
        }
        if (($u['role'] ?? '') !== 'admin' && (int) $app['user_id'] !== (int) $u['id']) {
            respond(false, 'Aplicación no válida para este usuario.');
        }
        $owner = db_one('SELECT id, user FROM user WHERE id = ?', [$app['user_id']]);
    } else {
        $targetId = ($u['role'] ?? '') === 'admin' ? post_int('user_id', (int) $u['id']) : (int) $u['id'];
        $owner = db_one('SELECT id, user FROM user WHERE id = ?', [$targetId]);
    }
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }

    $folder = '';
    $documentRoot = '';
    $phpVersion = '';
    $projectType = '';
    $gitUrl = '';
    $gitBranch = '';
    $gitTokenCipher = '';
    $projectPath = '';
    $gitToken = '';

    if ($appId > 0) {
        // Dominio vinculado a una App existente: usa sus datos y su usuario.
        $folder = $app['folder'];
        $appDocroot = (string) $app['document_root'];
        $documentRoot = $appDocroot !== '' ? $app['folder'] . '/' . $appDocroot : $app['folder'];
        $phpVersion = $app['php_version'];
        $projectType = $app['project_type'];
    } else {
        // Creación legacy: dominio con su propia carpeta/repo.
        $folder = trim(post('folder', '') ?? '');
        $folder = $folder === '' ? $domain : $folder;
        $folder = require_match(RE_FOLDER, $folder, 'Carpeta no válida.');
        foreach (explode('/', $folder) as $seg) {
            if ($seg === 'public_html') {
                respond(false, 'No se permite public_html dentro de la ruta del proyecto; usa otra carpeta.');
            }
        }
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
    }

    if (db_one('SELECT id FROM domain WHERE domain = ?', [$domain])) {
        respond(false, 'Ese dominio ya está registrado.');
    }

    db_run('INSERT INTO domain (user_id, app_id, domain, folder, project_type, git_url, git_branch, git_token, project_path, document_root, php_version, `ssl`, enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)', [
        $owner['id'], $appId ?: null, $domain, $folder, $projectType, $gitUrl, $gitBranch, $gitTokenCipher, $projectPath, $documentRoot, $phpVersion,
    ]);
    $id = (int) db_last_id();

    $type = $projectType;
    if ($appId === 0 && $gitUrl !== '') {
        // 1) clonar -> 2) detectar tipo -> 3) proponer/confirmar DocumentRoot
        $r = ctl_run(['git:clone', $owner['user'], $folder, $gitUrl, $gitBranch, $gitToken]);
        if ($r['exit'] !== 0) {
            domain_rollback($owner['user'], $folder, $domain, $id, 'Error al clonar el repositorio: ' . $r['out'], true);
        }
        $det = domain_detect($owner['user'], $folder, $projectPath);
        $type = (string) ($det['type'] ?? 'other');
        if ($documentRoot === '') {
            $documentRoot = domain_docroot_proposal($folder, $projectPath, (string) ($det['webroot'] ?? '.'));
        }
        db_run('UPDATE domain SET project_type = ?, document_root = ? WHERE id = ?', [$type, $documentRoot, $id]);
    } elseif ($appId === 0 && $documentRoot === '') {
        $documentRoot = $folder;
    }

    $r = ctl_run(['vhost:add', $owner['user'], $domain, $folder, $documentRoot, $phpVersion]);
    if ($r['exit'] !== 0) {
        domain_rollback($owner['user'], $folder, $domain, $id, 'Error al crear el vhost: ' . $r['out'], $appId === 0 && $gitUrl !== '');
    }

    $do = do_create_domain($domain);
    if (!$do['ok']) {
        flash('warn', 'Dominio creado pero DNS: ' . $do['msg']);
    }

    if (post('ssl') !== '0') {
        $jid = job_create('cert', $domain, (int) $owner['id']);
        job_spawn($jid, ['cert:issue', $domain, db_config()['le_email'] ?? '']);
    }

    // Despliegue automático solo en creación legacy con repo.
    if ($appId === 0 && $gitUrl !== '') {
        $appDir = $folder . ($projectPath !== '' ? '/' . $projectPath : '');
        $jid = job_create('deploy', $domain, (int) $owner['id']);
        job_spawn($jid, ['app:deploy', $owner['user'], $appDir, $phpVersion, 'all']);
    }

    $msg = $appId > 0
        ? 'Dominio vinculado a la aplicación (DocumentRoot: /home/' . $owner['user'] . '/' . $documentRoot . ').'
        : ($gitUrl !== ''
            ? 'Aplicación creada (tipo: ' . $type . ', DocumentRoot: /home/' . $owner['user'] . '/' . $documentRoot . '). Composer + caches en segundo plano; migraciones con el botón Migrar cuando conectes la BD.'
            : 'Dominio creado.');
    respond(true, $msg, url('domains'));
}

function ctrl_domains_edit(array $p): void
{
    $u = require_login();
    $row = domain_row_or_fail($p[0], $u);
    $owner = domain_owner($row);
    $apps = [];
    if ($owner) {
        $apps = db_all('SELECT id, name, folder FROM app WHERE user_id = ? ORDER BY name', [$owner['id']]);
    }
    $data = [
        'title' => 'Editar aplicación',
        'active' => 'domains',
        'row' => $row,
        'owner' => $owner,
        'apps' => $apps,
        'ssl_info' => domain_ssl_info((string) $row['domain']),
    ];
    render('domains/edit', $data);
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

    $appId = post_int('app_id', 0);
    $folder = '';
    $documentRoot = '';
    $phpVersion = '';
    $projectType = '';
    $gitUrl = '';
    $gitBranch = '';
    $gitTokenCipher = '';
    $projectPath = '';

    if ($appId > 0) {
        $app = db_one('SELECT * FROM app WHERE id = ? AND user_id = ?', [$appId, $owner['id']]);
        if (!$app) {
            respond(false, 'Aplicación no válida para este usuario.');
        }
        $folder = $app['folder'];
        $appDocroot = (string) $app['document_root'];
        $documentRoot = $appDocroot !== '' ? $app['folder'] . '/' . $appDocroot : $app['folder'];
        $phpVersion = $app['php_version'];
        $projectType = $app['project_type'];
    } else {
        // Dominio legacy: edición manual de metadatos.
        $projectType = post('project_type', '');
        if (!in_array($projectType, ['', 'laravel', 'wordpress', 'php', 'node', 'other'], true)) {
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
        $gitTokenCipher = $gitToken !== '' ? enc($gitToken) : '';
    }

    $params = [$appId ?: null, $folder, $projectType, $gitUrl, $gitBranch, $gitTokenCipher, $projectPath, $documentRoot, $phpVersion, (int) $p[0]];
    db_run('UPDATE domain SET app_id = ?, folder = ?, project_type = ?, git_url = ?, git_branch = ?, git_token = ?, project_path = ?, document_root = ?, php_version = ? WHERE id = ?', $params);

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
    $existed = false;
    $suggested = false;
    $r = ctl_run_raw(['fs:cat', $owner['user'], $rel, '2097152']);
    if ($r['exit'] === 0 && $r['err'] === '') {
        $content = $r['out'];
        $existed = true;
    } else {
        // El .env no existe todavía: proponer el .env.example del repo si está disponible.
        $exampleRel = domain_app_dir($row) . '/.env.example';
        $re = ctl_run_raw(['fs:cat', $owner['user'], $exampleRel, '2097152']);
        if ($re['exit'] === 0 && $re['err'] === '') {
            $content = $re['out'];
            $suggested = true;
            // Personalizar sugerencias básicas para este dominio.
            $domain = (string) ($row['domain'] ?? '');
            if ($domain !== '') {
                $content = preg_replace('/^APP_URL=https?:\/\/localhost\b/m', "APP_URL=http://$domain", $content);
                $content = preg_replace('/^APP_URL=$/m', "APP_URL=http://$domain", $content);
            }
        }
    }
    $data = [
        'title' => 'Editar .env',
        'active' => 'domains',
        'row' => $row,
        'owner' => $owner,
        'rel' => $rel,
        'content' => $content,
        'existed' => $existed,
        'suggested' => $suggested,
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
    $eff = domain_effective($row);
    $dir = domain_app_dir($row);
    $pv = (string) $eff['php_version'];
    $jid = job_create('deploy', $row['domain'], (int) $owner['id']);
    job_spawn($jid, ['app:deploy', $owner['user'], $dir, $pv, 'all']);
    respond(true, 'Despliegue iniciado (Composer + caches). Migraciones: usa el botón Migrar.', url('jobs'));
}

function ctrl_domains_pull(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    $owner = domain_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $eff = domain_effective($row);
    $gitUrl = trim($eff['git_url']);
    if ($gitUrl === '') {
        respond(false, 'Este dominio no tiene repositorio Git.');
    }
    $gitToken = '';
    if (!empty($eff['git_token'])) {
        $gitToken = dec($eff['git_token']);
    }
    $folder = $eff['folder'];
    $appDir = domain_app_dir($row);
    $pv = (string) $eff['php_version'];

    $jid = job_create('gitpull', $row['domain'], (int) $owner['id']);
    job_spawn($jid, ['git:pull', $owner['user'], $folder, (string) ($eff['git_branch'] ?: 'main'), $gitToken]);

    $jid2 = job_create('deploy', $row['domain'], (int) $owner['id']);
    job_spawn($jid2, ['app:deploy', $owner['user'], $appDir, $pv, 'all']);

    respond(true, 'Pull iniciado. Al terminar se desplegará Composer + caches.', url('jobs'));
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
    $eff = domain_effective($row);
    $dir = domain_app_dir($row);
    $pv = (string) $eff['php_version'];
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
    if (!empty($row['app_id'])) {
        respond(false, 'Los dominios vinculados a una app usan el tipo detectado de la app; edita la app si es necesario.');
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

function ctrl_domains_reinstall(array $p): void
{
    $u = require_login();
    csrf_check();
    $row = domain_row_or_fail($p[0], $u);
    $owner = domain_owner($row);
    if (!$owner) {
        respond(false, 'Usuario no válido.');
    }
    $eff = domain_effective($row);
    $gitUrl = trim($eff['git_url']);
    if ($gitUrl === '') {
        respond(false, 'Este dominio no tiene repositorio Git.');
    }
    $folder = $eff['folder'];
    $appDir = domain_app_dir($row);
    $envRel = $appDir . '/.env';

    // Conservar .env actual si existe.
    $envBackup = '';
    $re = ctl_run_raw(['fs:cat', $owner['user'], $envRel, '2097152']);
    if ($re['exit'] === 0 && $re['err'] === '') {
        $envBackup = $re['out'];
    }

    // Borrar carpeta del clon anterior.
    $rr = ctl_run(['fs:rmtree', $owner['user'], $folder]);
    if ($rr['exit'] !== 0) {
        respond(false, 'Error al borrar la carpeta anterior: ' . e($rr['out']));
    }

    // Clonar de nuevo.
    $gitToken = '';
    if (!empty($eff['git_token'])) {
        $gitToken = dec($eff['git_token']);
    }
    $cr = ctl_run(['git:clone', $owner['user'], $folder, $gitUrl, (string) ($eff['git_branch'] ?: 'main'), $gitToken]);
    if ($cr['exit'] !== 0) {
        respond(false, 'Error al clonar el repositorio: ' . e($cr['out']));
    }

    // Restaurar .env.
    if ($envBackup !== '') {
        ctl_run(['fs:write', $owner['user'], $envRel], $envBackup);
    }

    // Regenerar vhost y desplegar.
    ctl_run(['vhost:add', $owner['user'], $row['domain'], $folder, (string) $eff['document_root'], (string) $eff['php_version']]);
    $jid = job_create('deploy', $row['domain'], (int) $owner['id']);
    job_spawn($jid, ['app:deploy', $owner['user'], $appDir, (string) $eff['php_version'], 'all']);

    respond(true, 'Reinstalación iniciada. Se conservó el .env anterior si existía.', url('domains'));
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
        $eff = domain_effective($row);
        ctl_run(['fs:rmtree', $owner['user'], $eff['folder']]);
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

/** Directorio con el código de la aplicación (relativa al home), para .env/despliegue. */
function domain_app_dir(array $row): string
{
    $eff = domain_effective($row);
    $dir = $eff['folder'];
    if (($row['project_path'] ?? '') !== '') {
        $dir .= '/' . $row['project_path'];
    }
    return $dir;
}

/** Datos efectivos de un dominio: si está vinculado a una app, toma los de la app. */
function domain_effective(array $row): array
{
    if (!empty($row['app_id'])) {
        $app = db_one('SELECT * FROM app WHERE id = ?', [(int) $row['app_id']]);
        if ($app) {
            $dr = (string) $app['document_root'];
            return [
                'folder' => $app['folder'],
                'document_root' => $dr !== '' ? $app['folder'] . '/' . $dr : $app['folder'],
                'php_version' => $app['php_version'],
                'project_type' => $app['project_type'],
                'git_url' => $app['git_url'],
                'git_branch' => $app['git_branch'],
                'git_token' => $app['git_token'],
                'app_name' => $app['name'],
            ];
        }
    }
    return [
        'folder' => $row['folder'] ?? '',
        'document_root' => $row['document_root'] ?? '',
        'php_version' => $row['php_version'] ?? '',
        'project_type' => $row['project_type'] ?? '',
        'git_url' => $row['git_url'] ?? '',
        'git_branch' => $row['git_branch'] ?? '',
        'git_token' => $row['git_token'] ?? '',
        'app_name' => '',
    ];
}

/** DocumentRoot efectivo relativo al home. */
function domain_docroot(array $row): string
{
    return (string) domain_effective($row)['document_root'];
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