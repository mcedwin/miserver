<?php

declare(strict_types=1);

/**
 * Render de vistas con layout compartido.
 */

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $u = current_user();
    $active = $data['active'] ?? '';
    $title = $data['title'] ?? 'Mi Server';
    $content = $data['_content'] ?? null;

    if ($content === null) {
        ob_start();
        require APP_ROOT . '/views/' . $view . '.php';
        $content = ob_get_clean();
    }

    require APP_ROOT . '/views/layout.php';
}

/**
 * Páginas "auth" (login/setup) sin menú lateral.
 */
function render_plain(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $u = current_user();
    $title = $data['title'] ?? 'Mi Server';
    ob_start();
    require APP_ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/views/layout_auth.php';
}

/**
 * Menú lateral según rol.
 */
function menu_items(): array
{
    $u = current_user();
    $items = [
        ['active' => 'home', 'url' => 'home', 'icon' => 'home', 'label' => 'Inicio'],
    ];
    if (($u['role'] ?? '') === 'admin') {
        $items[] = ['active' => 'users', 'url' => 'users', 'icon' => 'users', 'label' => 'Usuarios'];
    }
    $items[] = ['active' => 'dbs', 'url' => 'dbs', 'icon' => 'database', 'label' => 'Bases de datos'];
    $items[] = ['active' => 'domains', 'url' => 'domains', 'icon' => 'globe', 'label' => 'Dominios'];
    $items[] = ['active' => 'files', 'url' => 'files', 'icon' => 'folder', 'label' => 'Archivos'];
    $items[] = ['active' => 'disk', 'url' => 'disk', 'icon' => 'disk', 'label' => 'Discos'];
    $items[] = ['active' => 'jobs', 'url' => 'jobs', 'icon' => 'tasks', 'label' => 'Tareas'];
    $items[] = ['active' => 'settings', 'url' => 'settings', 'icon' => 'cog', 'label' => 'Configuración'];
    return $items;
}