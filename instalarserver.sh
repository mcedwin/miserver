#!/bin/bash
cd /root/

sudo apt update -y
sudo apt upgrade -y

sudo apt install apache2 -y
sudo apt install mysql-server -y
sudo apt install php libapache2-mod-php php-mysql -y
sudo apt-get install -y php8.3-cli php8.3-common php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring php8.3-curl php8.3-xml php8.3-bcmath php8.3-intl

sudo a2dismod php8.3
sudo a2dismod mpm_prefork

sudo apt-get install libapache2-mpm-itk -y
sudo a2enmod mpm_itk
sudo a2enmod php8.3

sudo apt-get install curl unzip -y
sudo apt-get install php php-curl -y
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer self-update


echo -e '#!/bin/bash\nphp -S 0.0.0.0:8004 -t /root/miserver' > /root/start_php_server.sh

chmod +x /root/start_php_server.sh

echo -e '[Unit]\nDescription=PHP Development Server\n[Service]\nExecStart=/root/start_php_server.sh\nRestart=always\nUser=root\n[Install]\nWantedBy=multi-user.target' > /etc/systemd/system/php_server.service

git clone https://github.com/mcedwin/miserver.git /root/miserver
cd /root/miserver/core
composer install --no-interaction

cd /root/

sudo systemctl daemon-reload
sudo systemctl enable php_server.service
sudo systemctl start php_server.service

sudo apt install certbot python3-certbot-apache -y

sudo sed -i 's/^bind-address.*$/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf
sudo sed -i 's/^display_errors = .*/display_errors = On/' /etc/php/8.3/apache2/php.ini
sudo sed -i 's/^#PasswordAuthentication.*$/PasswordAuthentication yes/' /etc/ssh/sshd_config

sudo apachectl restart
sudo service ssh restart
sudo systemctl restart mysql

echo -e '#!/bin/bash\n# Configuración\nBACKUP_DIR="/root/miserver/backups"\nDATE=$(date +%F)\n# Crear directorio de backups si no existe\nmkdir -p $BACKUP_DIR\n# Obtener lista de usuarios en /home (excluir root y cuentas del sistema)\nusers=$(ls /home | grep -Ev "root|lost\+found")\n# Iterar sobre cada usuario y hacer el backup\nfor user in $users; do\n    tar -czvf $BACKUP_DIR/$user-backup-$DATE.tar.gz /home/$user\ndone\n# Eliminar backups antiguos (opcional)\nfind $BACKUP_DIR -type f -name "*.tar.gz" -mtime +7 -exec rm {} \;' > /root/backup_homes.sh

chmod +x /root/backup_homes.sh


echo -e '#!/bin/bash\n# Directorio donde se guardarán los backups\nBACKUP_DIR="/root/miserver/backups"\n# Credenciales de MySQL\nMYSQL_USER="tu_usuario"\nMYSQL_PASSWORD="tu_contraseña"\n\n# Crear el directorio de backups si no existe\nmkdir -p ${BACKUP_DIR}\n\n# Obtener la lista de todas las bases de datos\n\ndatabases=$(mysql -e "SHOW DATABASES;" | tr -d "| " | grep -v Database)\n# Realizar un backup de cada base de datos\nfor db in $databases; do\n  if [[ "$db" != "information_schema" && "$db" != "performance_schema" && "$db" != "mysql" && "$db" != "sys" ]]; then\n    echo "Respaldando la base de datos: $db"\n    mysqldump --databases $db | gzip > ${BACKUP_DIR}/${db}-backup-$(date +%F).sql.gz\n  fi\ndone\n\n# Eliminar los backups que tengan más de 7 días\nfind ${BACKUP_DIR} -type f -name "*.sql.gz" -mtime +7 -exec rm {} \;' > /root/backup_databases.sh

chmod +x /root/backup_databases.sh



TEMP_CRON=$(mktemp)
echo -e "0 0 * * * /root/backup_homes.sh >> /root/backup_databases.log 2>&1\n0 0 * * * /root/backup_databases.sh >> /root/backup_homes.log 2>&1" > "$TEMP_CRON"
sudo crontab -u root "$TEMP_CRON"
rm -f "$TEMP_CRON"