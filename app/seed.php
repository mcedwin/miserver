<?php

declare(strict_types=1);

/**
 * Comprobaciones de esquema e inicialización mínima.
 */

function db_schema_exists(): bool
{
    try {
        $tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $need = ['config', 'user', 'domain', 'db_shema', 'db_user', 'db_relation', 'job', 'login_attempts'];
        foreach ($need as $t) {
            if (!in_array($t, $tables, true)) {
                return false;
            }
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function db_config(): array
{
    $row = db_one('SELECT * FROM config WHERE id = 1');
    return $row ?: [
        'id' => 1, 'domain' => '', 'do_token' => '', 'le_email' => '',
        'panel_name' => 'Mi Server', 'created_at' => '',
    ];
}

/**
 * Migración idempotente para el módulo de aplicaciones (GitHub).
 * Añade las columnas de configuración de aplicación a `domain` en instalaciones
 * existentes (las nuevas ya las traen en res/miserver.sql). No toca contraseñas.
 */
function db_ensure_domain_columns(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (!db_schema_exists()) {
            return;
        }
        $db = db();
        $cols = array_map(
            static fn($r) => (string) $r['Field'],
            $db->query('SHOW COLUMNS FROM `domain`')->fetchAll()
        );
        $adds = [
            'app_id'        => "ALTER TABLE `domain` ADD COLUMN `app_id` int NULL DEFAULT NULL AFTER `user_id`",
            'project_type'  => "ALTER TABLE `domain` ADD COLUMN `project_type` varchar(20) NOT NULL DEFAULT '' AFTER `folder`",
            'git_url'       => "ALTER TABLE `domain` ADD COLUMN `git_url` varchar(500) NOT NULL DEFAULT '' AFTER `project_type`",
            'git_branch'    => "ALTER TABLE `domain` ADD COLUMN `git_branch` varchar(100) NOT NULL DEFAULT 'main' AFTER `git_url`",
            'project_path'  => "ALTER TABLE `domain` ADD COLUMN `project_path` varchar(255) NOT NULL DEFAULT '' AFTER `git_branch`",
            'document_root' => "ALTER TABLE `domain` ADD COLUMN `document_root` varchar(255) NOT NULL DEFAULT '' AFTER `project_path`",
            'php_version'   => "ALTER TABLE `domain` ADD COLUMN `php_version` varchar(10) NOT NULL DEFAULT '' AFTER `document_root`",
            'git_token'     => "ALTER TABLE `domain` ADD COLUMN `git_token` varchar(500) NOT NULL DEFAULT '' AFTER `php_version`",
        ];
        foreach ($adds as $col => $sql) {
            if (!in_array($col, $cols, true)) {
                $db->exec($sql);
            }
        }
        // Amplía `folder` (85 -> 255): una app puede clonarse en carpetas largas.
        $ft = $db->query('SHOW COLUMNS FROM `domain` LIKE \'folder\'')->fetch();
        if ($ft && preg_match('/^varchar\((\d+)\)/i', (string) ($ft['Type'] ?? ''), $m) && (int) $m[1] < 255) {
            $db->exec("ALTER TABLE `domain` MODIFY COLUMN `folder` varchar(255) NOT NULL DEFAULT 'public_html'");
        }
    } catch (Throwable $e) {
        error_log('miserver db_ensure_domain_columns: ' . $e->getMessage());
    }
}

/**
 * Migración idempotente para el módulo de Aplicaciones (tabla `app`).
 */
function db_ensure_app_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (!db_schema_exists()) {
            return;
        }
        $db = db();
        $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('app', $tables, true)) {
            $db->exec("CREATE TABLE `app` (
                `id` int NOT NULL AUTO_INCREMENT,
                `user_id` int NOT NULL,
                `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                `folder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                `project_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                `git_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                `git_branch` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main',
                `document_root` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                `php_version` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                `git_token` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `fk_app_user` (`user_id`),
                CONSTRAINT `fk_app_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE = InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        // FK de domain -> app (idempotente)
        $fks = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'domain' AND COLUMN_NAME = 'app_id'")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($fks)) {
            $db->exec('ALTER TABLE `domain` ADD COLUMN IF NOT EXISTS `app_id` int NULL DEFAULT NULL AFTER `user_id`');
            $db->exec('ALTER TABLE `domain` ADD KEY `fk_domain_app` (`app_id`)');
            $db->exec('ALTER TABLE `domain` ADD CONSTRAINT `fk_domain_app` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        }
    } catch (Throwable $e) {
        error_log('miserver db_ensure_app_tables: ' . $e->getMessage());
    }
}