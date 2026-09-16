<?php

declare(strict_types=1);

/**
 * Mi Server - Panel de control.
 * Front controller mínimo: enruta cada petición a un controlador explícito.
 */

require __DIR__ . '/app/bootstrap.php';

$ROUTES = [
    ['GET',  '/',                   ['home',    'index']],
    ['GET',  '/home',               ['home',    'index']],
    ['GET',  '/download',           ['home',    'download']],
    ['POST', '/backup',             ['home',    'backup']],
    ['GET',  '/login',              ['login',   'login']],
    ['POST', '/login',              ['login',   'attempt']],
    ['POST', '/logout',             ['login',   'logout']],
    ['GET',  '/setup',              ['setup',   'index']],
    ['POST', '/setup',              ['setup',   'run']],

    ['GET',  '/users',              ['users',   'index']],
    ['GET',  '/users/new',          ['users',   'create']],
    ['POST', '/users',              ['users',   'store']],
    ['GET',  '/users/{i}/edit',     ['users',   'edit']],
    ['POST', '/users/{i}',          ['users',   'update']],
    ['POST', '/users/{i}/delete',   ['users',   'destroy']],
    ['POST', '/users/{i}/toggle',   ['users',   'toggle']],
    ['POST', '/users/{i}/db/admin',   ['users', 'db_admin']],
    ['POST', '/users/{i}/db/unadmin', ['users', 'db_unadmin']],
    ['POST', '/users/{i}/db/own',     ['users', 'db_own']],
    ['POST', '/users/{i}/db/unown',   ['users', 'db_unown']],

    ['GET',  '/dbs',                ['dbs',     'index']],
    ['POST', '/dbs/db',             ['dbs',     'db_store']],
    ['POST', '/dbs/db/{i}/delete',  ['dbs',     'db_delete']],
    ['POST', '/dbs/dbu',            ['dbs',     'dbu_store']],
    ['POST', '/dbs/dbu/{i}/delete', ['dbs',     'dbu_delete']],
    ['POST', '/dbs/dbu/{i}/pass',   ['dbs',     'dbu_pass']],
    ['POST', '/dbs/rel',            ['dbs',     'rel_store']],
    ['POST', '/dbs/rel/{i}/{i}/delete', ['dbs', 'rel_delete']],

    ['GET',  '/domains',            ['domains', 'index']],
    ['POST', '/domains',            ['domains', 'store']],
    ['POST', '/domains/{i}/delete', ['domains', 'destroy']],
    ['POST', '/domains/{i}/toggle', ['domains', 'toggle']],
    ['POST', '/domains/{i}/ssl',    ['domains', 'ssl']],
    ['POST', '/domains/{i}/dns',    ['domains', 'dns']],

    ['GET',  '/files',              ['files',   'index']],
    ['GET',  '/files/raw',          ['files',   'raw']],
    ['GET',  '/files/edit',         ['files',   'editor']],
    ['POST', '/files/save',         ['files',   'save']],
    ['POST', '/files/mkdir',        ['files',   'mkdir']],
    ['POST', '/files/upload',       ['files',   'upload']],
    ['POST', '/files/rename',       ['files',   'rename']],
    ['POST', '/files/delete',       ['files',   'delete']],

    ['GET',  '/settings',           ['settings', 'index']],
    ['POST', '/settings',           ['settings', 'save']],
    ['POST', '/settings/password',  ['settings', 'password']],

    ['GET',  '/jobs',               ['jobs',    'index']],
    ['POST', '/jobs/poll',          ['jobs',    'poll']],
    ['POST', '/jobs/clear',         ['jobs',    'clear']],
];

function route_match(string $method, string $path): ?array
{
    global $ROUTES;
    $segs = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    foreach ($ROUTES as $r) {
        if ($r[0] !== $method) {
            continue;
        }
        $p = array_values(array_filter(explode('/', trim($r[1], '/')), 'strlen'));
        if (count($p) !== count($segs)) {
            continue;
        }
        $params = [];
        $ok = true;
        foreach ($p as $i => $seg) {
            if ($seg === '{i}') {
                if (!ctype_digit($segs[$i])) {
                    $ok = false;
                    break;
                }
                $params[] = (int) $segs[$i];
            } elseif ($seg !== $segs[$i]) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            return [$r[2][0], $r[2][1], $params];
        }
    }
    return null;
}

/*
 * ------------------------------------------------------------------
 * Dispatch principal
 * ------------------------------------------------------------------
 */
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rtrim($path, '/');
    if ($path === '') {
        $path = '/';
    }

    // En modo php -S (router) sirve aquí mismo los estáticos del panel.
    if (preg_match('#^/(assets/[A-Za-z0-9_./-]+|favicon\.ico|robots\.txt)$#', $path)) {
        $real = realpath(APP_ROOT . $path);
        if ($real !== false && is_file($real)) {
            $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
            $mime = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'svg' => 'image/svg+xml',
                'png' => 'image/png',
                'ico' => 'image/x-icon',
                'txt' => 'text/plain',
            ][$ext] ?? 'application/octet-stream';
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) filesize($real));
            readfile($real);
            exit;
        }
    }

    $installed = false;
    $schema = false;
    if (db_schema_exists()) {
        $schema = true;
        $installed = panel_installed();
    }

    // Independientemente de la ruta, si no hay esquema -> setup explica cómo instalar
    if (!$schema && $path !== '/setup') {
        redirect('setup');
    }
    // Si hay esquema pero todavía no hay admin -> setup (wizard)
    if ($schema && !$installed && !in_array($path, ['/setup', '/login'], true)) {
        redirect('setup');
    }

    $route = route_match($method, $path);
    if ($route === null) {
        http_response_code(404);
        echo '404 No encontrado';
        exit;
    }

    [$file, $action, $params] = $route;
    $filePath = APP_ROOT . '/controllers/' . $file . '.php';
    if (!is_file($filePath)) {
        http_response_code(500);
        echo 'Controlador no encontrado';
        exit;
    }
    require_once $filePath;

    $fn = 'ctrl_' . $file . '_' . $action;
    if (!function_exists($fn)) {
        http_response_code(500);
        echo 'Acción no encontrada';
        exit;
    }
    $fn($params);
} catch (Throwable $e) {
    error_log('miserver error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (is_ajax()) {
        respond(false, 'Error interno: ' . $e->getMessage());
    }
    http_response_code(500);
    echo 'Error interno del panel';
}