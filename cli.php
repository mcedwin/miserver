<?php

declare(strict_types=1);

/**
 * Herramientas de consola del panel.
 * Uso: php cli.php <comando> [--opcion=valor]
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/app/bootstrap.php';

function cli_args(): array
{
    $args = [];
    foreach (array_slice($_SERVER['argv'], 2) as $a) {
        if (str_starts_with($a, '--')) {
            [$k, $v] = array_pad(explode('=', substr($a, 2), 2), 2, '');
            $args[$k] = $v;
        }
    }
    return $args;
}

function cli_out(string $s = ''): void
{
    echo $s . "\n";
}

$cmd = $_SERVER['argv'][1] ?? 'help';
$opt = cli_args();

try {
    switch ($cmd) {
        case 'health':
            cli_out('Esquema BD : ' . (db_schema_exists() ? 'ok' : 'NO instalado (importa res/miserver.sql)'));
            cli_out('Wrapper    : ' . (ctl_available() ? ctl_path() : 'no disponible'));
            cli_out('Panel      : ' . (panel_installed() ? 'instalado' : 'sin admin (php cli.php init ...)'));
            break;

        case 'init':
            if (panel_installed()) {
                cli_out('El panel ya está configurado. No se repite el init.');
                break;
            }
            if (!db_schema_exists()) {
                cli_out('El esquema no existe. Importa res/miserver.sql o ejecuta instalarserver.sh.');
                exit(1);
            }
            foreach (['user', 'domain', 'pass'] as $need) {
                if (empty($opt[$need])) {
                    cli_out('Uso: php cli.php init --user=miadmin --domain=panel.midominio.com --pass=CLAVE [--le-email=x@x.com]');
                    exit(1);
                }
            }
            $user = $opt['user'];
            $domain = strtolower($opt['domain']);
            require_match(RE_USERNAME, $user, 'Usuario no válido.');
            require_match(RE_DOMAIN, $domain, 'Dominio no válido.');
            if (strlen($opt['pass']) < 8) {
                cli_out('La contraseña debe tener 8 o más caracteres.');
                exit(1);
            }
            if (db_one('SELECT id FROM user WHERE user = ?', [$user])) {
                cli_out('Ese usuario ya existe.');
                exit(1);
            }

            db_run('INSERT INTO config (id, domain, do_token, le_email, panel_name) VALUES (1, ?, ?, ?, ?)', [
                $domain, '', $opt['le-email'] ?? '', 'Mi Server',
            ]);
            db_run('INSERT INTO user (user, name, description, domain, password, role, active) VALUES (?, ?, ?, ?, ?, ?, 1)', [
                $user, 'Administrador', 'Cuenta principal', $domain,
                password_hash($opt['pass'], PASSWORD_DEFAULT), 'admin',
            ]);
            $uid = (int) db_last_id();
            db_run('INSERT INTO domain (user_id, domain, folder, `ssl`, enabled) VALUES (?, ?, ?, 0, 1)', [$uid, $domain, 'public_html']);
            cli_out('Admin creado en la BD: ' . $user . ' / ' . $domain);

            if (ctl_available()) {
                $r = ctl_run(['user:add', $user, $opt['pass'], $domain, 'admin']);
                cli_out(trim($r['out']));
                exit($r['exit']);
            }
            cli_out('Aviso: el wrapper no está disponible; la cuenta Linux/MySQL hay que crearla manualmente (instalar miserver-ctl).');
            break;

        case 'setup-linux':
            // Reintento de la parte del sistema (cuenta Linux + vhost + usuario MySQL)
            if (empty($opt['pass'])) {
                cli_out('Uso: php cli.php setup-linux --pass=NUEVACLAVE (tambien la cambia en MySQL)');
                break;
            }
            $u = db_one('SELECT u.user, u.domain, u.role FROM user u JOIN domain d ON d.user_id = u.id ORDER BY u.id LIMIT 1');
            if (!$u) {
                cli_out('No hay admin en la BD.');
                break;
            }
            $args = ['user:add', $u['user'], $opt['pass'], $u['domain']];
            if (($u['role'] ?? '') === 'admin') {
                $args[] = 'admin';
            }
            $r = ctl_run($args);
            cli_out('exit=' . $r['exit'] . ' -> ' . trim($r['out']));
            cli_out('Recuerda actualizar la contrasena en el panel (ajustes) si quieres que coincidan.');
            break;

        case 'migrate':
            cli_out('Migración desde el esquema antiguo (CodeIgniter)...');
            migrate_old();
            cli_out('Listo. Revisa los mensajes anteriores.');
            break;

        case 'help':
        default:
            cli_out('Comandos del panel:');
            cli_out('  health                    estado del panel');
            cli_out('  init --user= X --domain= Y --pass= Z [--le-email=E]');
            cli_out('  setup-linux --pass= X     recrea cuenta Linux/MySQL del admin');
            cli_out('  migrate                   adapta el esquema antiguo al nuevo');
            break;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

/** Migración best-effort desde el esquema del panel antiguo (ci/). */
function migrate_old(): void
{
    if (!db_schema_exists()) {
        cli_out('Importa primero el esquema nuevo (mysql ... < res/miserver.sql).');
        return;
    }
    $db = db();
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('user', $tables, true)) {
        $cols = array_map(static fn($r) => $r['Field'], $db->query('SHOW COLUMNS FROM `user`')->fetchAll());
        if (!in_array('role', $cols, true)) {
            $db->exec("ALTER TABLE `user` ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER `password`");
        }
        if (!in_array('name', $cols, true)) {
            $db->exec("ALTER TABLE `user` ADD COLUMN name VARCHAR(60) NOT NULL DEFAULT '' AFTER `user`");
        }
        if (!in_array('last_login', $cols, true)) {
            $db->exec('ALTER TABLE `user` ADD COLUMN last_login DATETIME NULL');
        }
        $db->exec("UPDATE `user` SET role='admin' WHERE id=1");
        $upd = $db->prepare('UPDATE `user` SET password = ? WHERE id = ?');
        foreach ($db->query('SELECT id, password FROM `user`')->fetchAll() as $r) {
            if ($r['password'] !== '' && !str_starts_with($r['password'], '$2y$')) {
                $upd->execute([password_hash($r['password'], PASSWORD_DEFAULT), (int) $r['id']]);
            }
        }
        cli_out('  user: role/name/last_login añadidos, contraseñas hasheadas');
    }

    if (in_array('domain', $tables, true)) {
        $cols = array_map(static fn($r) => $r['Field'], $db->query('SHOW COLUMNS FROM `domain`')->fetchAll());
        if (in_array('idUser', $cols, true) && !in_array('user_id', $cols, true)) {
            $db->exec('ALTER TABLE `domain` CHANGE COLUMN `idUser` `user_id` INT UNSIGNED NOT NULL');
        }
        foreach (['ssl' => 0, 'enabled' => 1] as $col => $def) {
            if (!in_array($col, $cols, true)) {
                $db->exec("ALTER TABLE `domain` ADD COLUMN $col TINYINT(1) NOT NULL DEFAULT $def");
            }
        }
        cli_out('  domain: columnas normalizadas');
    }

    if (in_array('db_user', $tables, true)) {
        $cols = array_map(static fn($r) => $r['Field'], $db->query('SHOW COLUMNS FROM `db_user`')->fetchAll());
        if (in_array('idUser', $cols, true) && !in_array('user_id', $cols, true)) {
            $db->exec('ALTER TABLE `db_user` CHANGE COLUMN `idUser` `user_id` INT UNSIGNED NOT NULL');
        }
        $upd = $db->prepare('UPDATE `db_user` SET password = ? WHERE id = ?');
        foreach ($db->query('SELECT id, password FROM `db_user`')->fetchAll() as $r) {
            if ($r['password'] !== '' && !str_starts_with($r['password'], 'MS:')) {
                $upd->execute([enc($r['password']), (int) $r['id']]);
            }
        }
        cli_out('  db_user: contraseñas cifradas con APP_SECRET');
    }

    if (in_array('config', $tables, true)) {
        try {
            $db->query('SELECT do_token FROM `config` LIMIT 1');
        } catch (Throwable $e) {
            $db->exec("ALTER TABLE `config` ADD COLUMN do_token VARCHAR(191) NOT NULL DEFAULT '' AFTER `domain`");
            $db->exec("ALTER TABLE `config` ADD COLUMN le_email VARCHAR(100) NOT NULL DEFAULT '' AFTER do_token");
            $db->exec("ALTER TABLE `config` ADD COLUMN panel_name VARCHAR(60) NOT NULL DEFAULT 'Mi Server' AFTER le_email");
            cli_out('  config: columnas nuevas añadidas');
        }
    }
}