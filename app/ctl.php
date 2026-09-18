<?php

declare(strict_types=1);

/**
 * Capa de ejecución de comandos privilegiados.
 *
 * El panel SOLO ejecuta comandos a través del wrapper `/usr/local/sbin/miserver-ctl`
 * (instalado por el script de instalación). PHP jamas ejecuta shell ni interpola
 * entradas de usuario en comandos; los argumentos se pasan como argv (sin shell).
 * El wrapper vuelve a validar cada argumento con listas blancas estrictas.
 */

function ctl_path(): string
{
    return env('CTL_PATH', '/usr/local/sbin/miserver-ctl');
}

function ctl_available(): bool
{
    $p = ctl_path();
    return $p !== '' && @is_file($p);
}

function ctl(): string
{
    $dir = env('LOG_DIR', '/var/log/miserver');
    return rtrim($dir, '/') . '/jobs';
}

function ctl_log_dir(): string
{
    @mkdir(ctl(), 0770, true); // se crea en el servidor por el wrapper
    return ctl();
}

/**
 * Ejecuta el wrapper de forma síncrona.
 * @param string[] $args
 * @param string $stdin contenido a enviar por la entrada estándar (opcional)
 * @return array{exit:int,out:string}
 */
function ctl_run(array $args, string $stdin = ''): array
{
    if (!ctl_available()) {
        return ['exit' => 127, 'out' => 'wrapper miserver-ctl no disponible'];
    }
    $cmd = array_merge(['sudo', '-n', ctl_path()], $args);
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        return ['exit' => 127, 'out' => 'no se pudo ejecutar el wrapper'];
    }
    if ($stdin !== '') {
        fwrite($pipes[0], $stdin);
    }
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    return ['exit' => $exit, 'out' => trim($out . ($err ? "\n" . $err : ''))];
}

/**
 * Ejecuta el wrapper de forma síncrona y devuelve la salida estándar SIN
 * recortar (para fs:cat: contenido exacto de archivos, incluidos saltos de
 * línea y bytes finales).
 * @param string[] $args
 * @return array{exit:int,out:string,err:string}
 */
function ctl_run_raw(array $args): array
{
    if (!ctl_available()) {
        return ['exit' => 127, 'out' => '', 'err' => 'wrapper miserver-ctl no disponible'];
    }
    $cmd = array_merge(['sudo', '-n', ctl_path()], $args);
    $proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        return ['exit' => 127, 'out' => '', 'err' => 'no se pudo ejecutar el wrapper'];
    }
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    return ['exit' => $exit, 'out' => $out, 'err' => $err];
}

/**
 * Ejecuta el wrapper vía proc_open() manteniendo stdin abierto mientras se lee
 * la salida. En algunos entornos sudo falla si se cierra stdin antes de que el
 * hijo termine.
 * @param string[] $args
 * @return array{exit:int,out:string,err:string}
 */
function ctl_run_read(array $args): array
{
    if (!ctl_available()) {
        return ['exit' => 127, 'out' => '', 'err' => 'wrapper miserver-ctl no disponible'];
    }
    $cmd = array_merge(['sudo', '-n', ctl_path()], $args);
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        return ['exit' => 127, 'out' => '', 'err' => 'no se pudo ejecutar el wrapper'];
    }
    // Algunos entornos requieren que stdin tenga datos para que sudo ejecute el comando.
    fwrite($pipes[0], "\n");
    fflush($pipes[0]);
    // Mantenemos stdin abierto durante la lectura; cierra después.
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    return ['exit' => $exit, 'out' => $out, 'err' => $err];
}

function ctl_ok_else(array $r, string $msg): void
{
    if ($r['exit'] !== 0) {
        respond(false, $msg . ' (' . e($r['out']) . ')');
    }
}

/* ------------------------------------------------------------------ */
/* Tareas asíncronas (certbot, etc.)                                  */
/* ------------------------------------------------------------------ */
function job_create(string $kind, string $target, ?int $userId = null): int
{
    db_run('INSERT INTO job (kind, target, user_id, status) VALUES (?, ?, ?, ?)', [
        $kind, $target, $userId, 'running',
    ]);
    return (int) db_last_id();
}

