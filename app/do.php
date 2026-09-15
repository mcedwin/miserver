<?php

declare(strict_types=1);

/**
 * Cliente de la API de DigitalOcean (opcional).
 * Si no hay token configurado, las funciones no hacen nada y el DNS
 * se gestiona manualmente.
 */

function do_token(): string
{
    $row = db_one('SELECT do_token FROM config WHERE id = 1');
    return trim((string) ($row['do_token'] ?? ''));
}

function do_available(): bool
{
    return do_token() !== '';
}

function do_own_ip(): string
{
    $ctx = stream_context_create(['timeout' => 5, 'http' => ['header' => "Connection: close\r\n"]]);
    $ip = @file_get_contents('https://api.ipify.org', false, $ctx);
    return is_string($ip) ? trim($ip) : '';
}

function do_api(string $url, string $method, array $payload = []): array
{
    $token = do_token();
    if ($token === '') {
        return ['ok' => false, 'status' => 0, 'body' => 'sin token'];
    }
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $method === 'GET' ? null : json_encode($payload),
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => (string) $body];
}

/**
 * Crea el dominio en DO apuntando al droplet (A @ y CNAME www).
 */
function do_create_domain(string $domain): array
{
    if (!do_available()) {
        return ['ok' => true, 'msg' => 'DNS automático desactivado (sin token)'];
    }
    $ip = do_own_ip();
    if ($ip === '') {
        return ['ok' => false, 'msg' => 'No se pudo obtener la IP del servidor'];
    }
    // Registrar el dominio (crea registro A con la IP del droplet)
    $r = do_api('https://api.digitalocean.com/v2/domains', 'POST', [
        'name' => $domain, 'ip_address' => $ip,
    ]);
    if (!$r['ok'] && $r['status'] !== 409) { // 409 = ya existe
        return ['ok' => false, 'msg' => 'DO: ' . substr($r['body'], 0, 200)];
    }
    // CNAME www -> dominio raiz
    do_api("https://api.digitalocean.com/v2/domains/{$domain}/records", 'POST', [
        'type' => 'CNAME', 'name' => 'www', 'data' => $domain . '.', 'ttl' => 3600,
    ]);
    return ['ok' => true, 'msg' => 'DNS actualizado en DigitalOcean'];
}

function do_delete_domain(string $domain): array
{
    if (!do_available()) {
        return ['ok' => true, 'msg' => 'DNS automático desactivado (sin token)'];
    }
    $r = do_api("https://api.digitalocean.com/v2/domains/{$domain}", 'DELETE');
    return ['ok' => $r['ok'] || $r['status'] === 404, 'msg' => 'Dominio eliminado de DO'];
}