<?php

namespace App\Controllers;



class Info extends BaseController
{


  public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
  {
    parent::initController($request, $response, $logger);
    if (empty($this->user->id)) $response->redirect(base_url('login'));
    helper('server');
  }

  public function index()
  {


    $str = shell_exec('df -h');

    /* $str = 'Filesystem      Size  Used Avail Use% Mounted on
tmpfs            97M  1.1M   96M   2% /run
/dev/vda1        24G  6.4G   17G  28% /
tmpfs           481M     0  481M   0% /dev/shm
tmpfs           5.0M     0  5.0M   0% /run/lock
/dev/vda16      881M  112M  708M  14% /boot
/dev/vda15      105M  6.1M   99M   6% /boot/efi
tmpfs            97M   12K   97M   1% /run/user/0
tmpfs            97M   12K   97M   1% /run/user/1000';*/

    $datos['datos'] = explode("\n", preg_replace('#[ ]+#', "\t", $str));



    $str = shell_exec('ls -all /backups');
//     $str = 'total 3353652
// drwxr-xr-x  2 root root       4096 Jul  7 16:45 .
// drwxr-xr-x 23 root root       4096 Jul  7 16:16 ..
// -rw-r--r--  1 root root 1712839317 Jul  7 16:41 backups-backup-2024-07-07.tar.gz
// -rw-r--r--  1 root root       1569 Jul  7 16:45 miserver-backup-2024-07-07.sql.gz
// -rw-r--r--  1 root root 1468481685 Jul  7 16:43 perulist-backup-2024-07-07.tar.gz
// -rw-r--r--  1 root root    3385268 Jul  7 16:45 perulist_web-backup-2024-07-07.sql.gz
// -rw-r--r--  1 root root    3862848 Jul  7 16:43 piruw-backup-2024-07-07.tar.gz
// -rw-r--r--  1 root root  212554514 Jul  7 16:43 punored-backup-2024-07-07.tar.gz
// -rw-r--r--  1 root root     474909 Jul  7 16:45 punored_web-backup-2024-07-07.sql.gz
// -rw-r--r--  1 root root   32497660 Jul  7 16:43 regino-backup-2024-07-07.tar.gz
// -rw-r--r--  1 root root       2489 Jul  7 16:45 regino_web-backup-2024-07-07.sql.gz
// ';
    $datos['backups'] = explode("\n", preg_replace('#[ ]+#', "\t", $str));

    //die(print_r($datos['datos']));
    $str = shell_exec('du -h --max-depth=1 /home');
    /* $str = '472M    /home/punored
91M     /home/regino
32K     /home/piruw
2.8G    /home/perulist
3.4G    /home';*/
    $datos['homes'] = explode("\n", preg_replace('#[ ]+#', "\t", $str));


    // $rows  = $this->db->query('SELECT * FROM db_shema')->getResult();
    // $infos = [];
    //foreach ($rows as $row) {
    // $sql = "SELECT table_schema AS database_name,
    //          ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
    //   FROM information_schema.tables
    //   WHERE 1
    //   GROUP BY table_schema";
    // $infos = $this->db->query($sql)->getResult();
    //$infos[] = ['data'=>$row->database_name,'size'=>$info->size_mb];
    // }

    $sql = 'mysql -e "SELECT table_schema AS database_name,
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables
        WHERE 1
        GROUP BY table_schema"';
    $str = shell_exec($sql);

    $str = str_replace(['|', '-', '+'], '', $str);
    $datos['info'] = explode("\n", preg_replace('#[ ]+#', "\t", $str));
    //die(print_r($datos['info']));
    //  $datos['infos']  = $infos;

    $this->showHeader();
    $this->ShowContent('index', $datos);
    $this->showFooter();
  }

  function download()
  {
    $archivo= $_GET['file'];
    $archivo = '/backups/'.$archivo;
    if (file_exists($archivo)) {
      // Define las cabeceras para forzar la descarga
      // header('Content-Description: File Transfer');
      header('Content-Type: application/octet-stream');
      header('Content-Disposition: attachment; filename="' . basename($archivo) . '"');
      header('Expires: 0');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');
      header('Content-Length: ' . filesize($archivo));
      flush(); // Limpia el búfer del sistema
      readfile($archivo); // Lee el archivo y lo envía al navegador
      exit;
    } else {
      echo 'El archivo no existe.';
    }
  }
}
