<?php

declare(strict_types=1);

function ctrl_disk_index(): void
{
    $u = require_login();
    $isAdmin = ($u['role'] ?? '') === 'admin';
    $users = [];
    if ($isAdmin) {
        $r = ctl_run(['du:users']);
        foreach (explode("\n", $r['out']) as $line) {
            $p = explode("\t", trim($line));
            if (count($p) >= 3 && $p[0] === 'H') {
                $users[] = ['user' => $p[1], 'bytes' => (int) $p[2]];
            }
        }
        usort($users, static fn($a, $b) => $b['bytes'] <=> $a['bytes']);
    }
    $info = sys_info();
    $partitions = parse_disk_partitions($info['partitions']['raw'] ?? '');
    $diskTotal = null;
    foreach ($partitions as $part) {
        if (($part['mount'] ?? '') === '/') {
            $diskTotal = $part;
            break;
        }
    }
    if (!$diskTotal && !empty($partitions)) {
        $diskTotal = $partitions[0];
    }
    render('disk/index', [
        'title' => 'Backups',
        'active' => 'disk',
        'isAdmin' => $isAdmin,
        'users' => $users,
        'backups' => disk_parse_backups($info['backups']['raw'] ?? ''),
        'disk_total' => $diskTotal,
        'memory' => parse_memory($info['disk']['raw'] ?? ''),
        'load' => parse_load($info['disk']['raw'] ?? ''),
        'db_by_user' => disk_db_sizes_by_user($u, $info['disk']['raw'] ?? ''),
    ]);
}

/** Agrupa las bases de datos devueltas por sys:info bajo cada cuenta de usuario. */
function disk_db_sizes_by_user(array $u, string $raw): array
{
    $isAdmin = ($u['role'] ?? '') === 'admin';
    $panelUsers = $isAdmin ? users_for_select() : [db_one('SELECT id, user FROM user WHERE id = ?', [(int) $u['id']]) ?: ['id' => 0, 'user' => $u['user'] ?? '']];
    usort($panelUsers, static fn($a, $b) => strlen((string) $b['user']) <=> strlen((string) $a['user']));
    $dbSizes = parse_db_sizes($raw);
    $byUser = [];
    foreach ($panelUsers as $usr) {
        $byUser[(string) $usr['user']] = [];
    }
    foreach ($dbSizes as $db) {
        foreach ($panelUsers as $usr) {
            $prefix = $usr['user'] . '_';
            if (strncmp($db['name'], $prefix, strlen($prefix)) === 0) {
                $byUser[(string) $usr['user']][] = [
                    'name' => substr($db['name'], strlen($prefix)),
                    'full' => $db['name'],
                    'size_kb' => $db['size_kb'],
                    'tables' => $db['tables'],
                ];
                break;
            }
        }
    }
    // Dejar solo usuarios con bases y ordenar por tamaño total descendente.
    $result = [];
    foreach ($byUser as $user => $dbs) {
        if ($dbs === []) {
            continue;
        }
        usort($dbs, static fn($a, $b) => $b['size_kb'] <=> $a['size_kb']);
        $total = array_sum(array_column($dbs, 'size_kb'));
        $result[] = ['user' => $user, 'total_kb' => $total, 'dbs' => $dbs];
    }
    usort($result, static fn($a, $b) => $b['total_kb'] <=> $a['total_kb']);
    return $result;
}

/** Admin puede operar sobre cualquier usuario; el resto solo sobre el suyo. */
function disk_resolve_user(array $u, string $postUser): string
{
    if (($u['role'] ?? '') === 'admin') {
        if (!preg_match(RE_USERNAME, $postUser)) {
            json_out(['ok' => false, 'msg' => 'Usuario no válido.']);
        }
        return $postUser;
    }
    return (string) ($u['user'] ?? '');
}

/** Mismas reglas que vrel() del wrapper: sin '..', sin absoluta, sin control; subcarpetas permitidas. */
function disk_valid_rel(string $rel): string
{
    $rel = trim(str_replace('\\', '/', $rel), '/');
    if ($rel !== '' && !relpath_ok($rel)) {
        json_out(['ok' => false, 'msg' => 'Ruta no válida.']);
    }
    return $rel;
}

