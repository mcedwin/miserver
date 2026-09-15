<?php

declare(strict_types=1);

/**
 * Bootstrap del panel.
 * Carga configuración (.env), sesión, autoload y helpers.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

const APP_ROOT = __DIR__ . '/..';
const APP_DIR = __DIR__;

/* ------------------------------------------------------------------ */
/* Lectura de variables de entorno (.env)                              */
/* ------------------------------------------------------------------ */
function env(string $key, string $default = ''): string
{
    static $env = null;
    if ($env === null) {
        $env = [];
        $file = APP_ROOT . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $raw) {
                $line = trim($raw);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $env[trim($k)] = trim(str_replace(['"', "'"], '', $v));
            }
        }
    }
    return $env[$key] ?? $default; // @phpstan-ignore-line
}

/* ------------------------------------------------------------------ */
/* Sesión                                                              */
/* ------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    $sp = env('SESSION_PATH');
    if ($sp !== '') {
        @session_save_path($sp);
    }
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('misid');
    session_start();
}

/* ------------------------------------------------------------------ */
/* Autoload de clases App\                                            */
/* ------------------------------------------------------------------ */
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, 4));
    $file = APP_DIR . '/' . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/* Helpers globales */
require_once APP_DIR . '/helpers.php';
require_once APP_DIR . '/auth.php';
require_once APP_DIR . '/view.php';
require_once APP_DIR . '/db.php';
require_once APP_DIR . '/ctl.php';
require_once APP_DIR . '/do.php';

/* Zona horaria */
$tz = env('APP_TIMEZONE', 'UTC');
if ($tz !== '') {
    date_default_timezone_set($tz);
}

require_once APP_DIR . '/seed.php';