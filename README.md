# Domain
Agregar dominio a la ip
A punored.com IP

Agregar wildcard subdominio
CNAME  *.punored.com IP

# install server in ubuntu
apt update
apt install apache2
apt install mysql-server
apt install php libapache2-mod-php php-mysql
apt-get install -y php8.1-cli php8.1-common php8.1-mysql php8.1-zip php8.1-gd php8.1-mbstring php8.1-curl php8.1-xml php8.1-bcmath php8.1-intl

# install composer

sudo apt-get install curl unzip
sudo apt-get install php php-curl
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer self-update 

# server
git clone
cd server/core
composer install
cd server
php -S 0.0.0.0:8004

# install cerbot
sudo apt install certbot python3-certbot-apache

# serstar services
apachectl restart
service ssh restart
systemctl restart mysql

![Screenshot](res/01users.png)

# MYSQL
Configurar bind 0.0.0.0
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# PHP display_errors = on
sudo nano /etc/php/8.1/apache2/php.ini

# poner en PasswordAuthentication yes, en caso que sea por llaves.ppk
sudo nano /etc/ssh/sshd_config

# Reinciar todo
sudo apachectl restart
sudo service ssh restart
sudo systemctl restart mysql

# Para denegar acceso a home, para probar
https://unix.stackexchange.com/questions/85537/how-to-hide-someone-elses-directories-from-a-user

# actualizar certificados
sudo certbot --apache
