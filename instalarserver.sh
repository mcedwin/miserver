#!/bin/bash
# =============================================================================
# instalarserver.sh — Instala y configura "Mi Server" en Ubuntu Server
#
# Uso (como ROOT):
#   bash instalarserver.sh <panel_host> <panel_admin_user> <panel_admin_pass>
#
# Ejemplo:
#   bash instalarserver.sh panel.misitio.com admin CLAVESEGURA123
#
# Qué hace:
#   1) Actualiza el sistema e instala: Apache2, PHP+CLI, MySQL, certbot,
#      vsftpd, mod_ruid2 (compilado desde fuente en 24.04) y utilidades.
#   2) Crea el usuario 'miserver' (dueño del panel y del proceso web).
#   3) Configura MySQL: base 'miserver' + usuario propio del panel.
#   4) Instala el código del panel en /home/miserver/panel.
#   5) Crea el archivo .env con las credenciales de la BD del panel.
#   6) Instala el wrapper privilegiado /usr/local/sbin/miserver-ctl + sudoers.
#   7) Crea el vhost Apache del panel (ServerName=$PANEL_HOST en 80/443) que
#      corre como usuario 'miserver' (mod_ruid2) — sin php -S ni puerto 8004.
#   8) Prepara /var/backups/miserver y el comando backup:run del wrapper
#      (backups MANUALES: archivos + carpetas + bases de datos, desde el panel).
# =============================================================================
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Debes ejecutar este script como root." >&2; exit 1; }

PANEL_HOST="${1:-panel.local}"
ADMIN_USER="${2:-admin}"
ADMIN_PASS="${3:-}"

# Repositorio del panel (usado si el instalador se ejecuta sin el código al lado).
PANEL_GIT_URL="${PANEL_GIT_URL:-https://github.com/mcedwin/miserver.git}"
PANEL_GIT_BRANCH="${PANEL_GIT_BRANCH:-main}"

