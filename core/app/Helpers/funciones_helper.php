<?php

function iniFields($fields, &$tfields)
{
    foreach ($fields as $reg) {
        if (!isset($tfields[$reg->name])) continue;
        $tfields[$reg->name]['type'] = isset($tfields[$reg->name]['type']) ? $tfields[$reg->name]['type'] : $reg->type;
        $tfields[$reg->name]['name'] = isset($tfields[$reg->name]['name']) ? $tfields[$reg->name]['name'] : $reg->name;
        $tfields[$reg->name]['max_length'] = $reg->max_length;
        $tfields[$reg->name]['value'] =  isset($tfields[$reg->name]['value']) ? $tfields[$reg->name]['value'] : '';
        $tfields[$reg->name]['required'] =  isset($tfields[$reg->name]['required']) ? $tfields[$reg->name]['required'] : true;
        $tfields[$reg->name]['valid'] =  isset($tfields[$reg->name]['valid']) ? $tfields[$reg->name]['valid'] : '';
        $tfields[$reg->name] = (object) $tfields[$reg->name];
    }
}



function resumen($contenido){
    return substr($contenido,0,255)."...";
}

function get_image($folder,$fname,$size){
    return base_url('uploads/'.$folder.'/' . str_replace('normal', $size, $fname));
}

function THS($arr)
{
    $str = "";
    foreach ($arr as $cod => $val) {
        if (!preg_match('/DT_/', $val['dt']))
            $str .= '<th class="ths">' . $val['dt'] . '</th>';
    }
    return $str;
}

function genDataTable($id, $columns, $withcheck = false, $responsive = false)
{
    if ($responsive) $class = "table table-striped table-bordered dt-responsive";
    else $class = "table table-striped table-bordered";
    return '<table id="' . $id . '" wch="' . $withcheck . '" cellpadding="0" cellspacing="0" border="0" width="100%" class="' . $class . '">
          <thead>
              <tr>
                  '  . THS($columns) . ($withcheck ? '<th></th>' : '') . '
              </tr>
          </thead>
      </table>';
}