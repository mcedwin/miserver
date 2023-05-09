<?php

namespace App\Controllers;

use App\Libraries\Ssp;
use App\Models\GeneralModel;

class Users extends BaseController
{
    protected $model;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        if (empty($this->user->id)) $response->redirect(base_url('login'));
        if ($this->user->id!=1) $response->redirect(base_url('databases'));
        helper('server');
        $this->model = new GeneralModel('user');
    }


    public function index()
    {
        $ssp = new Ssp();

        $this->addCss(array('lib/datatable/datatables.min.css'));
        $this->addJs(array('lib/datatable/datatables.min.js', 'js/users/lista.js'));
        $json = isset($_GET['json']) ? $_GET['json'] : false;

        $botonActivo = function ($d, $row) {
            $url = base_url('users/activar/' . $row['id']);
            if ($d == 1) return '<a href="' . $url . '" class="btn btn-sm btn-info activar"><i class="fa-solid fa-check"></i> Activo</a>';
            return '<a href="' . $url . '" class="btn btn-sm btn-light activar">Inactivo</a>';
        };

        $columns = array(
            array('db' => 'id', 'dt' => 'ID', "field" => "id"),
            array('db' => 'user', 'dt' => 'Usuario', "field" => "user"),
            array('db' => 'description', 'dt' => 'Descripción', "field" => "description"),
            array('db' => 'domain', 'dt' => 'Dominio', "field" => "domain"),
            array('db' => 'active', 'dt' => 'Activo', "field" => "active", "formatter" => $botonActivo),
            array('db' => 'id',  'dt' => 'DT_RowId',        "field" => "id"),
        );

        if ($json) {
            $condiciones = array();

            $joinQuery = "FROM {$this->model->table}";
            $condiciones[] = "1";

            $activo = $this->request->getPost('activo');

            if (!empty($activo)) $condiciones[] = " active = '{$activo}'";

            $where = count($condiciones) > 0 ? implode(' AND ', $condiciones) : "";
            echo json_encode(
                $ssp->simple($_POST, $this->getDataConn(), $this->model->getTable(), $this->model->getPrimaryKey(), $columns, $joinQuery, $where)
            );
            exit(0);
        }
        helper('formulario');
        $response['columns'] = $columns;

        $this->showHeader();
        $this->ShowContent('lista', $response);
        $this->showFooter();
    }


    public function perfil()
    {
        $this->meta->title = "Perfil de miembro";
        helper("formulario");
        $datos['fields'] = $this->model->geti($this->user->id);
        $datos['fields']->password->value = '';
        $this->addJs(array("js/users/perfil.js"));
        $this->showHeader();
        $this->ShowContent('perfil', $datos);
        $this->showFooter();
    }

    public function guardar_perfil()
    {
        $data = $this->validar($this->model->getFields());
        if (empty($data['password'])) unset($data['password']);
        else {
            $data['user'] = $this->user->user;
            shell_user_edit($data['user'], $data['password']);
        }

        $this->db->table('user')->update($data, array('id' => $this->user->id));
        $this->dieMsg(true, '', base_url('users/perfil'));
    }

    public function crear()
    {
        helper('formulario');

        $datos['id'] = '0';
        $datos['titulo'] = 'Nuevo usuario';
        $datos['fields'] = $this->model->geti();

        $datos['editar'] = false;

        $this->showContent('form', $datos);
    }

    public function guardar($id = '')
    {
        $data = $this->validar($this->model->getFields());

		print_r($data);
        //if (empty($data['password'])) unset($data['password']);

        if (empty($id)) {
            $this->model->insert($data);
            shell_user_new($data['user'], $data['password'], $data['domain']);
            shell_reset_apache();
        } else {
			print_r($data);
            $this->model->update(['id' => $id], $data);
            if (isset($data['password']))
                shell_user_edit($data['user'], $data['password']);
        }
        $this->dieMsg(true);
    }

    public function editar($id)
    {
        helper('formulario');
        $datos['id'] = $id;
        $datos['titulo'] = 'Editar usuario';

        $datos['fields'] = $this->model->geti($id);
        $datos['fields']->password->value = '';

        $datos['editar'] = true;

        $this->showContent('form', $datos);
    }

    public function borrar($id)
    {
        $this->dieAjax();
        if ($id == '1') $this->dieMsg(false, "Usuario principal");
        $row = $this->db->query("SELECT * FROM user WHERE id='{$id}'")->getRow();
        $user = $row->user;
        $dbusers = $this->db->query("SELECT * FROM db_user WHERE idUser='{$id}'")->getResult();
        foreach ($dbusers as $row) {
            shell_dbuser_delete($row->user);
        }
        $dbshemas = $this->db->query("SELECT * FROM db_shema WHERE idUser='{$id}'")->getResult();
        foreach ($dbshemas as $row) {
            shell_db_delete($row->name);
        }

        shell_domain_delete($user);
        $domains = $this->db->query("SELECT * FROM domain WHERE idUser='{$id}'")->getResult();
        foreach ($domains as $row) {
            shell_domain_delete($user . '_' . $row->id);
        }

        shell_user_delete($row->user);

        shell_reset_apache();

        $this->model->where("id='{$id}' AND id!=1")->delete();
        $this->dieMsg();
    }
    public function activar($id)
    {
        $this->dieAjax();
        $this->db->query("UPDATE user SET active = NOT active WHERE id='{$id}'");
        $this->dieMsg();
    }
}
