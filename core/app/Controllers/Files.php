<?php

namespace App\Controllers;

class Files extends BaseController
{
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        if (empty($this->user->id)) $response->redirect(base_url('login'));
        helper('server');
    }

    public function index()
    {
        if (isset($_GET['do'])) {
            if ($_GET['do'] == 'edit') {
                $chdir = "/home/{$this->user->user}/";
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $chdir = "./";
                }
                chdir($chdir);
                $datos['titulo'] = 'Editar archivo';
                $datos['file'] = $file = $_GET['file'];
                $datos['text'] = file_get_contents($file);
                $this->showContent('form', $datos);
                exit(0);
            }
        }
        $this->addJs(array('js/files/lista.js'));
        if (isset($_GET['do']) || isset($_POST['do'])) $this->showContent('index');
        $this->showHeader();
        $this->showContent('index');
        $this->showFooter();
    }

    public function guardar()
    {
        $chdir = "/home/{$this->user->user}/";
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $chdir = "./";
        }
        chdir($chdir);
        $file = $this->request->getPost('file');
        $text = $this->request->getPost('text');
        file_put_contents($file, $text);
        $this->dieMsg(true);
    }
}
