<?php


function shell_reset_apache()
{
    shell_exec("apachectl restart");
}
function shell_reset_mysql()
{
    shell_exec("");
}
function shell_reset_ssh()
{
    shell_exec("");
}

function shell_init($user, $password, $domain)
{
    shell_exec("useradd -s /bin/bash {$user}");
    shell_exec("bash -c \"echo -e '{$password}\\n{$password}' | passwd {$user}\"");
    shell_exec("bash -c \"echo -e '{$password}\\n{$password}' | passwd {$user}\"");
    shell_exec("mkdir /home/mcedwin/public_html");
    shell_exec("chmod o+x /home/u217308");
    shell_exec("chown u217308 /home/u217308/public_html");
    shell_exec("echo 'Hola u217308' > /home/u217308/public_html/index.html");

    shell_exec("echo '
ServerName 127.0.0.1

######INI {$user}######
<VirtualHost *:80>
DocumentRoot /home/{$user}/public_html
ServerName {$domain}
<Directory /home/{$user}/public_html/>
Options Indexes FollowSymLinks MultiViews
AllowOverride All
Require all granted
</Directory>
</VirtualHost>
######FIN {$user}######
' >> /etc/apache2/apache2.conf");

    echo shell_exec("mysql -u root -e \"CREATE DATABASE miserver;CREATE USER 'miserver'@'localhost' IDENTIFIED BY 'password';
    GRANT ALL PRIVILEGES ON miserver.* TO 'miserver'@'localhost' WITH GRANT OPTION;
    FLUSH PRIVILEGES;
    INSERT INFO config(domain) VALUES('{$domain}');
    INSERT INFO user(user,password,description,domain,active) VALUES('{$user}','{$password}','Root','','{$domain}');
    \"");
	
	
    shell_exec("apachectl restart");
    shell_exec("usermod -aG sudo {$user}");
}


function shell_user_new($user, $password, $domain)
{
    shell_exec("useradd -s /bin/bash {$user}");
    shell_exec("bash -c \"echo -e '{$password}\\n{$password}' | passwd {$user}\"");
    shell_exec("mkdir /home/{$user}/public_html");
    shell_exec("chmod o+x /home/{$user}");
    shell_exec("chown {$user} /home/{$user}/public_html");
    shell_exec("echo 'Hola {$user}' > /home/{$user}/public_html/index.html");

    shell_exec("echo '
######INI {$user}######
<VirtualHost *:80>
DocumentRoot /home/{$user}/public_html
ServerName {$domain}
<Directory /home/{$user}/public_html/>
Options Indexes FollowSymLinks MultiViews
AllowOverride All
Require all granted
</Directory>
</VirtualHost>
######FIN {$user}######
' >> /etc/apache2/apache2.conf");
}


function shell_user_edit($user, $password)
{
    shell_exec("bash -c \"echo -e '{$password}\\n{$password}' | passwd {$user}\"");
}

function shell_user_delete($user)
{
    shell_exec("userdel {$user}");
    shell_exec("rm -r /home/{$user}");
}

function shell_domain_new($user, $name, $domain, $folder)
{
    shell_exec("mkdir /home/{$user}/{$folder}");
    shell_exec("chmod o+x /home/{$user}");
    shell_exec("chown {$user} /home/{$user}/{$folder}");
    shell_exec("echo '
######INI {$name}######
<VirtualHost *:80>
DocumentRoot /home/{$user}/{$folder}
ServerName {$domain}
<Directory /home/{$user}/{$folder}/>
Options Indexes FollowSymLinks MultiViews
AllowOverride All
Require all granted
</Directory>
</VirtualHost>
######FIN {$name}######
' >> /etc/apache2/apache2.conf");
}

function shell_domain_delete($name)
{
    $cont = @file_get_contents("/etc/apache2/apache2.conf");
    $cont = preg_replace("/######INI {$name}######.+?######FIN {$name}######/s", '', $cont);
    $cont = @file_put_contents("/etc/apache2/apache2.conf", $cont);
}

function shell_db_new($user, $dbname)
{
    shell_exec("mysql -u root -e \"
        CREATE DATABASE {$dbname};
        GRANT ALL PRIVILEGES ON {$dbname}.* TO '{$user}'@'%' WITH GRANT OPTION;
        GRANT ALL PRIVILEGES ON {$dbname}.* TO '{$user}'@'localhost' WITH GRANT OPTION;
        FLUSH PRIVILEGES;
        \"");
}

function shell_db_delete($dbname)
{
    shell_exec("mysql -u root -e 'DROP DATABASE {$dbname};'");
}

function shell_dbuser_new($dbuser,$password)
{
    shell_exec("mysql -u root -e \"
        CREATE USER '{$dbuser}'@'%' IDENTIFIED BY '{$password}';
        CREATE USER '{$dbuser}'@'localhost' IDENTIFIED BY '{$password}';
    \"");
}

function shell_dbuser_edit($dbuser,$password)
{
    shell_exec("mysql -u root -e \"
    ALTER USER '{$dbuser}'@'localhost' IDENTIFIED BY '{$password}';
    ALTER USER '{$dbuser}'@'%' IDENTIFIED BY '{$password}';
    \"");
}

function shell_dbuser_delete($dbuser)
{
    shell_exec("mysql -u root -e \"DROP USER '{$dbuser}@localhost';DROP USER '{$dbuser}@%';\"");
}

function shell_dbrelation_new($dbname,$dbuser)
{
    shell_exec("mysql -u root -e \"
        GRANT ALL PRIVILEGES ON {$dbname}.* TO '{$dbuser}'@'%' WITH GRANT OPTION;
        GRANT ALL PRIVILEGES ON {$dbname}.* TO '{$dbuser}'@'localhost' WITH GRANT OPTION;
        FLUSH PRIVILEGES;
    \"");
}

function shell_dbrelation_delete($dbname,$dbuser)
{
    shell_exec("mysql -u root -e \"
        REVOKE ALL PRIVILEGES ON {$dbname}.* FROM '{$dbuser}'@'%';
        REVOLE ALL PRIVILEGES ON {$dbname}.* FROM '{$dbuser}'@'localhost';
        FLUSH PRIVILEGES;
    \"");
}
