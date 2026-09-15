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