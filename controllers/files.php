<?php

declare(strict_types=1);

/* =====================================================================
 * Gestor de archivos. El acceso queda limitado al home del usuario
 * elegido. Las escrituras van por el wrapper privilegiado (fs:...).
 * ===================================================================== */

function file_base(array $ctx): string
{
    return '/home/' . $ctx['user'];
}

/**
 * Normaliza y valida una ruta relativa dentro del home (sin `..`, sin absoluto).
 * Devuelve la ruta relativa limpia o null si es inválida.
 */
function file_rel(string $raw, array $ctx): ?string
{
    $raw = str_replace('\\', '/', trim($raw));
    $raw = trim($raw, '/');
    $segs = [];
    foreach (explode('/', $raw) as $seg) {
        if ($seg === '' || $seg === '.') {
            continue;
        }
        if ($seg === '..' || strpos($seg, "\0") !== false) {
            return null;
        }
        $segs[] = $seg;
    }
    return implode('/', $segs);
}

function file_entries(string $user, string $rel, bool $showHidden): array
{
    $items = ['dirs' => [], 'files' => [], 'error' => ''];
    $r = ctl_run(['fs:list', $user, $rel, $showHidden ? 'hidden' : '']);
    if ($r['exit'] !== 0) {
        $items['error'] = trim(preg_replace('/^error:\s*/', '', $r['out']));
        return $items;
    }
    foreach (preg_split('/\r?\n/', $r['out']) as $line) {
        if ($line === '') {
            continue;
        }
        [$t, $name, $size, $mtime] = array_pad(explode("\t", $line, 4), 4, '');
        if ($t === 'D') {
            $items['dirs'][] = ['name' => $name, 'mtime' => (int) $mtime];
        } elseif ($t === 'F') {
            $items['files'][] = [
                'name' => $name,
                'size' => (int) $size,
                'mtime' => (int) $mtime,
                'editable' => (int) $size < 2 * 1024 * 1024,
            ];
        }
    }
    usort($items['dirs'], static fn($a, $b) => strcasecmp($a['name'], $b['name']));
    usort($items['files'], static fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $items;
}

function ctrl_files_index(): void
{
    $u = require_login();
    $ctx = ctx_user($u);
    $base = file_base($ctx);
    $rel = file_rel(query('p', ''), $ctx);
    if ($rel === null) { redirect('files'); }
    $dir = $base . ($rel === '' ? '' : '/' . $rel);
    $entries = file_entries($ctx['user'], $rel, query('h') === '1');
    if ($entries['error'] !== '') {
        flash('err', 'No se pudo listar: ' . $entries['error']);
    }
    $crumbs = [];
    $r = '';
    foreach (explode('/', $rel) as $seg) {
        $r = $r === '' ? $seg : $r . '/' . $seg;
        $crumbs[] = ['label' => $seg, 'path' => $r];
    }
    $data = [
        'title' => 'Archivos',
        'active' => 'files',
        'ctx' => $ctx,
        'rel' => $rel,
        'dir' => $dir,
        'crumbs' => $crumbs,
        'entries' => $entries,
        'showHidden' => query('h') === '1',
        'users' => ($u['role'] ?? '') === 'admin' ? users_for_select() : [],
    ];
    render('files/index', $data);
}

function ctrl_files_editor(): void
{
    $u = require_login();
    $ctx = ctx_user($u);
    $rel = file_rel(query('p', ''), $ctx);
    if ($rel === null || $rel === '') { redirect('files'); }
    $r = ctl_run_raw(['fs:cat', $ctx['user'], $rel, '2097152']);
    if ($r['exit'] !== 0 || $r['err'] !== '') {
        flash('err', 'No se puede editar ese archivo.');
        redirect('files?p=' . urlencode(dirname($rel) === '.' ? '' : dirname($rel)));
    }
    $content = $r['out'];
    if (strpos($content, "\0") !== false) {
        flash('err', 'El archivo es binario y no se puede editar como texto.');
        redirect('files?p=' . urlencode(dirname($rel) === '.' ? '' : dirname($rel)));
    }
    $data = [
        'title' => 'Editar archivo',
        'active' => 'files',
        'ctx' => $ctx,
        'rel' => $rel,
        'content' => $content,
    ];
    render('files/editor', $data);
}

function ctrl_files_save(): void
{
    $u = require_login();
    csrf_check();
    $ctx = ctx_user_from_post($u);
    $base = file_base($ctx);
    $rel = file_rel(post('p', ''), $ctx);
    $content = (string) ($_POST['content'] ?? '');
    if ($rel === null || $rel === '') { respond(false, 'Ruta no válida.'); }
    if (strlen($content) > 2 * 1024 * 1024) { respond(false, 'Contenido demasiado grande.'); }
    $r = ctl_run(['fs:write', $ctx['user'], $rel], $content);
    if ($r['exit'] !== 0) { respond(false, 'Error al guardar: ' . e($r['out'])); }
    respond(true, 'Archivo guardado.', url('files/edit?u=' . (int) $ctx['id'] . '&p=' . urlencode($rel)));
}

function ctrl_files_mkdir(): void
{
    $u = require_login();
    csrf_check();
    $ctx = ctx_user_from_post($u);
    $rel = file_rel(post('p', '') . '/' . trim(post('name', '')), $ctx);
    if ($rel === null || $rel === '') { respond(false, 'Directorio no válido.'); }
    $r = ctl_run(['fs:mkdir', $ctx['user'], $rel]);
    if ($r['exit'] !== 0) { respond(false, 'Error: ' . e($r['out'])); }
    respond(true, 'Directorio creado.', url('files?u=' . (int) $ctx['id'] . '&p=' . urlencode(dirname($rel))));
}

function ctrl_files_upload(): void
{
    $u = require_login();
    csrf_check();
    $ctx = ctx_user_from_post($u);
    $rel = file_rel(post('p', ''), $ctx);
    if ($rel === null) { respond(false, 'Directorio no válido.'); }
    if (empty($_FILES['up']) || ($_FILES['up']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        respond(false, 'No se recibió el archivo.');
    }
    $name = basename($_FILES['up']['name']);
    if (!preg_match('/^[^\/\\\\]+$/', $name) || $name === '') {
        respond(false, 'Nombre de archivo no válido.');
    }
    if (filesize($_FILES['up']['tmp_name']) > 64 * 1024 * 1024) {
        respond(false, 'Máximo 64 MB por archivo.');
    }
    $destRel = $rel === '' ? $name : $rel . '/' . $name;
    $r = ctl_run(['fs:put', $ctx['user'], $destRel, $_FILES['up']['tmp_name']]);
    if ($r['exit'] !== 0) { respond(false, 'Error al subir: ' . e($r['out'])); }
    respond(true, 'Archivo subido.', url('files?u=' . (int) $ctx['id'] . '&p=' . urlencode($rel)));
}

function ctrl_files_delete(): void
{
    $u = require_login();
    csrf_check();
    $ctx = ctx_user_from_post($u);
    $rel = file_rel(post('p', ''), $ctx);
    if ($rel === null || $rel === '') { respond(false, 'Ruta no válida.'); }
    $r = ctl_run(['fs:rm', $ctx['user'], $rel]);
    if ($r['exit'] !== 0) { respond(false, 'Error al eliminar: ' . e($r['out'])); }
    $parent = dirname($rel);
    respond(true, 'Eliminado.', url('files?u=' . (int) $ctx['id'] . '&p=' . urlencode($parent === '.' ? '' : $parent)));
}

function ctrl_files_rename(): void
{
    $u = require_login();
    csrf_check();
    $ctx = ctx_user_from_post($u);
    $base = file_base($ctx);
    $rel = file_rel(post('p', ''), $ctx);
    $name = trim(post('name', ''));
    if ($rel === null || $rel === '') { respond(false, 'Ruta no válida.'); }
    if ($name === '' || strpbrk($name, "/\\\0") !== false) { respond(false, 'Nombre no válido.'); }
    $parent = ($d = dirname($rel)) === '.' ? '' : $d;
    $destRel = file_rel($parent === '' ? $name : $parent . '/' . $name, $ctx);
    if ($destRel === null) { respond(false, 'Destino no válido.'); }
    if ($destRel === $rel) { respond(false, 'El nombre es el mismo.'); }
    if (@is_link($base . '/' . $rel)) { respond(false, 'No se puede renombrar enlaces.'); }
    $r = ctl_run(['fs:mv', $ctx['user'], $rel, $destRel]);
    if ($r['exit'] !== 0) { respond(false, 'Error al renombrar: ' . e($r['out'])); }
    respond(true, 'Renombrado.', url('files?u=' . (int) $ctx['id'] . '&p=' . urlencode($parent)));
}

function ctrl_files_raw(): void
{
    $u = require_login();
    $ctx = ctx_user($u);
    $rel = file_rel(query('p', ''), $ctx);
    if ($rel === null || $rel === '') { respond(false, 'Ruta no válida.'); }
    $r = ctl_run_raw(['fs:cat', $ctx['user'], $rel, '20971520']);
    if ($r['exit'] !== 0 || $r['err'] !== '') { respond(false, 'Archivo no disponible.'); }
    header('Content-Type: application/octet-stream');
    $fname = str_replace(['"', '\\'], ['_', '_'], basename($rel));
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Content-Length: ' . (string) strlen($r['out']));
    echo $r['out'];
    exit;
}