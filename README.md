An alternative to CPANEL, to manage a single instance or a droplet.
# Domain
add domain
A punored.com IP

add sudomain wildcard
CNAME  *.punored.com IP

# install server in ubuntu
sudo apt update
sudo apt install apache2
sudo apt install mysql-server
sudo apt install php libapache2-mod-php php-mysql
sudo apt-get install -y php8.3-cli php8.3-common php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring php8.3-curl php8.3-xml php8.3-bcmath php8.3-intl
sudo apt install vsftpd

-----sudo apt-get install acl
sudo a2enmod suexec
sudo systemctl restart apache2

sudo echo -e 'pasv_enable=YES\npasv_min_port=10000\npasv_max_port=10100\nchroot_local_user=YES\nallow_writeable_chroot=YES\nforce_dot_files=YES' >> /etc/vsftpd.conf
sudo systemctl restart vsftpd
sudo systemctl enable vsftpd
sudo systemctl status vsftpd


# install composer

sudo apt-get install curl unzip
sudo apt-get install php php-curl
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer self-update 

# server
echo -e '#!/bin/bash\nphp -S 0.0.0.0:8004 -t /root/miserver' > start_php_server.sh

chmod +x start_php_server.sh

echo -e '[Unit]\nDescription=PHP Development Server\n[Service]\nExecStart=/root/start_php_server.sh\nRestart=always\nUser=root\n[Install]\nWantedBy=multi-user.target' > /etc/systemd/system/php_server.service

git clone https://github.com/mcedwin/miserver.git
cd miserver/core
composer install

sudo systemctl daemon-reload
sudo systemctl enable php_server.service
sudo systemctl start php_server.service

# install cerbot
sudo apt install certbot python3-certbot-apache

# serstar services
apachectl restart
service ssh restart
systemctl restart mysql

![Screenshot](res/01users.png)
![Screenshot](res/02files.png)
![Screenshot](res/03dbs.png)
![Screenshot](res/04doms.png)

# MYSQL
Configurar bind 0.0.0.0
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# PHP display_errors = on
sudo nano /etc/php/8.3/apache2/php.ini

# PasswordAuthentication yes, for ppk login 
sudo nano /etc/ssh/sshd_config

# lo mismo pero en sshd_config.d




# Reset all
sudo apachectl restart
sudo service ssh restart
sudo systemctl restart mysql

# For deny user home directory (try)
https://unix.stackexchange.com/questions/85537/how-to-hide-someone-elses-directories-from-a-user

# refresy certs
sudo certbot --apache



# backups
echo -e '#!/bin/bash\n# Configuración\nBACKUP_DIR="/root/miserver/backups"\nDATE=$(date +%F)\n# Crear directorio de backups si no existe\nmkdir -p $BACKUP_DIR\n# Obtener lista de usuarios en /home (excluir root y cuentas del sistema)\nusers=$(ls /home | grep -Ev "root|lost\+found")\n# Iterar sobre cada usuario y hacer el backup\nfor user in $users; do\n    tar -czvf $BACKUP_DIR/$user-backup-$DATE.tar.gz /home/$user\ndone\n# Eliminar backups antiguos (opcional)\nfind $BACKUP_DIR -type f -name "*.tar.gz" -mtime +7 -exec rm {} \;' > backup_homes.sh

chmod +x backup_homes.sh


echo -e '#!/bin/bash\n# Directorio donde se guardarán los backups\nBACKUP_DIR="/root/miserver/backups"\n# Credenciales de MySQL\nMYSQL_USER="tu_usuario"\nMYSQL_PASSWORD="tu_contraseña"\n\n# Crear el directorio de backups si no existe\nmkdir -p ${BACKUP_DIR}\n\n# Obtener la lista de todas las bases de datos\n\ndatabases=$(mysql -e "SHOW DATABASES;" | tr -d "| " | grep -v Database)\n# Realizar un backup de cada base de datos\nfor db in $databases; do\n  if [[ "$db" != "information_schema" && "$db" != "performance_schema" && "$db" != "mysql" && "$db" != "sys" ]]; then\n    echo "Respaldando la base de datos: $db"\n    mysqldump --databases $db | gzip > ${BACKUP_DIR}/${db}-backup-$(date +%F).sql.gz\n  fi\ndone\n\n# Eliminar los backups que tengan más de 7 días\nfind ${BACKUP_DIR} -type f -name "*.sql.gz" -mtime +7 -exec rm {} \;' > backup_databases.sh

chmod +x backup_databases.sh

sudo yum install cronie

crontab -e
0 0 * * * /root/backup_homes.sh >> /root/backup_databases.log 2>&1
0 0 * * * /root/backup_databases.sh >> /root/backup_homes.log 2>&1




# EN ami linux

sudo yum update -y
sudo dnf install -y httpd wget php-fpm php-mysqli php-json php php-devel php-intl
sudo dnf install mariadb105-server

sudo systemctl enable mariadb
sudo systemctl restart mariadb


sudo yum install vsftpd -y
echo -e 'pasv_enable=YES\npasv_min_port=10000\npasv_max_port=10100\nchroot_local_user=YES\nallow_writeable_chroot=YES\nforce_dot_files=YES' | sudo tee -a /etc/vsftpd/vsftpd.conf

sudo systemctl enable httpd
sudo systemctl start httpd
sudo systemctl restart vsftpd
sudo systemctl enable vsftpd
sudo systemctl status vsftpd


sudo yum install curl unzip -y
sudo yum install php php-curl -y
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer self-update

cd /root

echo -e '#!/bin/bash\nphp -S 0.0.0.0:8004 -t /root/miserver' > start_php_server.sh
chmod +x start_php_server.sh


echo -e '[Unit]\nDescription=PHP Development Server\n[Service]\nExecStart=/root/start_php_server.sh\nRestart=always\nUser=root\n[Install]\nWantedBy=multi-user.target' | sudo tee /etc/systemd/system/php_server.service

sudo yum install git -y

git clone https://github.com/mcedwin/miserver.git
cd miserver/core
composer install

sudo systemctl daemon-reload
sudo systemctl enable php_server.service
sudo systemctl start php_server.service



sudo yum install certbot python2-certbot-apache -y



sudo systemctl restart httpd
sudo systemctl restart sshd
sudo systemctl restart mariadb


sudo nano /etc/my.cnf


sudo nano /etc/php.ini


sudo nano /etc/ssh/sshd_config


sudo certbot --apache


####### dar permiso de escritura al grupo para apache
chown -R piruw:apache /home/piruw/public_html
sudo chmod -R g+w /home/piruw/public_html/


t3a.micro


