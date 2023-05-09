<?php

namespace App\Controllers;

use App\Libraries\Ssp;
use App\Models\GeneralModel;

class Login extends BaseController
{
    protected $model;

    public function __construct()
    {
        $this->model = new GeneralModel('user');
    }

    function index()
    {
        helper("formulario");

        $this->addJs(array('js/login/form.js'));

        $datos['fields'] = $this->model->geti();
        $this->showHeader(false);
        $this->showContent('login', $datos);
        $this->showFooter();
    }

    public function ingresar()
    {
        $usuario  = $this->request->getPost("user");
        $password = $this->request->getPost("password");
        $ip = $this->request->getIPAddress();

        $sql = "SELECT * FROM user WHERE user='{$usuario}' AND active=1 AND password='{$password}' LIMIT 1";
        $result = $this->db->query($sql);
        

        $session = session();
        if ($result->getNumRows()) {
            $row = $result->getRow();
            $sesdata = array(
                'id'  => $row->id,
                'user'  => $row->user,
                'name'  => $row->description,
                'auth'     => true
            );
            $session->set($sesdata);
            $sql = "UPDATE user SET lastip='{$ip}' WHERE id='{$row->id}'";
            $this->db->query($sql);
        } else {
            $this->dieMsg(false, "Error al ingresar sus datos ó no a confirmado su cuenta");
        }
        $this->dieMsg(true, '', base_url('/'));
    }

    function salir()
    {
        $session = session();
        $session->destroy();
        return redirect()->to('/');
    }

    function registrar(){
        
    }

    public function procregistrar()
    {

        $this->validar($this->model->getFields());


        $nombres  = ($this->request->getPost("usua_nombres"));
        $email  = ($this->request->getPost("usua_email"));
        $password = ($this->request->getPost("usua_password"));

        //$tipo_doc = ($this->request->getPost("usua_tipo_doc"));
        $num_doc = ($this->request->getPost("usua_dniruc"));
        //$apellidos = ($this->request->getPost("usua_apellidos"));
        $telefono = ($this->request->getPost("usua_telefono"));

        $email = trim($email);

        if (strlen($email) <= 3) $this->dieMsg(false, "Correo incorrecto");
        $password = ($this->request->getPost("usua_password"));
        $password2 = md5(rand(999999999, 9999999999));
        $row = $this->db->query("SELECT * FROM usuario WHERE usua_email='{$email}'")->getRow();

        if ($row) {
            $this->dieMsg(false, 'Usted ya esta registrado');
        }
        $datos = array(
            'usua_nombres' => $nombres,
            'usua_email' => $email,
            'usua_tipo_id' => '3',
            'usua_password' => md5($password),
            'usua_password2' => $password2,
            'usua_activo' => '0',

            'usua_dniruc' => $num_doc,
            'usua_telefono' => $telefono,
        );
        $this->db->transStart();
        $this->db->table('usuario')->insert($datos);
        // $this->sendpregistro($nombres, $email, $password2);
        $this->db->transComplete();

        $this->dieMsg();
    }

    function proc_cambiar($password2)
    {
        $email = $this->request->getPost("email");
        $password = $this->request->getPost("password");
        $ip = $this->request->getIPAddress();

        $sql = "SELECT usua_id as id,usua_email as email,usua_tipo_id as type FROM usuario WHERE 
        usua_email='{$email}' AND !(usua_password2 IS NULL) AND !(usua_password2='') 
        AND  usua_password2='{$password2}' LIMIT 1";

        $row = $this->db->query($sql)->getRow();
        if ($row) {
            $sql = "UPDATE usuario SET usua_lastip='{$ip}',usua_password=md5('{$password}'),usua_password2=NULL WHERE usua_id='{$row->id}'";
            $this->db->query($sql);
        } else {
            $this->dieMsg(false, "Error al ingresar sus datos");
        }
        $this->dieMsg(true, '', base_url(''));
    }


    function cambiar($email, $password2)
    {
        $this->meta->title = "Cambiar contraseña";
        $this->addJs(array("js/login/form.js"));
        $datos['password2'] = $password2;
        $datos['email'] = urldecode($email);
        $this->showHeader(true);
        $this->showContent('cambiar', $datos);
        $this->showFooter();
    }

    function confirmar($email, $password2)
    {
        $email = urldecode($email);
        $this->db->query("UPDATE usuario SET usua_password2=NULL, usua_activo=1 WHERE usua_email='{$email}' AND usua_password2='{$password2}'");
        return redirect()->to('/');
    }

    function recuperar()
    {
        $this->ShowContent('recuperar');
    }

    public function proc_recuperar()
    {
        $email = $this->request->getPost('email');
        $row = $this->db->query("SELECT * FROM usuario WHERE usua_email='{$email}'")->getRow();
        if ($row) {
            $this->sendpassword($row);
        } else {
            $this->dieMsg(false, "Email no encontrado.");
        }
        $this->dieMsg();
    }

    public function sendpassword($row)
    {
        $plan = $this->db->query("SELECT * FROM config_plantilla WHERE plan_id=4")->getRow();

        $passwordplain  = rand(999999999, 9999999999);
        $newpass = md5($passwordplain);

        $this->db->query("UPDATE usuario SET usua_password2='{$newpass}' WHERE usua_email='{$row->usua_email}'");

        helper('formulario');
        $asunto = $this->traducir($plan->plan_asunto);
        $cuerpo = wpautop($this->traducir($plan->plan_cuerpo, ['BOTON' => base_url('login/cambiar/' . urlencode($row->usua_email) . '/' . $newpass)]));

        $em = $this->getMail();
        $em->setTo($row->usua_email);
        $em->setSubject($asunto);
        $em->setMessage($cuerpo);

        if ($em->send(FALSE)) {
            //$this->dieMsg();
        } else {
            // $data = $em->printDebugger(['headers']);
            //  print_r($data);
            $this->dieMsg(false, "Error al enviar correo.");
        }
    }

    public function sendpregistro($nombres, $email, $password2)
    {
        $plan = $this->db->query("SELECT * FROM config_plantilla WHERE plan_id=3")->getRow();
        helper('formulario');
        $asunto = $this->traducir($plan->plan_asunto);

        $href_confirmar =  base_url('login/confirmar/' . urlencode($email) . '/' . $password2);
        $cuerpo = wpautop($this->traducir($plan->plan_cuerpo, ['BOTON' => "<a class='btn btn-primary' href='$href_confirmar' target='blank'> Confirmar </a>", 'NOMBRE' => $nombres]));

        $em = $this->getMail();
        $em->setTo($email);
        $em->setSubject($asunto);
        $em->setMessage($cuerpo);

        if ($em->send(FALSE)) {
        } else {
            $data = $em->printDebugger(['headers']);
            print_r($data);
            $this->dieMsg(false, "Error al enviar correo.");
        }
    }
}
