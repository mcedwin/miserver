<?php

namespace App\Models;

use CodeIgniter\Model;

class GeneralModel extends Model
{

    protected $returnType = 'object';
    public $fields = '';

    public function __construct($table)
    {

        $datas['user'] = ['table' => 'user', 'primary' => 'id', 'fields' => [
            'id' => array('label' => 'ID'),
            'user' => array('label' => 'Usuario', ),
            'password' => array('label' => 'Password', 'required' => false),
            'description' => array('label' => 'Descripción'),
            'domain' => array('label' => 'Dominio'),
            'active' => array('label' => 'Tipo'),
        ]];

        $datas['domain'] = ['table' => 'domain', 'primary' => 'id', 'fields' => [
            'id' => array('label' => 'ID'),
            'domain' => array('label' => 'Dominio'),
            'folder' => array('label' => 'Folder', ),
            'idUser' => array('label' => 'Usuario'),
        ]];

        $datas['db_user'] = ['table' => 'db_user', 'primary' => 'id', 'fields' => [
            'id' => array('label' => 'ID'),
            'user' => array('label' => 'Usuario'),
            'password' => array('label' => 'Password', 'required' => false),
            'idUser' => array('label' => 'Usuario'),
        ]];

        $datas['db_shema'] = ['table' => 'db_shema', 'primary' => 'id', 'fields' => [
            'id' => array('label' => 'ID'),
            'name' => array('label' => 'Usuario'),
            'idUser' => array('label' => 'Usuario'),
        ]];

        $datas['db_relation'] = ['table' => 'db_relation', 'primary' => 'idShema', 'fields' => [
            'idShema' => array('label' => 'Database','type' => 'select'),
            'idUser' => array('label' => 'Usuario','type' => 'select'),
        ]];

        extract($datas[$table]);

        $this->table = $table;
        $this->fields = $fields;
        $this->primaryKey = $primary;

        parent::__construct();
    }

    public function getTable()
    {
        return $this->table;
    }
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    protected function initialize()
    {
        helper('funciones');

        $dfields = $this->db->getFieldData($this->table);

        iniFields($dfields, $this->fields);

        foreach ($this->fields as $field) {
            $this->allowedFields[] = $field->name;
        }
    }

    function getFields()
    {
        return $this->fields;
    }

    function geti($id = '')
    {
        $builder = $this->db->table($this->table);

        if (!empty($id)) {
            $row = $builder->select()->where($this->primaryKey, $id)->get()->getRow();
            foreach ($row as $k => $value) {
                if (!isset($this->fields[$k])) continue;
                $this->fields[$k]->value =  $value;
            }
        }

        return (object)$this->fields;
    }

    function enum_valores($campo)
    {
        $consulta = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE '$campo'");
        if ($consulta->getNumRows() > 0) {
            $consulta = $consulta->getRow();
            $array = explode(",", str_replace(array("enum", "'", "(", ")"), "", $consulta->Type));
            foreach ($array as $key) {
                $array2[] = (object)array('id' => $key, 'text' => $key);
            }
            return $array2;
        } else {
            return FALSE;
        }
    }
}
