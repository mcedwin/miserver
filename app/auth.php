<?php

declare(strict_types=1);

/**
 * Autenticación, sesión y protección CSRF.
 */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $tok = post('_csrf');
    if ($tok === '' || !hash_equals(csrf_token(), $tok)) {
        if (is_ajax()) {
            json_out(['ok' => false, 'msg' => 'CSRF inválido. Recarga la página.']);
        }
        http_response_code(403);
        echo 'CSRF inválido.';
        exit;
    }
}

function is_ajax(): bool
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';
}

/* ------------------------------------------------------------------ */
/* Usuario actual                                                     */
/* ------------------------------------------------------------------ */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $u = current_user();
    if ($u === null || empty($u['id'])) {
        if (is_ajax()) {
            respond(false, 'Sesión expirada.', url('login'));
        }
        redirect('login');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if (($u['role'] ?? '') !== 'admin') {
        if (is_ajax()) {
            respond(false, 'Permiso denegado.');
        }
        http_response_code(403);
        echo 'Acceso denegado.';
        exit;
    }
    return $u;
}

function login_as(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
}

function logout(): void
{
    session_regenerate_id(true);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ------------------------------------------------------------------ */
/* Rate-limit (intentos fallidos por IP)                               */
/* ------------------------------------------------------------------ */
function failed_attempts(string $ip): int
{
    return db_count('login_attempts', 'ip = ?', [$ip]);
}

function register_failed_attempt(string $ip): void
{
    // Borrar antiguos > 10 min
    db_run('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND ip = ?', [$ip]);
    db_run('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())', [$ip]);
}

function clear_failed_attempts(string $ip): void
{
    db_run('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
}

function is_locked_out(string $ip): bool
{
    return failed_attempts($ip) >= 6;
}