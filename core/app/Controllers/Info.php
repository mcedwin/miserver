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

    $str = 'Filesystem      Size  Used Avail Use% Mounted on
tmpfs            97M  1.1M   96M   2% /run
/dev/vda1        24G  6.4G   17G  28% /
tmpfs           481M     0  481M   0% /dev/shm
tmpfs           5.0M     0  5.0M   0% /run/lock
/dev/vda16      881M  112M  708M  14% /boot
/dev/vda15      105M  6.1M   99M   6% /boot/efi
tmpfs            97M   12K   97M   1% /run/user/0
tmpfs            97M   12K   97M   1% /run/user/1000';
    $datos['datos'] = explode("\n", preg_replace('#[ ]+#', "\t", $str));

    $str = shell_exec('du -h --max-depth=1 /home');
    $str = '472M    /home/punored
91M     /home/regino
32K     /home/piruw
2.8G    /home/perulist
3.4G    /home';
    $datos['homes'] = explode("\n", preg_replace('#[ ]+#', "\t", $str));


    $rows  = $this->db->query('SELECT * FROM db_shema')->getResult();
    $infos = [];
    foreach ($rows as $row) {
      $sql = "SELECT table_schema AS database_name,
               ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.tables
        WHERE table_schema = '" . $row->name . "'
        GROUP BY table_schema";
      $info = $this->db->query($sql)->getRow();
      $infos[] = ['data'=>$row->name,'size'=>$info->size_mb];
    }

    $datos['infos']  = $infos;

    $this->showHeader();
    $this->ShowContent('index', $datos);
    $this->showFooter();
  }
}
