<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (file_exists("./core/.env"))
            return redirect()->to('files');

        $this->addJs(array('js/home/form.js'));

        $this->showHeader(false);
        $this->showContent('login');
        $this->showFooter();
    }

    public function crear()
    {

        $domain = $this->request->getPost('domain');
        $user = $this->request->getPost('user');
        $password = $this->request->getPost('password');

        helper('server');
        shell_init($user, $password, $domain);

        $file = "./core/.env";
        if (!file_exists("./core/.env")) {
            $cont = file_get_contents($file . '.example');
            $cont = str_replace('# database.default', 'database.default', $cont);
            $cont = str_replace('database.default.database = ci4', "database.default.database = miserver", $cont);
            $cont = str_replace('database.default.username = root', "database.default.username = miserver", $cont);
            $cont = str_replace('database.default.password = root', "database.default.password = password", $cont);
            file_put_contents($file, $cont);
        }

        $this->dieMsg(true, '', base_url('/'));
    }
}
