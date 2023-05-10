<?php

namespace App\Controllers;

use App\Libraries\Ssp;
use App\Models\GeneralModel;

class Databases extends BaseController
{
    protected $modelShema;
    protected $modelUser;
    protected $modelRelation;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        if (empty($this->user->id)) $response->redirect(base_url('login'));
        helper('server');
        $this->modelShema = new GeneralModel('db_shema');
        $this->modelUser = new GeneralModel('db_user');
        $this->modelRelation = new GeneralModel('db_relation');
    }
    public function phpmyadmin()
    {
        $row = $this->db->query("SELECT * FROM user WHERE id='{$this->user->id}'")->getRow();
        $_SESSION["dbuser"] = $row->user;
        $_SESSION["dbpassword"] = $row->password;
        if (empty($this->user->id)) $this->response->redirect(base_url('/phpmyadmin'));
    }
    public function index()
    {
        $ssp = new Ssp();

        $this->addCss(array('lib/datatable/datatables.min.css'));
        $this->addJs(array('lib/datatable/datatables.min.js', 'js/databases/lista.js'));
        $json = isset($_GET['json']) ? $_GET['json'] : false;

        $columns1 = array(
            array('db' => "CONCAT('{$this->user->user}_',name) as dbname", 'dt' => 'Nombre', "field" => "dbname"),
            array('db' => 'id',  'dt' => 'DT_RowId',        "field" => "id"),
        );


        $columns2 = array(
            array('db' => "CONCAT('{$this->user->user}_',user) as dbuser", 'dt' => 'Usuario', "field" => "dbuser"),
            array('db' => 'id',  'dt' => 'DT_RowId',        "field" => "id"),
        );

        $columns3 = array(
            array('db' => "CONCAT('{$this->user->user}_',db_user.user) as dbuser", 'dt' => 'Usuario', "field" => "dbuser"),
            array('db' => "CONCAT('{$this->user->user}_',db_shema.name) as dbname", 'dt' => 'Base de datos', "field" => "dbname"),
            array('db' => 'db_user.id as idu',  'dt' => 'DT_RowIdU',        "field" => "idu"),
            array('db' => 'db_shema.id as ids',  'dt' => 'DT_RowIdS',        "field" => "ids"),
        );


        if ($json == '1') {
            $condiciones = array();

            $joinQuery = "FROM {$this->modelShema->table}";
            $condiciones[] = "idUser = {$this->user->id}";

            $where = count($condiciones) > 0 ? implode(' AND ', $condiciones) : "";
            echo json_encode(
                $ssp->simple($_POST, $this->getDataConn(), $this->modelShema->getTable(), $this->modelShema->getPrimaryKey(), $columns1, $joinQuery, $where)
            );
            exit(0);
        }

        if ($json == '2') {
            $condiciones = array();

            $joinQuery = "FROM {$this->modelUser->table}";
            $condiciones[] = "idUser = {$this->user->id}";

            $where = count($condiciones) > 0 ? implode(' AND ', $condiciones) : "";
            echo json_encode(
                $ssp->simple($_POST, $this->getDataConn(), $this->modelUser->getTable(), $this->modelUser->getPrimaryKey(), $columns2, $joinQuery, $where)
            );
            exit(0);
        }

        if ($json == '3') {
            $condiciones = array();

            $joinQuery = "FROM {$this->modelRelation->table} 
            JOIN db_user ON db_user.id=db_relation.idUser
            JOIN db_shema ON db_shema.id=db_relation.idShema
            ";
            $condiciones[] = "db_relation.idUser = {$this->user->id}";

            $where = count($condiciones) > 0 ? implode(' AND ', $condiciones) : "";
            echo json_encode(
                $ssp->simple($_POST, $this->getDataConn(), $this->modelRelation->getTable(), $this->modelRelation->getPrimaryKey(), $columns3, $joinQuery, $where)
            );
            exit(0);
        }
        helper('formulario');
        $response['columns1'] = $columns1;
        $response['columns2'] = $columns2;
        $response['columns3'] = $columns3;

        $this->showHeader();
        $this->ShowContent('index', $response);
        $this->showFooter();
    }

    public function crear1()
    {
        helper('formulario');

        $datos['id'] = '0';
        $datos['titulo'] = 'Nueva base de datos';
        $datos['fields'] = $this->modelShema->geti();

        $this->showContent('form1', $datos);
    }

    public function guardar1($id = '')
    {
        $data = $this->validar($this->modelShema->getFields());

        if (empty($id)) {
            $data['idUser'] = $this->user->id;
            $this->modelShema->insert($data);
            $dbname = $this->user->user . '_' . $data['name'];
            shell_db_new($this->user->user, $dbname);
        } else {
        }
        $this->dieMsg(true);
    }


    public function borrar1($id)
    {
        $this->dieAjax();
        $row = $this->db->query("SELECT * FROM db_shema WHERE id='{$id}' AND idUser='{$this->user->id}'")->getRow();
        $dbname = $this->user->user . '_' . $row->name;
        shell_db_delete($dbname);
        $this->modelShema->where("id='{$id}' AND id!=1")->delete();
        $this->dieMsg();
    }


    public function crear2()
    {
        helper('formulario');

        $datos['id'] = '0';
        $datos['titulo'] = 'Nuevo usario de base de datos';
        $datos['fields'] = $this->modelUser->geti();
        $datos['editar'] = false;

        $this->showContent('form2', $datos);
    }

    public function guardar2($id = '')
    {
        $data = $this->validar($this->modelUser->getFields());

        if (empty($data['password'])) unset($data['password']);

        if (empty($id)) {
            $data['idUser'] = $this->user->id;
            $this->modelUser->insert($data);
            $dbuser = $this->user->user . '_' . $data['user'];
            shell_dbuser_new($dbuser, $data['password']);
        } else {
            $this->modelUser->update(['id' => $id], $data);
            $dbuser = $this->user->user . '_' . $data['user'];
            if (isset($data['password']))
                shell_dbuser_edit($dbuser, $data['password']);
        }
        $this->dieMsg(true);
    }

    public function editar2($id)
    {
        helper('formulario');
        $datos['id'] = $id;
        $datos['titulo'] = 'Editar usuario de base de datos';

        $datos['fields'] = $this->modelUser->geti($id);
        $datos['fields']->password->value = '';

        $datos['editar'] = true;

        $this->showContent('form2', $datos);
    }

    public function borrar2($id)
    {
        $this->dieAjax();
        $row = $this->db->query("SELECT * FROM db_user WHERE id='{$id}' AND idUser='{$this->user->id}'")->getRow();
        $dbuser = $this->user->user . '_' . $row->user;
        shell_dbuser_delete($dbuser);
        $this->modelUser->where("id='{$id}' AND id!=1")->delete();
        $this->dieMsg();
    }



    public function crear3()
    {
        helper('formulario');

        $datos['id'] = '0';
        $datos['titulo'] = 'Relacionar base de datos con usuario';
        $datos['shemas'] = $this->db->query("SELECT id as `id`, name as `text` FROM db_shema WHERE idUser='{$this->user->id}'")->getResult();
        $datos['users'] = $this->db->query("SELECT id as `id`, user as `text` FROM db_user WHERE idUser='{$this->user->id}'")->getResult();
        $datos['fields'] = $this->modelRelation->geti();

        $datos['editar'] = false;

        $this->showContent('form3', $datos);
    }

    public function guardar3($id = '')
    {
        $data = $this->validar($this->modelRelation->getFields());

        if (empty($id)) {
            $data['idUser'] = $this->user->id;
            $this->modelRelation->insert($data);
            $iduser = $data['idUser'];
            $idshema = $data['idShema'];
            $user = $this->db->query("SELECT * FROM db_user WHERE id='{$iduser}' AND idUser='{$this->user->id}'")->getRow()->user;
            $name = $this->db->query("SELECT * FROM db_shema WHERE id='{$idshema}' AND idUser='{$this->user->id}'")->getRow()->name;
            $dbname = $this->user->user . '_' . $name;
            $dbuser = $this->user->user . '_' . $user;
            shell_dbrelation_new($dbname, $dbuser);
        } else {
        }
        $this->dieMsg(true);
    }


    public function borrar3($idUser, $idShema)
    {
        $this->dieAjax();
        $row = $this->db->query("SELECT * FROM db_shema WHERE id='{$idShema}' AND idUser='{$this->user->id}'")->getRow();

        if ($row) {
            $user = $this->db->query("SELECT * FROM db_user WHERE id='{$idUser}' AND idUser='{$this->user->id}'")->getRow()->user;
            $name = $this->db->query("SELECT * FROM db_shema WHERE id='{$idShema}' AND idUser='{$this->user->id}'")->getRow()->name;
            $dbname = $this->user->user . '_' . $name;
            $dbuser = $this->user->user . '_' . $user;

            shell_dbrelation_delete($dbname, $dbuser);

            $this->modelRelation->where("idUser='{$idUser}' AND idShema='{$idShema}'")->delete();
        }


        $this->dieMsg();
    }
}
