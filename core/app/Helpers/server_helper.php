<?php


function shell_reset_apache()
{
  shell_exec("apachectl restart");
}
function shell_reset_mysql()
{
  shell_exec("systemctl restart mysql");
}
function shell_reset_ssh()
{
  shell_exec("service ssh restart");
}

function shell_init($user, $password, $domain, $token)
{
  shell_exec("useradd -m -s /bin/bash {$user}");
  shell_exec("bash -c \"echo -e '{$password}\\n{$password}' | passwd {$user}\"");
  shell_exec("mkdir /home/{$user}/public_html");
  shell_exec("chmod o+x /home/{$user}");
  
  // shell_exec("chown {$user} /home/{$user}/public_html");
  // shell_exec("echo 'Hola {$user}' > /home/{$user}/public_html/index.html");
  // shell_exec("chown -R {$user}:www-data /home/{$user}/public_html");
  // shell_exec("sudo chmod -R g+w /home/{$user}/public_html/");

shell_exec("echo '
ServerName 127.0.0.1
' >> /etc/apache2/apache2.conf");

  newwebfolder($user,$user,'public_html',$domain);

  
//   shell_exec("echo '
// ServerName 127.0.0.1

// ######INI {$user}######
// <VirtualHost *:80>
// DocumentRoot /home/{$user}/public_html
// ServerName {$domain}
// <Directory /home/{$user}/public_html/>
// Options Indexes FollowSymLinks MultiViews
// AllowOverride All
// Require all granted
// </Directory>
// </VirtualHost>
// ######FIN {$user}######
// ' >>  /etc/httpd/conf/httpd.conf");

  shell_exec("mysql -u root -e \"
    CREATE USER '{$user}'@'%' IDENTIFIED BY '{$password}';
    CREATE USER '{$user}'@'localhost' IDENTIFIED BY '{$password}';
    \"");

  echo shell_exec("mysql -u root -e \"CREATE DATABASE miserver;CREATE USER 'miserver'@'localhost' IDENTIFIED BY 'password';
    GRANT ALL PRIVILEGES ON miserver.* TO 'miserver'@'localhost';
    GRANT ALL PRIVILEGES ON *.* TO '{$user}'@'localhost';
    GRANT ALL PRIVILEGES ON *.* TO '{$user}'@'%';
    FLUSH PRIVILEGES;
    \"");

  echo shell_exec("mysql -u root miserver < res/miserver.sql");

  echo shell_exec("mysql -u root -e \"USE miserver;
    INSERT INTO config(id,domain,token) VALUES('1','{$domain}','{$token}');
    INSERT INTO user(id,user,password,description,domain,active) VALUES(1,'{$user}','{$password}','Root','{$domain}','1');
    \"");

  shell_reset_apache();
  shell_exec("usermod -aG sudo {$user}");
}

function newwebfolder($user,$name,$folder,$domain)
{
  shell_exec("sudo -u {$user} mkdir /home/{$user}/{$folder}");
  shell_exec("sudo -u {$user} chmod 755 /home/{$user}/{$folder}");
  shell_exec("echo 'Hola {$user}' | sudo -u {$user} tee /home/{$user}/{$folder}/index.html >/dev/null");
  shell_exec("sudo -u {$user} umask 022");
  //shell_exec("chown -R {$user}:www-data /home/{$user}/{$folder}");
  shell_exec("sudo chmod -R g+w /home/{$user}/{$folder}/");
  //shell_exec("sudo chmod g+s /home/{$user}/{$folder}/");

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
SuexecUserGroup {$user} {$user}
</VirtualHost>
######FIN {$name}######
' >> /etc/apache2/apache2.conf");

}


function shell_user_new($user, $password, $domain, $token)
{
  shell_exec("useradd -m -s /bin/bash {$user}");
  shell_exec("bash -c \"echo -e '{$password}\\n{$password}' | passwd {$user}\"");
  shell_exec("chmod o+x /home/{$user}");
  shell_exec("usermod -a -G www-data {$user}");
  newwebfolder($user,$user,'public_html',$domain);


  
  //   shell_exec("echo '
  // ######INI {$user}######
  // <VirtualHost *:80>
  // DocumentRoot /home/{$user}/public_html
  // ServerName {$domain}
  // <Directory /home/{$user}/public_html/>
  // Options Indexes FollowSymLinks MultiViews
  // AllowOverride All
  // Require all granted
  // </Directory>
  // </VirtualHost>
  // ######FIN {$user}######
  // ' >> /etc/httpd/conf/httpd.conf");


  $ipAddress = file_get_contents('https://api.ipify.org');
  curl_adddomain($token, $domain, $ipAddress);

  shell_exec("mysql -u root -e \"
        CREATE USER '{$user}'@'%' IDENTIFIED BY '{$password}';
        CREATE USER '{$user}'@'localhost' IDENTIFIED BY '{$password}';
    \"");
}

function curl_adddomain($apiToken, $domainName, $ipAddress)
{

  // $apiToken = "TU_TOKEN_API";
  // $domainName = "ejemplo.com";  // Nombre del dominio que deseas agregar
  // $ipAddress = "192.168.1.1";  // Dirección IP asociada con el dominio

  $ch = curl_init();

  curl_setopt($ch, CURLOPT_URL, "https://api.digitalocean.com/v2/domains");
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiToken",
    "Content-Type: application/json"
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "name" => $domainName,
    "ip_address" => $ipAddress
  ]));

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  curl_close($ch);

  if ($httpCode == 201) {
    // echo "El dominio $domainName ha sido agregado correctamente.";
  } else {
    // echo "Hubo un problema al agregar el dominio $domainName. Código HTTP: $httpCode\n";
    // echo "Respuesta de la API: $response";
  }
}

