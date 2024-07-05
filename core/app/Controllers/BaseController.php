<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */

    protected $helpers = ['form'];
    public $csss = [];
    public $jss = [];
    public $frontVersion = 1;
    public $user;
    public $usizes;
    public $esizes;
    public $meta;
    public $title;
    public $controller;
    public $db;
    public $mc_scripts;
    protected $datos = [];



    protected $rulesMessages = [
        'valid_email' => 'El correo no tiene un formato correcto',
        'required' => 'Campo {field} es obligatorio',
        'exact_length' => 'El valor de {field} debe tener {param} caracteres',
        'min_length' => 'El valor de {field} debe tener más de {param} caracteres',
        'max_length' => 'El valor de {field} debe tener menos de {param} caracteres',
        'numeric' => 'Campo {field} solo permite números',
        'integer' => 'Campo {field} solo permite números',
        'is_unique' => 'El {field} ya existe',
    ];


    /**
     * Constructor.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {

        $session = session();
        $this->user = (object)[
            'id' => $session->get('id'),
            'user' => $session->get('user'),
            'name' => $session->get('name'),
            'type' => $session->get('type'),
        ];

        $this->title = 'Mi Server';
        $this->meta = (object) array(
            'title' => $this->title,
            'description' => 'Aviso Paginas Peruanas Webs.',
            'image' => '',
            'url' => current_url(),
            'site_name' => 'Mi Server',
        );
        

        if (file_exists("./core/.env")) {
            

            $this->db = db_connect();


            $row = $this->db->query("SELECT domain FROM config WHERE id='1'")->getRow();

            /*if (!empty($this->user->id)) {
                $this->user->name = $row->domain;
            }*/


            $this->datos['user'] = $this->user;

            $this->controller = class_basename(service('router')->controllerName());
            $this->datos['controller'] = $this->controller;

            
        }
        parent::initController($request, $response, $logger);
    }

    public function validar($fields)
    {
        $validation =  \Config\Services::validation();
        $data = array();
        foreach ($this->request->getPost() as $key => $val) {
            if (!isset($fields[$key])) {
                continue;
            }
            if ($fields[$key]->type == 'select') $fields[$key]->type = 'int';
            if ($fields[$key]->type == 'hidden') $fields[$key]->type = 'text';
            if ($fields[$key]->type == 'password') $fields[$key]->type = 'text';
            if ($fields[$key]->required == true) {
                if (!empty($fields[$key]->valid)) {

                    $rule_valid =  $this->setRuleValidate($fields[$key]);
                    if (!$this->validate([$fields[$key]->name => $rule_valid])) {
                        $errors = $validation->getErrors();
                        $this->dieMsg(false, $errors[$fields[$key]->name]);
                    }
                }
                if (is_array($val)) {
                    if (count($val) <= 0) $this->dieMsg(false, "Campo requerido : " . $fields[$key]->label);
                } else if ($fields[$key]->type != 'bit' && strlen($val) <= 0) $this->dieMsg(false, "Campo requerido : " . $fields[$key]->label);
            }
            if (in_array($fields[$key]->type, array('text', 'varchar', 'url', 'email', 'fore', 'decimal', 'int', 'enum'))) {
                $data[$key] = $this->request->getPost($key);
                if ($fields[$key]->type == 'int' && empty($val)) $data[$key] = null;
            } else if ($fields[$key]->type == 'date') {
                $data[$key] = ($val);
            } else if ($fields[$key]->type == 'bit') {
                $data[$key] = $this->request->getPost($key) == '1' ? 1 : 0;
            }
        }
        return $data;
    }

    private  function setRuleValidate($field)
    {
        $rule_temp = [
            'label'  => $field->label,
            'rules'  => $field->valid,
            'errors' => []
        ];
        $array_rules = explode('|', $field->valid);
        foreach ($this->rulesMessages as $rule =>  $message) {
            foreach ($array_rules as $elem) {
                $elem =  explode('[', $elem);
                if ($elem[0] == $rule) {
                    $message = str_replace('{field}', $field->label, $message);
                    $message = str_replace('{param}', count($elem) > 1 ? substr($elem[1], 0, -1) : '', $message);
                    $rule_temp['errors'][$rule] = $message;
                }
            }
        }
        return  $rule_temp;
    }

    public function getDataConn()
    {
        return array(
            'user' => $this->db->username,
            'pass' => $this->db->password,
            'db' => $this->db->database,
            'host' => $this->db->hostname
        );
    }

    public function guardar_imagen($folder, $name)
    {
        if (empty($_FILES['foto']['name'])) {
            return false;
        }

        $validationRule = [
            'foto' => [
                'label' => 'Image File',
                'rules' => 'uploaded[foto]'
                    . '|is_image[foto]'
                    . '|mime_in[foto,image/jpg,image/jpeg,image/gif,image/png,image/webp]'
                    . '|max_size[foto,1000]'
                //. '|max_dims[foto,2024,2768]',
            ],
        ];

        if (!$this->validate($validationRule)) {
            $data = $this->validator->getErrors();
            $this->dieMsg(false, implode('\n', $data));
        }

        $img = $this->request->getFile('foto');

        if (!$img->hasMoved()) {
            $this->resize_image(APPPATH . '../../' . $folder, WRITEPATH . 'uploads/' . $img->store(), $name);
        } else {
            $this->dieMsg(false, 'Archivo movido');
        }

        return true;
    }


    public function guardar_archivo($folder, $name, $path)
    {
        if (empty($_FILES[$name]['name'])) {
            return false;
        }

        $validationRule = [
            $name => [
                'label' => 'Image File',
                'rules' => 'uploaded[' . $name . ']'
                    . '|mime_in[' . $name . ',image/jpg,image/jpeg,image/gif,image/png,image/webp,application/pdf,application/msword]'
                    . '|max_size[' . $name . ',10000]'
            ],
        ];

        if (!$this->validate($validationRule)) {
            $data = $this->validator->getErrors();
            $this->dieMsg(false, implode('\n', $data));
        }

        $img = $this->request->getFile($name);

        if ($img->isValid() && !$img->hasMoved()) {
            $img->move(APPPATH . '../../' . $folder, $path);
        } else {
            $this->dieMsg(false, 'Archivo movido');
        }
        return true;
    }

    function get_image($folder, $fname, $size)
    {
        return base_url('uploads/' . $folder . '/' . str_replace('normal', $size, $fname));
    }

    function resize_image($folder, $full_path, $fname)
    {
        $result = true;
        $sizes = $this->esizes;
        if (preg_match('/usuario/', $folder)) $sizes = $this->usizes;

        foreach ($sizes as $size) {
            if ($size->sufijo == 'full') {
                copy($full_path, $folder . '/' . str_replace('full', $size->sufijo, $fname));
            } else {
                $image = \Config\Services::image()
                    ->withFile($full_path)
                    ->resize($size->ancho, $size->alto, true, 'height')
                    ->save($folder . '/' . str_replace('normal', $size->sufijo, $fname));
            }
        }

        return $result;
    }

    function borrar_imagen($folder, $name)
    {
        if (empty($name)) return;
        $sizes = $this->esizes;
        if (preg_match('/usuario/', $folder)) $sizes = $this->usizes;

        foreach ($sizes as $size) {
            unlink(APPPATH . '../../' . $folder . '/' . str_replace('normal', $size->sufijo, $name));
        }
    }

    public function dieAjax()
    {
        if (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') == 0
        ) {
            return true;
        }
        $this->dieMsg(false, "No es ajax.");
    }

    public function diePermiso($user)
    {

        if (is_null($user) || empty($user)) {

            if ($this->isAjax()) {
                $this->dieMsg(true, "user", base_url('login'));
            } else {
                return redirect()->to('/login');
            }
        }
        return false;
    }

    public function isAjax()
    {
        if (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') == 0
        ) {
            return true;
        }
        return false;
    }

    public function addJs($jss)
    {
        if (is_array($jss)) $this->jss = $jss;
        else $this->jss[] = $jss;
    }
    public function addCss($csss)
    {
        if (is_array($csss)) $this->csss = $csss;
        else $this->csss[] = $csss;
    }

    public function showHeader($conmenu = true)
    {
        $strcss = '';
        $strjs = '';

        $this->datos['menu_top'] = [];


        if ($this->user->id == '1') {
            $this->datos['menu_top'] = [
                ['url' => 'users', 'base' => 'users', 'name' => 'Usuarios', 'ico' => 'fa-regular fa-folder-open'],
                ['url' => 'files', 'base' => 'files', 'name' => 'Archivos', 'ico' => 'fa-solid fa-address-book'],
                ['url' => 'databases', 'base' => 'databases', 'name' => 'Bases de Datos', 'ico' => 'fa-solid fa-share-from-square'],
                ['url' => 'crontabs', 'base' => 'crontabs', 'name' => 'CronTab', 'ico' => 'fa-regular fa-clock'],
                ['url' => 'domains', 'base' => 'domains', 'name' => 'Dominios', 'ico' => 'fa-solid fa-address-book'],
            ];
        } else {
            $this->datos['menu_top'] = [
                ['url' => 'files', 'base' => 'files', 'name' => 'Archivos', 'ico' => 'fa-solid fa-address-book'],
                ['url' => 'databases', 'base' => 'databases', 'name' => 'Bases de Datos', 'ico' => 'fa-solid fa-share-from-square'],
                ['url' => 'crontabs', 'base' => 'crontabs', 'name' => 'CronTab', 'ico' => 'fa-regular fa-clock'],
            ];
        }

        $this->datos['conmenu'] = $conmenu;

        foreach ($this->csss as $css) {
            $strcss .= '<link href="' . ((preg_match('#^htt#', $css) == TRUE) ? '' : base_url('core/assets') . '/') . $css . '?v=' . $this->frontVersion . '" rel="stylesheet" type="text/css" media="all" />';
        }
        foreach ($this->jss as $js) {
            $strjs .= '<script type="text/javascript" src="' . ((preg_match('#^htt#', $js) == TRUE) ? '' : base_url('core/assets') . '/') . $js . '?v=' . $this->frontVersion . '"></script>';
        }

        $this->mc_scripts['js'] = $strjs;
        $this->mc_scripts['css'] = $strcss;

        if ($this->title != $this->meta->title) $this->meta->title = $this->meta->title . ' | ' . $this->meta->site_name;
        $this->mc_scripts['meta'] = $this->meta;
        echo view('templates/header', $this->mc_scripts);
        echo view('templates/menu', $this->datos);
    }

    public function getMail()
    {
        $conf = $this->db->query("SELECT * FROM config")->getRow();

        $email = \Config\Services::email();

        $config['protocol'] = 'smtp';
        $config['SMTPHost'] = $conf->conf_mail_host;
        $config['SMTPUser']  = $conf->conf_mail_user;
        $config['SMTPPass'] = $conf->conf_mail_pass;
        $config['SMTPPort'] = $conf->conf_mail_port;
        $config['SMTPCrypto'] = $conf->conf_mail_crypto;
        $config['SMTPTimeout'] = '60';
        $config['mailType'] = 'html';


        $email->initialize($config);
        $email->setFrom($conf->conf_mail_reply, $conf->conf_mail_nreply);
        return $email;
    }

    public function traducir($mensaje, $add = [])
    {
        $list = (array)$this->user;
        $list = array_merge($list, $add);

        foreach ($list as $id => $item) {

            if ($item == null) $item = '';

            $mensaje = str_replace('{' . $id . '}', $item, $mensaje);
        }
        return $mensaje;
    }

    public function showContent($path, $response = [])
    {
        $router = service('router');
        $controller  = preg_replace("#.App.Controllers.#", '', $router->controllerName());
        echo view(strtolower($controller) . '/' . $path, array_merge($this->datos, $response));
    }
    public function showFooter($conmenu = true)
    {
        $datos['conmenu'] = $conmenu;
        echo view('templates/footer', $datos);
    }

    public function dieMsg($ret = true, $msg = "", $redirect = "")
    {
        if ($ret == false) {
            $this->response->setStatusCode(500, $msg);
            $this->response->send();
            exit(0);
        }
        $resp = ['exito' => $ret, 'mensaje' => $msg, 'redirect' => $redirect];
        $this->response->setJSON($resp);
        $this->response->send();
        exit(0);
    }
}
