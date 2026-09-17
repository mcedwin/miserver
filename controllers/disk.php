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
    render('disk/index', [
        'title' => 'Discos',
        'active' => 'disk',
        'isAdmin' => $isAdmin,
        'users' => $users,
        'backups' => parse_pipe_lines($info['backups']['raw'] ?? ''),
    ]);
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

/** Mismas reglas que vrel() del wrapper: sin '..', sin '/', sin control. */
function disk_valid_rel(string $rel): string
{
    if (strlen($rel) > 255 || strpos($rel, '..') !== false || preg_match('{[\\\\/\x00-\x1f]}', $rel)) {
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