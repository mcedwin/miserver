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
        if(isset($_GET['do'])||isset($_POST['do'])) $this->showContent('index'); 
        $this->showHeader();
        $this->showContent('index');
        $this->showFooter();
    }
}