/**
 * Explora una carpeta del home de un usuario y devuelve el detalle
 * de cada entrada (tamaño, tipo, mtime) como JSON.
 */
function ctrl_disk_browse(): void
{
    $u = require_login();
    $user = disk_resolve_user($u, trim((string) post('user', '')));
    $rel = disk_valid_rel((string) post('rel', ''));

    $r = ctl_run(['du:stat', $user, $rel]);
    if ($r['exit'] !== 0) {
        json_out(['ok' => false, 'msg' => 'No se pudo escanear: ' . trim($r['out'])]);
    }

    $total = null;
    $entries = [];
    foreach (explode("\n", $r['out']) as $line) {
        $p = explode("\t", trim($line));
        if (!isset($p[1])) {
            continue;
        }
        $name = $p[1];
        $bytes = (int) ($p[2] ?? 0);
        switch ($p[0]) {
            case 'T':
                $total = $bytes;
                break;
            case 'D':
                $entries[] = ['t' => 'D', 'n' => $name, 'b' => $bytes];
                break;
            case 'L':
                $entries[] = ['t' => 'L', 'n' => $name, 'b' => $bytes];
                break;
            default:
                $entries[] = ['t' => 'F', 'n' => $name, 'b' => $bytes, 'm' => (int) ($p[3] ?? 0)];
                break;
        }
    }

    // Carpetas primero, luego por tamaño descendente.
    usort($entries, static function ($a, $b) {
        $da = $a['t'] === 'D' ? 1 : 0;
        $db = $b['t'] === 'D' ? 1 : 0;
        if ($da !== $db) {
            return $db <=> $da;
        }
        return $b['b'] <=> $a['b'];
    });

    json_out([
        'ok' => true,
        'rel' => $rel,
        'up' => $rel === '' ? '' : preg_replace('#/[^/]*$#', '', $rel),
        'total' => $total,
        'entries' => $entries,
    ]);
}

/** Crea un job que respalda una carpeta concreta del home. */
function ctrl_disk_backup(): void
{
    $u = require_login();
    csrf_check();
    $user = disk_resolve_user($u, trim((string) post('user', '')));
    $rel = disk_valid_rel((string) post('rel', ''));

    $target = '/home/' . $user . ($rel !== '' ? '/' . $rel : '');
    $jid = job_create('backup', 'carpeta ' . $target, (int) $u['id']);
    job_spawn($jid, ['backup:folder', $user, $rel]);
    respond(true, 'Backup de ' . $target . ' en curso. Se verá en la lista de backups cuando termine.', url('disk'));
}

/** Backup completo: homes + bases de datos. */
function ctrl_disk_backup_all(): void
{
    $u = require_login();
    csrf_check();
    $jid = job_create('backup', 'todo el servidor', (int) $u['id']);
    job_spawn($jid, ['backup:run']);
    respond(true, 'Backup completo en curso. Cuando termine podrás descargarlo desde Backups.', url('disk'));
}

/** Backup de todas las bases de datos de usuario. */
function ctrl_disk_backup_db(): void
{
    $u = require_login();
    csrf_check();
    $jid = job_create('backup', 'bases de datos', (int) $u['id']);
    job_spawn($jid, ['backup:db']);
    respond(true, 'Backup de bases de datos en curso. Cuando termine podrás descargarlo desde Backups.', url('disk'));
}

/** Descarga un backup existente. */
function ctrl_disk_download(): void
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

/** Elimina un backup existente. */
function ctrl_disk_backup_delete(): void
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

/** Parsea la salida pipe-delimited del wrapper backup:list a arrays estructurados. */
function disk_parse_backups(string $raw): array
{
    $rows = [];
    foreach (preg_split('/\r?\n/', $raw) as $line) {
        $line = trim($line);
        if ($line === '' || strncmp($line, 'file|', 5) !== 0) {
            continue;
        }
        $p = explode('|', $line);
        if (count($p) < 4) {
            continue;
        }
        $rows[] = [
            'size' => (int) $p[1],
            'date' => $p[2],
            'name' => $p[3],
        ];
    }
    return $rows;
}