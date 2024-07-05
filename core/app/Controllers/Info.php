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

    $comando = 'ls -l';

    // Ejecutar el comando y capturar la salida
    $resultado = shell_exec($comando);

    // Mostrar la salida
    echo "<pre>$resultado</pre>";

   /* $this->showHeader();
    $this->ShowContent('index', $datos);
    $this->showFooter();*/
  }
}