function curl_removedomain($domain, $apiToken)
{



  $ch = curl_init();

  curl_setopt($ch, CURLOPT_URL, "https://api.digitalocean.com/v2/domains/$domain");
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiToken"
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  curl_close($ch);

  if ($httpCode == 204) {
    //echo "El dominio $domain ha sido eliminado correctamente.";
  } else {
    // echo "Hubo un problema al eliminar el dominio $domain. Código HTTP: $httpCode";
  }
}


function shell_user_edit($user, $password)
{
  shell_exec("bash -c \"echo -e '{$password}\\n{$password}' | passwd {$user}\"");
  shell_exec("mysql -u root -e \"
    ALTER USER '{$user}'@'localhost' IDENTIFIED BY '{$password}';
    ALTER USER '{$user}'@'%' IDENTIFIED BY '{$password}';
    \"");
}

function shell_user_delete($user, $domain, $token)
{
  shell_exec("userdel {$user}");
  shell_exec("rm -r /home/{$user}");

  shell_exec("mysql -u root -e \"DROP USER '{$user}'@'localhost';DROP USER '{$user}'@'%';\"");

  curl_removedomain($domain, $token);

  shell_exec("mysql -u root -e 'DROP DATABASE {$user};'");
}

function shell_domain_new($user, $name, $domain, $folder, $token)
{
  shell_exec("mkdir /home/{$user}/{$folder}");
  shell_exec("chmod o+x /home/{$user}");
  
  newwebfolder($user,$name,$folder,$domain);


//   shell_exec("echo '
// ######INI {$name}######
// <VirtualHost *:80>
// DocumentRoot /home/{$user}/{$folder}
// ServerName {$domain}
// <Directory /home/{$user}/{$folder}/>
// Options Indexes FollowSymLinks MultiViews
// AllowOverride All
// Require all granted
// </Directory>
// </VirtualHost>
// ######FIN {$name}######
// ' >> /etc/apache2/apache2.conf");

  //   shell_exec("echo '
  // ######INI {$name}######
  // <VirtualHost *:80>
  // DocumentRoot /home/{$user}/{$folder}
  // ServerName {$domain}
  // <Directory /home/{$user}/{$folder}/>
  // Options Indexes FollowSymLinks MultiViews
  // AllowOverride All
  // Require all granted
  // </Directory>
  // </VirtualHost>
  // ######FIN {$name}######
  // ' >> /etc/httpd/conf/httpd.conf");

  $ipAddress = file_get_contents('https://api.ipify.org');
  curl_adddomain($token, $domain, $ipAddress);
}

function shell_domain_delete($name, $domain, $token)
{
  $cont = @file_get_contents("/etc/apache2/apache2.conf");
  $cont = preg_replace("/######INI {$name}######.+?######FIN {$name}######/s", '', $cont);
  curl_removedomain($domain, $token);
  $cont = @file_put_contents("/etc/apache2/apache2.conf", $cont);

  // $cont = @file_get_contents("/etc/httpd/conf/httpd.conf");
  // $cont = preg_replace("/######INI {$name}######.+?######FIN {$name}######/s", '', $cont);
  // curl_removedomain($domain, $token);
  // $cont = @file_put_contents("/etc/httpd/conf/httpd.conf", $cont);
}

function shell_db_new($user, $dbname)
{
  shell_exec("mysql -u root -e \"
        CREATE DATABASE {$dbname};
        GRANT ALL PRIVILEGES ON {$dbname}.* TO '{$user}'@'%';
        GRANT ALL PRIVILEGES ON {$dbname}.* TO '{$user}'@'localhost';
        FLUSH PRIVILEGES;
        \"");
}

function shell_db_delete($dbname)
{
  shell_exec("mysql -u root -e 'DROP DATABASE {$dbname};'");
}

function shell_dbuser_new($dbuser, $password)
{
  shell_exec("mysql -u root -e \"
        CREATE USER '{$dbuser}'@'%' IDENTIFIED BY '{$password}';
        CREATE USER '{$dbuser}'@'localhost' IDENTIFIED BY '{$password}';
    \"");
}

function shell_dbuser_edit($dbuser, $password)
{
  shell_exec("mysql -u root -e \"
    ALTER USER '{$dbuser}'@'localhost' IDENTIFIED BY '{$password}';
    ALTER USER '{$dbuser}'@'%' IDENTIFIED BY '{$password}';
    \"");
}

function shell_dbuser_delete($dbuser)
{
  shell_exec("mysql -u root -e \"DROP USER '{$dbuser}'@'localhost';DROP USER '{$dbuser}'@'%';\"");
}

function shell_dbrelation_new($dbname, $dbuser)
{
  shell_exec("mysql -u root -e \"
        GRANT ALL PRIVILEGES ON {$dbname}.* TO '{$dbuser}'@'%' ;
        GRANT ALL PRIVILEGES ON {$dbname}.* TO '{$dbuser}'@'localhost';
        FLUSH PRIVILEGES;
    \"");
}

function shell_dbrelation_delete($dbname, $dbuser)
{

  /*die("mysql -u root -e \"
        REVOKE ALL PRIVILEGES ON {$dbname}.* FROM '{$dbuser}'@'%';
        REVOKE ALL PRIVILEGES ON {$dbname}.* FROM '{$dbuser}'@'localhost';
        FLUSH PRIVILEGES;
    \"");*/

  shell_exec("mysql -u root -e \"
        REVOKE ALL PRIVILEGES ON {$dbname}.* FROM '{$dbuser}'@'%';
        REVOKE ALL PRIVILEGES ON {$dbname}.* FROM '{$dbuser}'@'localhost';
        FLUSH PRIVILEGES;
    \"");
}
