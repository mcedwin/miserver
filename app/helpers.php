<?php

declare(strict_types=1);

/* =====================================================================
 * Helpers básicos del panel
 * ===================================================================== */

/** Escapa texto para HTML (usa en todas las vistas). */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim(env('APP_BASEURL', '/'), '/');
    if ($path === '') {
        return $base === '' ? '/' : $base;
    }
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function post_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? null;
    return ctype_digit((string) $v) ? (int) $v : $default;
}

function query(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

function query_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? null;
    return ctype_digit((string) $v) ? (int) $v : $default;
}

/** Mensaje flash (se muestra una sola vez). */
function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_pull(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Respuesta JSON para las peticiones AJAX del panel. */
function json_out(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Responde ok/fail: JSON si es AJAX, si no redirige con mensaje flash. */
function respond(bool $ok, string $msg, string $redirect = ''): never
{
    if (is_ajax()) {
        json_out(['ok' => $ok, 'msg' => $msg, 'redirect' => $redirect]);
    }
    if ($redirect !== '') {
        flash($ok ? 'ok' : 'err', $msg);
        header('Location: ' . url($redirect));
        exit;
    }
    http_response_code($ok ? 200 : 400);
    echo e($msg);
    exit;
}

/** Valida y devuelve un campo regex; lanza error si no cumple. */
function require_match(string $pattern, string $value, string $msg): string
{
    if (!preg_match($pattern, $value)) {
        respond(false, $msg);
    }
    return $value;
}

const RE_USERNAME = '/^[a-z][a-z0-9_]{2,31}$/';
const RE_DBNAME   = '/^[a-z0-9_]{1,64}$/';
const RE_DBUSER   = '/^[a-z0-9_]{1,64}$/';
const RE_DOMAIN   = '/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/';
const RE_FOLDER   = '/^(?!\.)(?!.*\.\.)[^\/\x00-\x1f]{1,255}$/'; // sin /, sin .., sin punto inicial, sin control; permite UTF-8

// Aplicaciones desde GitHub: solo https, sin credenciales embebidas (user@ o :pass@).
const RE_GITURL    = '/^https:\/\/[a-z0-9]([a-z0-9.-]*[a-z0-9])?(\.[a-z]{2,})+(\/[a-z0-9._~!$&()*+,;=:@%\-]+)+$/i';
const RE_GITBRANCH = '/^[a-z0-9][a-z0-9._\/-]{0,99}$/i';
const RE_PHPVER    = '/^[0-9]+\.[0-9]+$/';
// Token para repos privados (GitHub PAT / HTTP): juego seguro y acotado.
const RE_GITTOKEN  = '/^[A-Za-z0-9._:-]{8,150}$/';

/**
 * Valida una ruta relativa (proyecto dentro del repo o DocumentRoot respecto al home):
 * relativa, sin `..`, sin bytes de control, sin barra final, máx. 255. Permite subcarpetas.
 */
function relpath_ok(string $raw): bool
{
    $s = trim(str_replace('\\', '/', $raw));
    $s = trim($s, '/');
    $n = strlen($s);
    if ($n === 0 || $n > 255) {
        return false;
    }
    if (preg_match('/[[:cntrl:]]/', $s)) {
        return false;
    }
    foreach (explode('/', $s) as $seg) {
        if ($seg === '..' || $seg === '.') {
            return false;
        }
    }
    return true;
}

// Contraseñas: solo se exige longitud 8-72. Se rechazan ' y \ porque
// romperían la sentencia MySQL que construye el wrapper.
function valid_panel_password(string $pass, string $label = 'Contraseña'): string
{
    $n = strlen($pass);
    if ($n < 8 || $n > 72) {
        respond(false, $label . ' debe tener entre 8 y 72 caracteres.');
    }
    if (strpos($pass, "'") !== false || strpos($pass, '\\') !== false) {
        respond(false, $label . ' no puede contener comillas ni backslash.');
    }
    return $pass;
}

/* ------------------------------------------------------------------ */
/* Cifrado simple (para contraseñas de usuarios de BD)                 */
/* ------------------------------------------------------------------ */
function enc(string $plain): string
{
    $key = env('APP_SECRET');
    if ($key === '' || !function_exists('openssl_encrypt')) {
        return $plain; // sin clave definida no se puede cifrar
    }
    $iv = random_bytes(16);
    $raw = openssl_encrypt($plain, 'aes-256-gcm', hash('sha256', $key), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $raw);
}

function dec(string $cipher): string
{
    $key = env('APP_SECRET');
    if ($key === '' || !function_exists('openssl_decrypt')) {
        return '********';
    }
    $b = base64_decode($cipher, true);
    if ($b === false || strlen($b) < 32) {
        return '';
    }
    $iv = substr($b, 0, 16);
    $tag = substr($b, 16, 16);
    $raw = substr($b, 32);
    return openssl_decrypt($raw, 'aes-256-gcm', hash('sha256', $key), OPENSSL_RAW_DATA, $iv, $tag) ?: '';
}

/* ------------------------------------------------------------------ */
/* Utilidades pequeñas                                                 */
/* ------------------------------------------------------------------ */
function bytes_human(int $bytes, int $decimals = 1): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        ++$i;
    }
    return round($bytes, $decimals) . ' ' . $units[$i];
}

