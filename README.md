# Mi Server

Alternativa ligera a cPanel para gestionar un único servidor (una VPS / droplet):
cuentas, dominios + Apache vhosts, SSL (Let's Encrypt), bases de datos MySQL,
cron del usuario y un gestor de archivos, todo desde un navegador.

**Stack**: PHP 8 puro (sin framework) + MySQL + Apache (mod_mpm_itk) + Certbot.
El panel se sirve en su propio vhost Apache (ServerName dedicado, puertos 80/443)
corriendo como usuario **no privilegiado** (`miserver`, vía `AssignUserID` del
mod_mpm_itk) y delega cada operación privilegiada en un wrapper validado
(`miserver-ctl`) que se ejecuta vía `sudo`.

---

## Estructura

```
├── index.php            # router único (front controller) + arranque
├── cli.php              # herramientas de consola (init, migrate, health)
├── instalarserver.sh    # instalador completo (Ubuntu 22/24, ejecutar como root)
├── app/
│   ├── bootstrap.php    # entorno (.env), sesión, autoload
│   ├── helpers.php      # utilidades: url(), e(), csrf, validación, cifrado
│   ├── db.php           # PDO sobre MySQL del panel
│   ├── ctl.php          # ejecución del wrapper privilegiado miserver-ctl
│   ├── auth.php         # login/logout/guard + protección (rate limit, CSRF)
│   ├── view.php         # render de vistas con layout
│   ├── do.php           # DNS en DigitalOcean + helpers de tareas
│   └── seed.php         # comprobación de esquema y configuración
├── controllers/         # un controller por módulo (ctrl_<modulo>_<accion>)
├── views/               # plantillas PHP
├── assets/              # css/js (vanilla, sin dependencias)
├── res/miserver.sql     # esquema de la BD del panel
└── install/
    └── miserver-ctl             # wrapper privilegiado (validaciones)
```

El router es `index.php`: en el servidor, el vhost del panel usa
`FallbackResource /index.php` para que toda petición que no sea un archivo real
pase por el router (se despachan rutas como `/dbs`, `/files/edit?...`). El
directorio `/assets` se sirve directo por Apache.

---

## Instalación (Ubuntu 22.04 / 24.04)

```bash
# como root
sudo apt update
curl -sSL https://mcedwin.github.io/miserver/instalarserver.sh -o instalarserver.sh
bash instalarserver.sh panel.tudominio.com admin CLAVE_SEGURA
```

Al terminar se mostrará la URL del panel (`http://panel.tudominio.com`), las
credenciales del admin, y se habrán ejecutado automáticamente: Apache + vhosts
por usuario (mod_mpm_itk), el vhost del panel (como `miserver`), MySQL,
certificados Let's Encrypt y vsftpd.

### Requisitos manuales

1. **DNS** — crea el registro A para el panel en tu registrador o proveedor
   DNS (en DigitalOcean: panel de DNS del droplet):
   ```
   A   panel   <IP del servidor>
   A   www     <IP del servidor>   (opcional)
   ```
   Espera la propagación y comprueba con `dig +short panel.tudominio.com`.

2. **Apache** — el instalador ya crea el vhost con `ServerName panel.tudominio.com`
   y además lo deja como vhost *default* con la IP como alias, así que también
   puedes entrar por `http://<IP-del-servidor>` mientras el DNS no resuelva.
   Si cambiaste el dominio después de instalar, pasa el nuevo nombre al script
   (`bash instalarserver.sh panel.tudominio.com ...`) o renombra el vhost a mano.

3. **Firewall** — abre los puertos en ufw/os-security:
   - 22 (SSH) · 80/443 (panel y sitios) · 21/10000-10100 (FTP)

4. **HTTPS** — cuando el A record resuelva, en el panel pulsa **Activar SSL**
   en `panel.tudominio.com` (Dominios), o ejecuta
   `certbot --apache -d panel.tudominio.com`.
5. **Token DigitalOcean** (opcional) — para que el panel cree los registros DNS
   automáticamente al añadir un dominio: `Configuración » Token DigitalOcean`.

### Migración desde el panel anterior (CodeIgniter)

Si vienes del esquema antiguo (código en `core/`):

```bash
cp .env.example .env        # rellena credenciales
mysql -u root miserver < res/miserver.sql
php cli.php migrate         # añade columnas, hashea contraseñas planas
php cli.php health
```

---

## Seguridad

- El proceso del panel corre como usuario **`miserver`** (sin privilegios).
- **Ningún** comando libre llega al host: el panel solo invoca
  `/usr/local/sbin/miserver-ctl`, un wrapper que valida cada argumento contra
  listas blancas (usuario, dominio, nombres de BD, rutas relativas sin `..`,
  contraseñas con juego de caracteres seguro) y registra cada operación en
  `/var/log/miserver/ops.log`.
- `sudoers` restringe `miserver` a ejecutar **únicamente** ese binario.
- Sesiones en el directorio `SESSION_PATH` (`var/sessions`), cookies
  `HttpOnly + SameSite` y CSRF en todos los POST y rate-limiting en el login.
- Archivos del gestor se escriben aplicando `chown`/`chmod` al usuario
  objetivo; nunca se elevan permisos sobre el código del panel.
- Contraseñas de panel y de BD del panel cifradas con `APP_SECRET` (`.env`).

---

## Módulos

| Ruta           | Función                                                        |
| -------------- | -------------------------------------------------------------- |
| `/`            | Dashboard: stats, sitios, últimas tareas y backups (crear/descargar) |
| `/download`    | Descarga de backups (`?f=archivo`)                             |
| `/users`       | Alta/baja de cuentas (admin): usuario Linux + MySQL + vhost    |
| `/dbs`         | Bases y usuarios MySQL, permisos (relaciones)                  |
| `/domains`     | Dominios, vhosts, activar SSL (job asíncrono), DNS-DO          |
| `/cron`        | `crontab` del usuario                                          |
| `/files`       | Gestor de archivos + editor de texto                           |
| `/settings`    | Config del panel, token DO, contraseña                         |
| `/jobs`        | Tareas en segundo plano (certbot, DNS, ...)                    |
| `/login`       | Autenticación                                                  |

Roles: **admin** (ve/opera todo) y **user** (solo su propio contexto). El
admin inicial se crea con `cli.php init` (o el wizard `/setup`).

---

## API de consola

```bash
php cli.php health                          # diagnóstico
php cli.php init --user=admin --domain=panel.tudominio.com --pass=CLAVE
php cli.php migrate                         # migración desde esquema antiguo
```

---

## Levantar el servidor

### En producción (Ubuntu VPS)

```bash
# como root
sudo apt update
curl -sSL https://mcedwin.github.io/miserver/instalarserver.sh -o instalarserver.sh
bash instalarserver.sh panel.tudominio.com admin CLAVE_SEGURA
```

Al terminar el panel queda accesible en `http://panel.tudominio.com` (Apache
vhost, usuario `miserver`). Para HTTPS: entra al panel → **Dominios** → botón
**Activar SSL** de tu dominio (o `certbot --apache -d panel.tudominio.com`).

### En local (desarrollo)

Requisitos: PHP 8+ con `pdo_mysql` y MySQL/MariaDB en la misma máquina.

```bash
# 1) Crear la base de datos del panel y un usuario propio
mysql -u root -p <<'SQL'
CREATE DATABASE miserver CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'miserver'@'localhost' IDENTIFIED BY 'CLAVE_SEGURA';
GRANT ALL PRIVILEGES ON miserver.* TO 'miserver'@'localhost';
FLUSH PRIVILEGES;
SQL

# 2) Copiar y rellenar el .env (APP_SECRET, DB_HOST/PORT/NAME/USER/PASS, SESSION_PATH)
cp .env.example .env

# 3) Importar el esquema
mysql -u miserver -p miserver < res/miserver.sql

# 4) Arrancar el servidor del panel (Windows)
php -S 127.0.0.1:8004 .\index.php
#    Linux/macOS:  php -S 127.0.0.1:8004 ./index.php

# 5) Abrir http://localhost:8004 → asistente /setup crea el admin
#    (o en consola: php cli.php init --user=admin --domain=localhost --pass=CLAVE)

# 6) Comprobar estado
php cli.php health
```

Notas:
- En local no hay `miserver-ctl` instalado, así que las acciones que tocan el
  sistema (crear dominios/cuentas/cron/bases, backups, vhosts) devuelven
  "wrapper miserver-ctl no disponible"; es normal: eso solo funciona en el
  servidor. Sí puedes entrar, ver el dashboard, gestionar el panel, configuración
  y probar la interfaz.
- `index.php` actúa de router: `php -S ... .\index.php` sirve también `/assets`.
- Sin base de datos no hay modo demo: `/setup` te indicará que importes
  `res/miserver.sql`.

---

## Notas de despliegue en producción

- Emite HTTPS para el dominio del panel (Certbot desde el panel, módulo
  Dominios → SSL, o `certbot --apache -d panel.tudominio.com`); el vhost ya
  contempla los puertos 80/443.
- Haz `chmod 600` de `.env` y revisa los permisos de `var/`.
- Monitoriza `systemctl status apache2` y los logs `/var/log/miserver/` y
  `/var/log/apache2/miserver-panel-*`.
- Los crontab de estudiantes se instalan en la cuenta correspondiente; el panel
  no agrega tareas propias. Los backups son manuales y se disparan desde el
  dashboard (Inicio → Crear backup).