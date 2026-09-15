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
  vsftpd curl wget unzip acl rsync ca-certificates

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
  echo "   copiando código desde: $SRC"
  cp -rp "$SRC"/. /home/miserver/panel/
  # en producción se recomienda: git clone <repo> /home/miserver/panel
fi
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

# Certificado autofirmado para poder usar HTTPS por IP/subdominio de inmediato
# (mientras no haya DNS + Let's Encrypt). Cubre IP y dominio del panel.
install -d -m 0755 /etc/ssl/miserver
if [ ! -f /etc/ssl/miserver/miserver-selfsigned.key ] || [ ! -f /etc/ssl/miserver/miserver-selfsigned.crt ]; then
  openssl req -x509 -nodes -newkey rsa:2048 -days 1095 \
    -keyout /etc/ssl/miserver/miserver-selfsigned.key \
    -out /etc/ssl/miserver/miserver-selfsigned.crt \
    -subj "/CN=${PANEL_HOST}" \
    -addext "subjectAltName=IP:${IP},DNS:${PANEL_HOST},DNS:www.${PANEL_HOST}" >/dev/null 2>&1
fi
chmod 600 /etc/ssl/miserver/miserver-selfsigned.key 2>/dev/null || true

cat > "/etc/apache2/sites-available/${PANEL_HOST}.conf" <<APACHE
<VirtualHost _default_:80>
  ServerName $PANEL_HOST
  ServerAlias www.$PANEL_HOST $IP
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

<VirtualHost _default_:443>
  ServerName $PANEL_HOST
  ServerAlias www.$PANEL_HOST $IP
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
a2ensite "${PANEL_HOST}.conf" >/dev/null 2>&1 || true
# Desactiva el sitio por defecto de Ubuntu: de lo contrario, las peticiones por IP
# (o con Host desconocido) las gana "000-default" (/var/www/html) y no el panel.
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

  URL del panel : http://${PANEL_HOST}   (o directamente por IP: http://${IP})
                 HTTPS: entra al panel → Dominios → botón SSL cuando el DNS resuelva

  Admin         : ${ADMIN_USER}  /  ${ADMIN_PASS}

  Notas:
   * Abre los puertos en el firewall si usas ufw/os-security:
       ufw allow 22/tcp; ufw allow 80/tcp; ufw allow 443/tcp
       ufw allow 21/tcp; ufw allow 10000:10100/tcp (FTP pasivo)
   * El dominio ${PANEL_HOST} apunta al PANEL (Apache + mod_ruid2 como
     usuario 'miserver'). Los sitios de las cuentas se crean desde el panel
     con sus propios dominios.
   * Crea cuentas/dominios desde el panel; cada cuenta tendrá su
     usuario Linux, vhost Apache y usuario MySQL propios.
   * Los backups se hacen manualmente desde el panel (Inicio → Crear backup)
     y guardan homes + bases de datos en /var/backups/miserver.
   * Guarda esta salida: si pierdes el .env perderás acceso a la BD.
═══════════════════════════════════════════════════════════════════
EOF