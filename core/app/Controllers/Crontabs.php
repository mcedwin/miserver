<?php

namespace App\Controllers;

use App\Libraries\Ssp;
use App\Models\GeneralModel;

class Crontabs extends BaseController
{


  public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
  {
    parent::initController($request, $response, $logger);
    if (empty($this->user->id)) $response->redirect(base_url('login'));
    helper('server');
  }

  public function index()
  {
    $this->addJs(array("js/crontabs/index.js"));
    $datos['text'] = @file_get_contents("/var/spool/cron/crontabs/".$this->user->user);
    $this->showHeader();
    $this->ShowContent('index', $datos);
    $this->showFooter();
  }

  public function guardar(){
    $cont = $this->request->getPost('texto');
    $cont = @file_put_contents("/var/spool/cron/crontabs/".$this->user->user, $cont);
    shell_exec("sudo chown {$this->user->user}:crontab /var/spool/cron/crontabs/{$this->user->user}");
    shell_exec("sudo chmod 600 /var/spool/cron/crontabs/{$this->user->user}");

    $this->dieMsg(true, '', base_url('crontabs'));
  }
}