function dt(string $t = 'now'): string
{
    return date('Y-m-d H:i:s', strtotime($t));
}

function safe_json(?string $s): string
{
    return e($s ?? '');
}

function panel_installed(): bool
{
    $config = db()->query('SELECT COUNT(*) c FROM config')->fetchColumn();
    $users = db()->query('SELECT COUNT(*) c FROM user')->fetchColumn();
    return (int) $config > 0 && (int) $users > 0;
}

/* ------------------------------------------------------------------ */
/* Usuario en contexto (para administradores que ven los de otros)     */
/* ------------------------------------------------------------------ */
function ctx_user_id(array $u): int
{
    if (($u['role'] ?? '') === 'admin') {
        $id = query_int('u', (int) $u['id']);
        $row = db_one('SELECT id FROM user WHERE id = ?', [$id]);
        return $row ? (int) $row['id'] : (int) $u['id'];
    }
    return (int) $u['id'];
}

/** Usuario "en contexto" (para administradores que operan sobre otro). */
function ctx_user(array $u): array
{
    $row = db_one('SELECT id, user, domain, role FROM user WHERE id = ?', [ctx_user_id($u)]);
    if (!$row) {
        redirect('home');
    }
    return $row;
}

/** Como ctx_user pero leyendo el usuario de un POST (forms del gestor de archivos). */
function ctx_user_from_post(array $u): array
{
    if (($u['role'] ?? '') === 'admin') {
        $id = (int) post('u', '0');
        if ($id > 0) {
            $row = db_one('SELECT id, user, domain, role FROM user WHERE id = ?', [$id]);
            if ($row) {
                return $row;
            }
        }
    }
    return ctx_user($u);
}

function user_row(int $id): ?array
{
    return db_one('SELECT * FROM user WHERE id = ?', [$id]) ?: null;
}

function users_for_select(): array
{
    return db_all('SELECT id, user, domain, role FROM user ORDER BY user');
}

/** Prefijo real (usuario linux) de una base de datos o usuario de BD. */
function db_prefix(string $linuxUser, string $name): string
{
    return $linuxUser . '_' . $name;
}

/** Convierte la salida "campo|v1|v2..." del wrapper en arrays. */
function parse_pipe_lines(string $raw): array
{
    $rows = [];
    foreach (preg_split('/\r?\n/', $raw) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = explode('|', $line);
        $rows[] = $parts;
    }
    return $rows;
}

/** Genera una contraseña aleatoria segura para usuarios de BD. */
function random_password(int $len = 16): string
{
    $bytes = bin2hex(random_bytes((int) ceil($len / 2)));
    return substr(preg_replace('/[^A-Za-z0-9_]/', '', $bytes), 0, $len);
}