[ -z "$ADMIN_PASS" ] && ADMIN_PASS="$(openssl rand -hex 8)"
[ ${#ADMIN_PASS} -ge 8 ] || { echo "La contraseña del admin debe tener 8+ caracteres." >&2; exit 1; }

export DEBIAN_FRONTEND=noninteractive
log(){ echo "==> $*"; }

# ---------------------------------------------------------------------------
log "1/9 Actualizando sistema e instalando paquetes..."
# ---------------------------------------------------------------------------
apt-get update -y
apt-get upgrade -y
apt-get install -y --no-install-recommends \
  apache2 libapache2-mod-php apache2-dev build-essential libcap-dev php-cli php-mysql php-mbstring \
  php-xml php-curl mysql-server mysql-client certbot python3-certbot-apache \
  vsftpd curl wget unzip acl rsync ca-certificates git cron

# Certbot: verificación en Ubuntu 24.04 (apt) + plugin del Apache.
log "   Comprobando Certbot y el plugin apache..."
if ! certbot --version >/dev/null 2>&1; then
  echo "   certbot no responde; reintentando instalación..."
  apt-get install -y certbot python3-certbot-apache || true
fi
if ! certbot plugins 2>/dev/null | grep -qi '^ *apache\|mod_auth\|apache'; then
  echo "   plugin apache de certbot NO detectado; instalando python3-certbot-apache..."
  apt-get install -y --no-install-recommends python3-certbot-apache || \
    echo "   ERROR: no se pudo instalar el plugin apache. Hazlo manualmente: apt install python3-certbot-apache" >&2
fi
certbot --version && certbot plugins | grep -i apache && echo "   -> certbot OK con plugin apache" \
  || echo "   ATENCION: verifica certbot --version y certbot plugins en la consola"

# MPM prefork (necesario por mod_php) + mod_ruid2 (usuario por vhost) + rewrite + ssl
a2dismod -f mpm_worker mpm_event >/dev/null 2>&1 || true
a2enmod -f mpm_prefork >/dev/null 2>&1 || true
a2enmod rewrite headers ssl >/dev/null 2>&1 || true

# mod_ruid2: el paquete .deb solo existe hasta Ubuntu 22.04; en 24.04 (noble)
# se compila desde fuente (mismo upstream 0.9.8 que usa el paquete de Debian).
if ! apt-get install -y --no-install-recommends libapache2-mod-ruid2 >/dev/null 2>&1; then
  log "   libapache2-mod-ruid2 no está en los repos (24.04); compilando desde fuente..."
  ruid_tmp="$(mktemp -d)"
  curl -fsSL https://github.com/mind04/mod-ruid2/archive/refs/heads/master.tar.gz -o "$ruid_tmp/mod-ruid2.tar.gz"
  tar -xzf "$ruid_tmp/mod-ruid2.tar.gz" -C "$ruid_tmp"
  if ( cd "$ruid_tmp"/mod-ruid2-master && apxs2 -i -c -l cap mod_ruid2.c ) \
     && [ -f /usr/lib/apache2/modules/mod_ruid2.so ]; then
    rm -rf "$ruid_tmp"
  else
    rm -rf "$ruid_tmp"
    echo "ERROR: no se pudo compilar mod_ruid2." >&2
    exit 1
  fi
fi
if ! grep -q "ruid2_module" /etc/apache2/mods-available/ruid2.load 2>/dev/null; then
  echo "LoadModule ruid2_module /usr/lib/apache2/modules/mod_ruid2.so" > /etc/apache2/mods-available/ruid2.load
fi
a2enmod -f ruid2 >/dev/null 2>&1 || true

php_mod="$(ls /etc/apache2/mods-available/php*.load 2>/dev/null | sed 's#.*/##; s#\.load##' | head -1 || true)"
[ -n "${php_mod:-}" ] && a2enmod -f "$php_mod" >/dev/null 2>&1 || true
php -d opcache.enable_cli=1 -r 'echo "PHP ok\n";'

# ---------------------------------------------------------------------------
log "2/9 Creando usuario del panel (miserver)."
# ---------------------------------------------------------------------------
if ! id miserver >/dev/null 2>&1; then
  useradd -m -s /bin/bash miserver
fi

# ---------------------------------------------------------------------------
log "3/9 Configurando MySQL (panel: base miserver + credenciales)."
# ---------------------------------------------------------------------------
# Arranca MySQL y espera a que responda (el primer arranque de MySQL 8 puede tardar).
systemctl start mysql 2>/dev/null || service mysql start || true
systemctl enable mysql >/dev/null 2>&1 || true
for _i in $(seq 1 45); do mysqladmin ping >/dev/null 2>&1 && break; sleep 2; done
mysqladmin ping >/dev/null 2>&1 || {
  echo "ERROR: MySQL no responde. Revisa: systemctl status mysql; tail -50 /var/log/mysql/error.log" >&2
  exit 1
}

# MySQL accesible remotamente (los usuarios del panel se crean también como
# 'usuario'@'%'). Controla quién llega al 3306 con el firewall (ufw).
cnf=/etc/mysql/mysql.conf.d/mysqld.cnf
[ -f "$cnf" ] || cnf=/etc/mysql/my.cnf
if [ -f "$cnf" ]; then
  sed -i 's/^bind-address\s*=.*/bind-address = 0.0.0.0/' "$cnf"
  if ! grep -q '^bind-address' "$cnf"; then
    printf '\n[mysqld]\nbind-address = 0.0.0.0\n' >> "$cnf"
  fi
  sed -i 's/^mysqlx-bind-address\s*=.*/mysqlx-bind-address = 0.0.0.0/' "$cnf" || true
  systemctl restart mysql 2>/dev/null || service mysql restart >/dev/null 2>&1 || true
  for _i in $(seq 1 30); do mysqladmin ping >/dev/null 2>&1 && break; sleep 2; done
  mysqladmin ping >/dev/null 2>&1 || echo "ATENCION: MySQL no volvió a responder tras reconfigurar bind-address." >&2
fi

# MySQL remoto a la vista: si ufw está activo, abre el 3306 (los usuarios del
# panel se crean con cuenta 'usuario'@'%'; el firewall controla quién llega).
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q 'Status: active'; then
  ufw allow 3306/tcp >/dev/null 2>&1 && log "   ufw: permitido 3306/tcp (MySQL remoto)" \
    || echo "   ATENCION: no se pudo abrir 3306 en ufw (hazlo manualmente)." >&2
else
  echo "   (ufw no está activo; si lo actives luego: ufw allow 3306/tcp)"
fi

# openssl rand (no usar pipe: tr|head recibe SIGPIPE y con pipefail aborta en silencio).
PANEL_DB_PASS="$(openssl rand -hex 12)"
# MySQL 8.0 (Ubuntu 24.04) rechaza GRANT sobre information_schema: no se otorga.
mysql <<SQL
CREATE DATABASE IF NOT EXISTS miserver CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'miserver'@'localhost' IDENTIFIED BY '${PANEL_DB_PASS}';
ALTER USER 'miserver'@'localhost' IDENTIFIED BY '${PANEL_DB_PASS}';
GRANT ALL PRIVILEGES ON miserver.* TO 'miserver'@'localhost';
FLUSH PRIVILEGES;
SQL

log "   (la base del panel NO monta a usuarios: esos se crean por el wrapper)"

# ---------------------------------------------------------------------------
log "4/9 Instalando código del panel en /home/miserver/panel."
# ---------------------------------------------------------------------------
install -d -o miserver -g miserver /home/miserver/panel
if [ ! -f /home/miserver/panel/index.php ]; then
  SRC="$(cd "$(dirname "$0")" && pwd)"
  if [ -f "$SRC/index.php" ] && [ -f "$SRC/res/miserver.sql" ]; then
    echo "   copiando código desde: $SRC"
    cp -rp "$SRC"/. /home/miserver/panel/
  else
    echo "   clonando panel desde: $PANEL_GIT_URL ($PANEL_GIT_BRANCH)"
    git clone -q -b "$PANEL_GIT_BRANCH" "$PANEL_GIT_URL" /home/miserver/panel
  fi
elif [ ! -f /home/miserver/panel/res/miserver.sql ]; then
  SRC="$(cd "$(dirname "$0")" && pwd)"
  if [ -f "$SRC/res/miserver.sql" ]; then
    echo "   instalación previa incompleta: copiando res/ (esquema SQL)"
    cp -rp "$SRC"/res /home/miserver/panel/
  else
    echo "   instalación previa incompleta: clonando res/ desde $PANEL_GIT_URL"
    git clone -q -b "$PANEL_GIT_BRANCH" --depth 1 "$PANEL_GIT_URL" /tmp/panel-src
    cp -rp /tmp/panel-src/res /home/miserver/panel/
    rm -rf /tmp/panel-src
  fi
fi
echo "   limpieza: no se despliega core/ (panel antiguo); se conserva .git para actualizar con 'git pull'"
rm -rf /home/miserver/panel/core
for d in var/sessions var/cache var/log; do install -d -o miserver -g miserver "/home/miserver/panel/$d"; done
chown -R miserver:miserver /home/miserver/panel
chmod -R u+rwX,go-w /home/miserver/panel
chmod 600 /home/miserver/panel/.env 2>/dev/null || true

# ---------------------------------------------------------------------------
log "5/9 Creando .env del panel."
# ---------------------------------------------------------------------------
tee /home/miserver/panel/.env > /dev/null <<EOF
APP_BASEURL=/
APP_SECRET=$(openssl rand -hex 24)
APP_TIMEZONE=America/Lima

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=miserver
DB_USER=miserver
DB_PASS=${PANEL_DB_PASS}

CTL_PATH=/usr/local/sbin/miserver-ctl
BACKUP_DIR=/var/backups/miserver
SESSION_PATH=/home/miserver/panel/var/sessions
EOF
chown miserver:miserver /home/miserver/panel/.env
chmod 600 /home/miserver/panel/.env

log "   Importando esquema de la base..."
mysql -u miserver -p"${PANEL_DB_PASS}" miserver < /home/miserver/panel/res/miserver.sql

# ---------------------------------------------------------------------------
log "6/9 Instalando wrapper privilegiado + sudoers."
# ---------------------------------------------------------------------------
install -m 0755 /home/miserver/panel/install/miserver-ctl /usr/local/sbin/miserver-ctl

# Directorio de logs de tareas en segundo plano (certbot): debe ser escribible
# por PHP (usuario 'miserver') o las tareas quedan "running" para siempre
# (no se escribe el marcador MISERVER_EXIT).
install -d -o miserver -g miserver -m 0770 /var/log/miserver/jobs

# Un cron de root mantiene la copia /usr/local/sbin/miserver-ctl sincronizada
# con el repo del panel: tras un "git pull" (o edición manual), el wrapper se
# refresca en < 1 minuto sin pasos extra.
cat > /etc/cron.d/miserver <<'EOF'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
# Si cambia el wrapper del repo, actualiza la copia root.
* * * * * root cmp -s /home/miserver/panel/install/miserver-ctl /usr/local/sbin/miserver-ctl || /usr/bin/install -m 0755 /home/miserver/panel/install/miserver-ctl /usr/local/sbin/miserver-ctl
EOF
chown root:root /etc/cron.d/miserver
chmod 644 /etc/cron.d/miserver
echo "   cron: sincronización del wrapper activa (/etc/cron.d/miserver)"

cat > /etc/sudoers.d/miserver <<'EOF'
miserver ALL=(root) NOPASSWD: /usr/local/sbin/miserver-ctl
Defaults!/usr/local/sbin/miserver-ctl !requiretty
EOF
chmod 440 /etc/sudoers.d/miserver
visudo -cf /etc/sudoers.d/miserver >/dev/null 2>&1 && echo "   sudoers ok"

log "   Creando cuenta admin y vhost principal del panel..."
su - miserver -s /bin/bash -c \
  "cd /home/miserver/panel && php cli.php init --user=${ADMIN_USER} --domain=${PANEL_HOST} --pass='${ADMIN_PASS}'" \
  && echo "   admin creado" || echo "   ATENCIÓN: no se pudo crear el admin (revisa arriba)"

# ---------------------------------------------------------------------------
log "7/9 Creando vhost Apache del panel (ServerName: ${PANEL_HOST})."
# ---------------------------------------------------------------------------
# Deja de usar el servidor de desarrollo php -S si existía un servicio previo
systemctl disable --now miserver.service >/dev/null 2>&1 || true
rm -f /etc/systemd/system/miserver.service
systemctl daemon-reload >/dev/null 2>&1 || true

IP="$(hostname -I 2>/dev/null | awk '{print $1}')"

# Certificado autofirmado para HTTPS por IP/túnel/panel mientras no haya LE.
# SAN: IP pública, loopback (para el túnel SSH) y subdominio del panel.
install -d -m 0755 /etc/ssl/miserver
if [ ! -f /etc/ssl/miserver/miserver-selfsigned.key ] || [ ! -f /etc/ssl/miserver/miserver-selfsigned.crt ]; then
  openssl req -x509 -nodes -newkey rsa:2048 -days 1095 \
    -keyout /etc/ssl/miserver/miserver-selfsigned.key \
    -out /etc/ssl/miserver/miserver-selfsigned.crt \
    -subj "/CN=${PANEL_HOST}" \
    -addext "subjectAltName=IP:${IP},IP:127.0.0.1,DNS:${PANEL_HOST},DNS:localhost" >/dev/null 2>&1
fi
chmod 600 /etc/ssl/miserver/miserver-selfsigned.key 2>/dev/null || true

# 1) Catch-all NEGADO: cualquier Host sin vhost (IP, dominio raiz, subdominios
#    sin sitio) recibe 403. El panel SOLO responde al ServerName exacto.
cat > /etc/apache2/sites-available/miserver-default.conf <<APACHE
<VirtualHost _default_:80>
  ServerName miserver-default.invalid
  DocumentRoot /var/www/html
  <Directory /var/www/html>
    Require all denied
  </Directory>
  ErrorLog \${APACHE_LOG_DIR}/miserver-default-error.log
  CustomLog \${APACHE_LOG_DIR}/miserver-default-access.log combined
</VirtualHost>

<VirtualHost _default_:443>
  ServerName miserver-default.invalid
  DocumentRoot /var/www/html
  SSLEngine on
  SSLCertificateFile /etc/ssl/miserver/miserver-selfsigned.crt
  SSLCertificateKeyFile /etc/ssl/miserver/miserver-selfsigned.key
  <Directory /var/www/html>
    Require all denied
  </Directory>
  ErrorLog \${APACHE_LOG_DIR}/miserver-default-error.log
  CustomLog \${APACHE_LOG_DIR}/miserver-default-access.log combined
</VirtualHost>
APACHE
chmod 644 /etc/apache2/sites-available/miserver-default.conf

# 2) Panel SOLO en el ServerName exacto (sin ServerAlias: ni IP ni www ni raiz
#    lo sirven; esos caen al catch-all 403 o al túnel 8443).
cat > "/etc/apache2/sites-available/${PANEL_HOST}.conf" <<APACHE
<VirtualHost *:80>
  ServerName $PANEL_HOST
  DocumentRoot /home/miserver/panel

  <Directory /home/miserver/panel>
    Options -Indexes
    AllowOverride None
    Require all granted
    FallbackResource /index.php
  </Directory>

  <IfModule mod_ruid2.c>
    RMode config
    RUidGid miserver miserver
  </IfModule>

  <LocationMatch "^(/var/|/install/|/core/|/res/|/\.env(\.|$)|/\.git/|/\.gitignore|/cli\.php)">
    Require all denied
  </LocationMatch>
  <FilesMatch "\.(sql|md|sh|example|bak|swp)$">
    Require all denied
  </FilesMatch>

  <IfModule mod_php.c>
    php_admin_value upload_max_filesize 64M
    php_admin_value post_max_size 80M
    php_admin_value memory_limit 128M
    php_admin_value display_errors Off
  </IfModule>

  ErrorLog \${APACHE_LOG_DIR}/miserver-panel-error.log
  CustomLog \${APACHE_LOG_DIR}/miserver-panel-access.log combined
</VirtualHost>

<VirtualHost *:443>
  ServerName $PANEL_HOST
  DocumentRoot /home/miserver/panel

  SSLEngine on
  SSLCertificateFile /etc/ssl/miserver/miserver-selfsigned.crt
  SSLCertificateKeyFile /etc/ssl/miserver/miserver-selfsigned.key

  <Directory /home/miserver/panel>
    Options -Indexes
    AllowOverride None
    Require all granted
    FallbackResource /index.php
  </Directory>

  <IfModule mod_ruid2.c>
    RMode config
    RUidGid miserver miserver
  </IfModule>

  <LocationMatch "^(/var/|/install/|/core/|/res/|/\.env(\.|$)|/\.git/|/\.gitignore|/cli\.php)">
    Require all denied
  </LocationMatch>
  <FilesMatch "\.(sql|md|sh|example|bak|swp)$">
    Require all denied
  </FilesMatch>

  <IfModule mod_php.c>
    php_admin_value upload_max_filesize 64M
    php_admin_value post_max_size 80M
    php_admin_value memory_limit 128M
    php_admin_value display_errors Off
  </IfModule>

  ErrorLog \${APACHE_LOG_DIR}/miserver-panel-error.log
  CustomLog \${APACHE_LOG_DIR}/miserver-panel-access.log combined
</VirtualHost>
APACHE
chmod 644 "/etc/apache2/sites-available/${PANEL_HOST}.conf"

# 3) Panel por IP sin DNS: puerto administrativo 8443 directo (0.0.0.0).
#    Acceso: https://${IP}:8443  (HTTPS con certificado autofirmado).
#    Para endurecerlo se puede restringir en ufw a la propia IP de admin.
cat > /etc/apache2/conf-available/miserver-panel-8443.conf <<APACHE
Listen 8443
APACHE
chmod 644 /etc/apache2/conf-available/miserver-panel-8443.conf

cat > /etc/apache2/sites-available/miserver-panel-8443.conf <<APACHE
<VirtualHost *:8443>
  ServerName $PANEL_HOST
  DocumentRoot /home/miserver/panel

  SSLEngine on
  SSLCertificateFile /etc/ssl/miserver/miserver-selfsigned.crt
  SSLCertificateKeyFile /etc/ssl/miserver/miserver-selfsigned.key

  <Directory /home/miserver/panel>
    Options -Indexes
    AllowOverride None
    Require all granted
    FallbackResource /index.php
  </Directory>

  <IfModule mod_ruid2.c>
    RMode config
    RUidGid miserver miserver
  </IfModule>

  <LocationMatch "^(/var/|/install/|/core/|/res/|/\.env(\.|$)|/\.git/|/\.gitignore|/cli\.php)">
    Require all denied
  </LocationMatch>
  <FilesMatch "\.(sql|md|sh|example|bak|swp)$">
    Require all denied
  </FilesMatch>

  <IfModule mod_php.c>
    php_admin_value upload_max_filesize 64M
    php_admin_value post_max_size 80M
    php_admin_value memory_limit 128M
    php_admin_value display_errors Off
  </IfModule>

  ErrorLog \${APACHE_LOG_DIR}/miserver-panel-8443-error.log
  CustomLog \${APACHE_LOG_DIR}/miserver-panel-8443-access.log combined
</VirtualHost>
APACHE
chmod 644 /etc/apache2/sites-available/miserver-panel-8443.conf

# Activa catch-all (primero), panel (solo ServerName) y puerto administración.
a2ensite miserver-default.conf >/dev/null 2>&1 || true
a2ensite "${PANEL_HOST}.conf" >/dev/null 2>&1 || true
a2ensite miserver-panel-8443.conf >/dev/null 2>&1 || true
a2enconf miserver-panel-8443 >/dev/null 2>&1 || true
# Desactiva los sitios por defecto de Ubuntu.
a2dissite 000-default.conf >/dev/null 2>&1 || true
a2dissite default-ssl.conf >/dev/null 2>&1 || true
apache2ctl -t >/dev/null && systemctl reload apache2 || exit 1

# ---------------------------------------------------------------------------
log "8/9 Preparando directorio de backups (manuales, desde el panel)."
# ---------------------------------------------------------------------------
install -d -o root -g root -m 0700 /var/backups/miserver

# ---------------------------------------------------------------------------
log "9/9 Resumen final."
# ---------------------------------------------------------------------------
cat <<EOF

═══════════════════════════════════════════════════════════════════
  Panel "Mi Server" instalado.

  URL del panel : https://${PANEL_HOST}   (solo este Host sirve el panel)
                 Por IP sin DNS: https://${IP}:8443 (HTTPS autofirmado)

  Admin         : ${ADMIN_USER}  /  ${ADMIN_PASS}

  Notas:
   * Abre los puertos en el firewall si usas ufw/os-security:
       ufw allow 22/tcp; ufw allow 80/tcp; ufw allow 443/tcp
       ufw allow 8443/tcp  (panel por IP; limítalo a tu IP: ufw allow from TU_IP to any port 8443)
       ufw allow 21/tcp; ufw allow 10000:10100/tcp (FTP pasivo)
       ufw allow 3306/tcp  (MySQL REMOTO; los usuarios se crean como 'user'@'%')
   * El panel SOLO responde en el ServerName exacto ${PANEL_HOST} (subdominio
     dedicado) o en https://${IP}:8443 (acceso admin por IP). Cualquier otro
     Host (dominio raiz, subdominios sin sitio) recibe 403 del vhost catch-all.
   * Los sitios de las cuentas se crean desde el panel con sus propios dominios
     (ServerName exacto).
   * MySQL remoto: la cuenta 'admin' ve todas las bases; el resto solo las suyas
     (prefijo usuario_). El puerto 3306 ya queda a la escucha (bind 0.0.0.0).
   * Certbot se instaló por apt (Ubuntu 24.04) con el plugin apache. La emisión
     se hace desde el panel (Dominios -> Activar SSL) o con:
       certbot --apache --non-interactive --agree-tos --redirect -d tusitio.com
   * Crea cuentas/dominios desde el panel; cada cuenta tendrá su
     usuario Linux, vhost Apache y usuario MySQL propios.
   * Los backups se hacen manualmente desde el panel (Inicio → Crear backup)
     y guardan homes + bases de datos en /var/backups/miserver.
   * Guarda esta salida: si pierdes el .env perderás acceso a la BD.
═══════════════════════════════════════════════════════════════════
EOF