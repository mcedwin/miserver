<?php

namespace App\Controllers;

use App\Models\GeneralModel;

class Ajustes extends BaseController
{
    protected $model;

    public function __construct()
    {
        $this->model = new GeneralModel('config');
    }

    public function index()
    {
        $this->meta->title = "Perfil de miembro";
        helper("formulario");
        $this->datos['fields'] = $this->model->geti(1);
        $this->datos['fields']->conf_logo->value = base_url('uploads/recursos') . (empty($this->datos['fields']->conf_logo->value) ? '/sinlogo.png' : '/' . $this->datos['fields']->conf_logo->value);
        $this->addJs(array(
            "lib/tinymce/tinymce.min.js",
            "lib/tinymce/jquery.tinymce.min.js",
            'js/ajustes/formgeneral.js',
            "js/ajustes/form.js"
        ));
        $this->showHeader();
        $this->ShowContent('index');
        $this->showFooter();
    }

    public function guardar()
    {
        $data = $this->validar($this->model->getFields());
        unset($data['conf_logo']);

        $path = 'img_' . 1 . '.small.jpg';
        if ($this->guardar_imagen('uploads/recursos', $path)) {
            $data = array_merge($data, array('conf_logo' => $path));
        }

        $this->db->table('config')->update($data, array('conf_id' => 1));
        $this->dieMsg(true, '', base_url('ajustes/index'));
    }

    public function guardargeneral()
    {
        $data = $this->validar($this->model->getFields());

        $this->db->table('config')->update($data, array('conf_id' => 1));
        $this->dieMsg(true, '', base_url('ajustes/index'));

    }
}
