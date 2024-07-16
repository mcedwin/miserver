<?php

namespace App\Controllers;

use App\Libraries\Ssp;
use App\Models\GeneralModel;

class Domains extends BaseController
{
  protected $model;

  public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
  {
    parent::initController($request, $response, $logger);
    if (empty($this->user->id)) $response->redirect(base_url('login'));
    //if ($this->user->id!=1) $response->redirect(base_url('databases'));
    helper('server');
    $this->model = new GeneralModel('domain');
  }

  public function index()
  {
    $ssp = new Ssp();

    $this->addCss(array('lib/datatable/datatables.min.css'));
    $this->addJs(array('lib/datatable/datatables.min.js', 'js/domains/lista.js'));
    $json = isset($_GET['json']) ? $_GET['json'] : false;

    $botonActivo = function ($d, $row) {
      $url = base_url('users/activar/' . $row['id']);
      if ($d == 1) return '<a href="' . $url . '" class="btn btn-sm btn-info activar"><i class="fa-solid fa-check"></i> Activo</a>';
      return '<a href="' . $url . '" class="btn btn-sm btn-light activar">Inactivo</a>';
    };

    $columns = array(
      array('db' => 'id', 'dt' => 'ID', "field" => "id"),
      array('db' => 'domain', 'dt' => 'Dominio', "field" => "domain"),
      array('db' => 'folder', 'dt' => 'Folder', "field" => "folder"),
      array('db' => 'id',  'dt' => 'DT_RowId',        "field" => "id"),
    );

    if ($json) {
      $condiciones = array();

      $joinQuery = "FROM {$this->model->table}";
      $condiciones[] = "idUser = {$this->user->id}";

      $where = count($condiciones) > 0 ? implode(' AND ', $condiciones) : "";
      echo json_encode(
        $ssp->simple($_POST, $this->getDataConn(), $this->model->getTable(), $this->model->getPrimaryKey(), $columns, $joinQuery, $where)
      );
      exit(0);
    }
    helper('formulario');
    $response['columns'] = $columns;

    $this->showHeader();
    $this->ShowContent('index', $response);
    $this->showFooter();
  }

  public function crear()
  {
    helper('formulario');

    $datos['id'] = '0';
    $datos['titulo'] = 'Nuevo dominio';
    $datos['fields'] = $this->model->geti();

    $datos['editar'] = false;

    $this->showContent('form', $datos);
  }

  public function guardar($id = '')
  {
    $data = $this->validar($this->model->getFields());

    if (empty($id)) {
      $data['idUser'] = $this->user->id;
      $this->model->insert($data);
      $id = $this->model->getInsertID();
      $data['user'] = $this->user->user;
      shell_domain_new($data['user'], $data['user'] . '_' . $id, $data['domain'], $data['folder'], $this->user->token);
      shell_reset_apache();
      $domain = $data['domain'];
      shell_exec("sudo certbot --apache -d {$domain}");
    } else {
      $this->model->update(['id' => $id], $data);
    }
    $this->dieMsg(true);
  }


  public function borrar($id)
  {
    $this->dieAjax();
    $row = $this->db->query("SELECT * FROM domain WHERE id='{$id}' AND idUser='{$this->user->id}'")->getRow();
    shell_domain_delete($this->user->user . '_' . $id, $row->domain, $this->user->token);
    shell_reset_apache();
    $this->model->where("id='{$id}' AND id!=1")->delete();
    $this->dieMsg();
  }
}
