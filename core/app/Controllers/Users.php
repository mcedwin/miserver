<?php

namespace App\Controllers;

use App\Libraries\Ssp;
use Aws\Route53\Route53Client;
use App\Models\GeneralModel;

class Users extends BaseController
{
  protected $model;

  public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
  {
    parent::initController($request, $response, $logger);
    if (empty($this->user->id)) $response->redirect(base_url('login'));

    helper('server');
    $this->model = new GeneralModel('user');
  }


  public function index()
  {
    if ($this->user->id != 1) $this->response->redirect(base_url('databases'));
    $ssp = new Ssp();

    $this->addCss(array('lib/datatable/datatables.min.css'));
    $this->addJs(array('lib/datatable/datatables.min.js', 'js/users/lista.js'));
    $json = isset($_GET['json']) ? $_GET['json'] : false;

    $boton = function ($d, $row) {
      $url = 'http://' . $d;
      return '<a href="' . $url . '" target="_blank" class="btn btn-sm btn-warning "><i class="fa-solid fa-link"></i> ' . $d . '</a>';
    };

    $botonActivo = function ($d, $row) {
      $url = base_url('users/activar/' . $row['id']);
      if ($d == 1) return '<a href="' . $url . '" class="btn btn-sm btn-info activar"><i class="fa-solid fa-check"></i> Activo</a>';
      return '<a href="' . $url . '" class="btn btn-sm btn-light activar">Inactivo</a>';
    };

    $columns = array(
      array('db' => 'id', 'dt' => 'ID', "field" => "id"),
      array('db' => 'user', 'dt' => 'Usuario', "field" => "user"),
      array('db' => 'description', 'dt' => 'Descripción', "field" => "description"),
      array('db' => 'domain', 'dt' => 'Dominio', "field" => "domain", "formatter" => $boton),
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
    if ($this->user->id != 1) $this->response->redirect(base_url('databases'));
    helper('formulario');

    $datos['id'] = '0';
    $datos['titulo'] = 'Nuevo usuario';
    $datos['fields'] = $this->model->geti();

    $datos['editar'] = false;

    $this->showContent('form', $datos);
  }

  public function guardar($id = '')
  {
    if ($this->user->id != 1) $this->response->redirect(base_url('databases'));
    $data = $this->validar($this->model->getFields());

    if (empty($data['password'])) unset($data['password']);

    if (empty($id)) {
      $this->model->insert($data);
      shell_user_new($data['user'], $data['password'], $data['domain'], $this->user->token);
      // $this->crear_dominio($data['domain']);
      shell_reset_apache();
    } else {
      $this->model->update(['id' => $id], $data);
      if (isset($data['password']))
        shell_user_edit($data['user'], $data['password']);
    }
    $this->dieMsg(true);
  }

  public function editar($id)
  {
    if ($this->user->id != 1) $this->response->redirect(base_url('databases'));
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
    if ($this->user->id != 1) $this->response->redirect(base_url('databases'));
    $this->dieAjax();
    if ($id == '1') $this->dieMsg(false, "Usuario principal");
    $row = $this->db->query("SELECT * FROM user WHERE id='{$id}'")->getRow();
    $user = $row->user;
    $domain = $row->domain;
    $dbusers = $this->db->query("SELECT * FROM db_user WHERE idUser='{$id}'")->getResult();
    foreach ($dbusers as $row) {
      shell_dbuser_delete($user . '_' . $row->user);
    }
    $dbshemas = $this->db->query("SELECT * FROM db_shema WHERE idUser='{$id}'")->getResult();
    foreach ($dbshemas as $row) {
      shell_db_delete($user . '_' . $row->name);
    }



    shell_domain_delete($user, $domain, $this->user->token);
    // $this->borrar_dominio($domain);
    $domains = $this->db->query("SELECT * FROM domain WHERE idUser='{$id}'")->getResult();
    foreach ($domains as $row) {
      shell_domain_delete($user . '_' . $row->id, $row->domain, $this->user->token);
      // $this->borrar_dominio($row->domain);
    }

    shell_user_delete($user, $domain, $this->user->token);

    shell_reset_apache();

    $this->model->where("id='{$id}' AND id!=1")->delete();
    $this->dieMsg();
  }
  public function activar($id)
  {
    if ($this->user->id != 1) $this->response->redirect(base_url('databases'));
    $this->dieAjax();
    $this->db->query("UPDATE user SET active = NOT active WHERE id='{$id}'");
    $this->dieMsg();
  }

  public function crearmasa()
  {
    $str = "230380	A - 1 - APAZA ANAHUI WALDIR ALEXANDER	u230380	230380AR	u230380.piruw.com
221249	A - 2 - APAZA QUISPE ROYER	u221249	221249AR	u221249.piruw.com
231523	A - 3 - APAZA RAMIREZ JIMMY EDSON	u231523	231523AN	u231523.piruw.com
231449	A - 4 - ARIZACA MACHACA ERICK ABEL	u231449	231449AL	u231449.piruw.com
230010	A - 5 - CALDERON PILCO IVAN LEONIDAS	u230010	230010CS	u230010.piruw.com
230379	A - 6 - CALSINA CHIPANA BORIS OMAR	u230379	230379CR	u230379.piruw.com
230350	A - 7 - CONDORI NINA MILDWARD ERIK	u230350	230350CK	u230350.piruw.com
230320	A - 8 - CONDORI QUISPE WILLIAM YEFERSON	u230320	230320CN	u230320.piruw.com
231493	A - 9 - GODOY OCHOA DIEGO ALESSANDRO	u231493	231493GO	u231493.piruw.com
227411	A - 10 - HUANACUNI CCAMA, JHON EDY	u227411	227411HY	u227411.piruw.com
231511	A - 11 - MALLEA BUSTINZA JOEL EDWARS	u231511	231511MS	u231511.piruw.com
230858	A - 12 - MAYTA BARRIALES  HOOVER ADAN	u230858	230858MN	u230858.piruw.com
230347	A - 13 - ÑAUPA VALERIANO HECTOR PEDRO	u230347	230347ÑO	u230347.piruw.com
231487	A - 14 - OTAZU LUNA, JHON JHILMAR	u231487	231487OR	u231487.piruw.com
230452	A - 15 - PONCE QUILCA MISHAEL FERNANDO	u230452	230452PO	u230452.piruw.com
231483	A - 16 - QUISPE MALMA FRANCESCO ADRIANO	u231483	231483QO	u231483.piruw.com
231522	A - 17 - QUISPE TAPIA ALEX DANIEL	u231522	231522QL	u231522.piruw.com
230494	A - 18 - ROJAS LUQUE FRANCO	u230494	230494RO	u230494.piruw.com
231512	B - 1 - APAZA MEDINA NILBER YOSEL	u231512	231512AL	u231512.piruw.com
227585	B - 2 - CALCINA PACO DIOLNIVAR CROSBY	u227585	227585CY	u227585.piruw.com
232063	B - 3 - CALSIN RUIZ VLADIMIR YENAN	u232063	232063CN	u232063.piruw.com
091155	B - 4 - CHAMBILLA CHIPANA HERNAN	u091155	091155CN	u091155.piruw.com
227881	B - 5 - CHOQUEHUANCA CCAMA DIEGO ERICK	u227881	227881CK	u227881.piruw.com
123602	B - 6 - CUNO COILA DANNY YHOEL	u123602	123602CL	u123602.piruw.com
217008	B - 7 - CUTISACA CONDORI ALEXIS KENDIO	u217008	217008CO	u217008.piruw.com
232055	B - 8 - EDUARDO PAYE BRANDON	u232055	232055EN	u232055.piruw.com
230363	B - 9 - FLORES CCAMA KATHERINE SHIRLEY	u230363	230363FY	u230363.piruw.com
230920	B - 10 - FLORES TEVES, ESTEFANO ADRIAN	u230920	230920FN	u230920.piruw.com
227454	B - 11 - HUAYCANI CARTAGENA BRAYAN JHOEL	u227454	227454HL	u227454.piruw.com
227601	B - 12 - JARA CHACON RONALD JHOSUE	u227601	227601JE	u227601.piruw.com
230333	B - 13 - JULI YANA, CRISBERTH APARICIO	u230333	230333JO	u230333.piruw.com
229704	B - 14 - MAMANI CCOLLANQUI DEYVI ALEXANDER	u229704	229704MR	u229704.piruw.com
144005	B - 15 - MAMANI HUANCA KARINA BETTY	u144005	144005MY	u144005.piruw.com
230051	B - 16 - MENDOZA MACEDO JOSE MIGUEL	u230051	230051ML	u230051.piruw.com
230425	B - 17 - ÑAUPA COPACONDORI WILLIAN LUIS	u230425	230425ÑS	u230425.piruw.com
221400	B - 18 - ORDOÑEZ RAMOS ARNOLD DANIEL	u221400	221400OL	u221400.piruw.com
230921	B - 19 - ORTIZ BIZARRO, YORDY YOSEF	u230921	230921OF	u230921.piruw.com
230918	B - 20 - PONGO CALLO ELMER JOSE	u230918	230918PE	u230918.piruw.com
231477	B - 21 - QUISPE CALIZAYA ANTONY BRANDON	u231477	231477QN	u231477.piruw.com
227894	B - 22 - QUISPE CHOQUEHUANCA JOHAN ARCHY	u227894	227894QY	u227894.piruw.com
195917	B - 23 - QUISPE PAREDES PERCY ALVARO	u195917	195917QO	u195917.piruw.com
231468	B - 24 - QUISPE PEREZ CRISTOFER DEX	u231468	231468QX	u231468.piruw.com
227257	B - 25 - SARAZA MAMANI JOSHUA GABRIEL	u227257	227257SL	u227257.piruw.com
231429	B - 26 - SUAÑA CHAMBI MARC RUSBELL	u231429	231429SL	u231429.piruw.com
231502	B - 27 - TITALO LIMACHI JHANPIER STIVEN	u231502	231502TN	u231502.piruw.com
202555	B - 28 - VARGAS CONDORI MARILEYNA YESSI	u202555	202555VI	u202555.piruw.com
230370	B - 29 - VASQUEZ QUISPE JOEL FRANKLIN	u230370	230370VN	u230370.piruw.com
231519	B - 30 - VILCA CARI HECTOR YERAN	u231519	231519VN	u231519.piruw.com
228596	B - 31 - VILCA CARPIO CARLOS DANIEL	u228596	228596VL	u228596.piruw.com
230403	B - 32 - YUCRA MIRANDA ROY OMAR	u230403	230403YR	u230403.piruw.com";
    $rows = explode("\r\n", $str);
    print_r($rows);
    foreach ($rows as $row) {
      $row = explode("\t",$row);
      //print_r($row);
      shell_user_new($row[2],$row[3], $row[4], $this->user->token);
      echo "creando ".$row[2]."<br>\n";
    }
  }
}