function job_spawn(int $id, array $args): void
{
    $log = ctl_log_dir() . '/' . $id . '.log';
    @touch($log);
    // stdin desde /dev/null: evita que certbot/promesas se bloqueen leyendo
    // de un pipe abierto. El anti-cuelgue (timeout) va DENTRO del wrapper
    // (cert:issue) porque aqui no podemos intercalar 'timeout': el sudoers
    // solo autoriza 'sudo -n /usr/local/sbin/miserver-ctl' sin contraseña.
    $inner = 'nohup sudo -n ' . ctl_path()
        . ' ' . implode(' ', array_map('escapeshellarg', $args))
        . ' </dev/null >> ' . escapeshellarg($log) . ' 2>&1';
    // Shell separado: lanza el comando en segundo plano y sale al instante.
    $full = '( ' . $inner . '; echo "MISERVER_EXIT=$?" >> ' . escapeshellarg($log) . ' ) &';
    $proc = @proc_open($full, [
        0 => ['pipe', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', '/dev/null', 'w'],
    ], $pipes);
    if (is_resource($proc)) {
        fclose($pipes[0]);
        @proc_close($proc); // sh sale al momento; el job queda en segundo plano
    } elseif (function_exists('exec')) {
        @exec($full, $out); // respaldo si proc_open estuviera deshabilitado
    }
    db_run('UPDATE job SET started_at = NOW() WHERE id = ?', [$id]);
}

function job_log(int $id): string
{
    return ctl_log_dir() . '/' . $id . '.log';
}

function job_poll(): void
{
    $rows = db_all("SELECT id FROM job WHERE status = 'running'");
    foreach ($rows as $row) {
        job_finish_if_done((int) $row['id']);
    }
}

function job_finish_if_done(int $id): void
{
    $log = job_log($id);
    if (!is_file($log)) {
        return;
    }
    $content = (string) @file_get_contents($log);
    if (preg_match('/MISERVER_EXIT=(\d+)\s*$/m', $content, $m)) {
        $ok = (int) $m[1] === 0;
        db_run('UPDATE job SET status = ?, exit_code = ?, output = ?, finished_at = NOW() WHERE id = ?', [
            $ok ? 'done' : 'failed', (int) $m[1], mb_substr($content, -3000), $id,
        ]);
    } else {
        // en ejecución: guarda la salida parcial (para progreso)
        db_run('UPDATE job SET output = ? WHERE id = ? AND CHAR_LENGTH(COALESCE(output,\'\')) < ?', [
            mb_substr($content, -3000), $id, 2999,
        ]);
    }
}

/** Devuelve la lista de tareas con estado actualizado. */
function jobs_list(): array
{
    job_poll();
    return db_all('SELECT * FROM job ORDER BY id DESC LIMIT 50');
}

/* ------------------------------------------------------------------ */
/* Información del sistema (con caché de 30 s)                        */
/* ------------------------------------------------------------------ */
function sys_info(): array
{
    $cacheFile = APP_ROOT . '/var/cache/sysinfo.json';
    @mkdir(dirname($cacheFile), 0770, true);
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 30) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }
    $out = [];
    $r = ctl_run(['sys:info']);
    $out['raw'] = ['ok' => $r['exit'] === 0, 'raw' => $r['out']];
    $lines = preg_split('/\r?\n/', $r['out']);
    $disk = [];
    $homes = [];
    foreach ((array) $lines as $line) {
        if (strncmp($line, 'home|', 5) === 0) {
            $homes[] = $line;
        } elseif ($line !== '') {
            $disk[] = $line;
        }
    }
    $out['disk'] = ['ok' => $r['exit'] === 0, 'raw' => implode("\n", $disk)];
    $out['homes'] = ['ok' => $r['exit'] === 0, 'raw' => implode("\n", $homes)];
    $rb = ctl_run(['backup:list']);
    $out['backups'] = ['ok' => $rb['exit'] === 0, 'raw' => $rb['out']];
    file_put_contents($cacheFile, json_encode($out));
    return $out;
}