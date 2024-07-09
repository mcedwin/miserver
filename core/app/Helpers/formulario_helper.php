<?php
function select_options($datos, $opts, $vacio = FALSE)
{
    $options = ($vacio != FALSE) ? array("" => $vacio) : array();
    $id = $opts[0];
    $nombre = $opts[1];
    foreach ($datos as $value) {
        $options[$value->$id] = $value->$nombre;
    }
    return $options; 
}

function select_enum($datos, $vacio = FALSE)
{
    $options = ($vacio != FALSE) ? array("" => $vacio) : array();
    foreach ($datos as $value) {
        $options[$value] = $value;
    }
    return $options;
}

function dateToMysql($date)
{
    $date = trim($date);
    if (preg_match('#([0-9]{1,2})/([0-9]{1,2})/([0-9]{2,4}) ([0-9]{1,2}):([0-9]{1,2}) ([A|P][M])#', $date, $buf)) {
        $hora = ($buf[6] == 'PM' && $buf[4] < 12) ? $buf[4] + 12 : ($buf[4] == 12 && $buf[6] == 'AM' ? 0 : $buf[4]);
        return $buf[3] . '-' . $buf[2] . '-' . $buf[1] . ' ' . $hora . ':' . $buf[5] . ':00';
    }
    return preg_replace('#(\d{1,2})/(\d{1,2})/(\d{4})#', '$3-$2-$1', $date);
}

function dateToUser($date)
{
    $date = trim($date);
    if (preg_match('/ /', $date)) return preg_replace('#(\d{4})-(\d{1,2})-(\d{1,2})\s(.*)#', '$3/$2/$1 $4', $date);
    return preg_replace('#(\d{4})-(\d{1,2})-(\d{1,2})#', '$3/$2/$1', $date);
}
// m = edwin calderon // campos = [titulo,resumen,contenido]
function GetQS($_m, $campos)
{
    $_m = preg_replace("/[ \t]+/i", " ", trim($_m));
    $mms = explode(" ", $_m);
    $arro = array();
    foreach ($mms as $ms) {
        $arra = array();
        foreach ($campos as $cam) {
            $arra[] = "$cam LIKE '%$ms%'";
        }
        $arro[] = "(" . implode(" OR ", $arra) . ")";
    }
    $mm_sql = '(' . implode(" AND ", $arro) . ')';
    return $mm_sql;
}

function myselect($name, $value, $placeholder, $data = [], $params = '')
{
    $options = count($data) > 1 ? "<option value=''>{$placeholder}</option>" : '';
    foreach ($data as $item) {
        $options .= "<option value='{$item->id}' " . ($item->id == $value ? 'selected' : '') . ">{$item->text}</option>";
    }
    $html = <<<EOT
        <select class="form-select" id="{$name}" name="{$name}" {$params}>{$options}</select>
EOT;

    return $html;
}

function myinput($reg, $col, $class = '', $params = '', $data = array(), $placeholder = "",$group='')
{

    if (!isset($reg->id)) $reg->id = $reg->name;

    if (isset($reg->max_length) && $reg->max_length >= 256) $reg->type = 'text';
    $options = count($data) > 1 ? "<option value=''>{$placeholder}</option>" : '';
    foreach ($data as $item) {
        $options .= "<option value='{$item->id}' " . ($item->id == $reg->value ? 'selected' : '') . ">{$item->text}</option>";
    }
    $add = '';
    if ($reg->required) {
        $params .= ' required';
        $add = '<strong class="text-danger">*</strong>';
    }
    $label = '';
    $html = '';
    if ($col != '0') {
        $label = "<label for='{$reg->name}'>{$reg->label} {$add}</label>";
        $html .= <<<EOT
            <div class="form-group mb-2 col-md-{$col}">
EOT;
    }

    if(!empty($group)){
        $label .= '<div class="input-group"><span class="input-group-text">'.$group.'</span>';
    }

    if ($reg->type == 'text') {
        $html .= <<<EOT
        {$label}<textarea class="form-control limit" maxlength="{$reg->max_length}" id="{$reg->name}" name="{$reg->name}" {$params}>{$reg->value}</textarea>
EOT;
    } else if ($reg->type == 'int') {
        $html .= <<<EOT
        {$label}<input type="number" class="form-control" maxlength="{$reg->max_length}" id="{$reg->name}" name="{$reg->name}" value="{$reg->value}" {$params} autocomplete="off">
EOT;
    } else if ($reg->type == 'select') {
        $html .= <<<EOT
        {$label}<select class="form-select {$class}" id="{$reg->id}" name="{$reg->name}" {$params}>{$options}</select>
EOT;
    } else if ($reg->type == 'date') {
        $html .= <<<EOT
        {$label}<input type="date" class="form-control {$class}" id="{$reg->name}" name="{$reg->name}" value="{$reg->value}" {$params} placeholder="día/mes/año">
EOT;
    } else if ($reg->type == 'bit') {
        $checked = ($reg->value == '1' ? 'checked' : '');
        $html .= <<<EOT
        <div class="form-check">
        <input type='hidden' value='0' name="{$reg->name}">
        <input class="form-check-input" type="checkbox" value="1" name="{$reg->name}" {$checked} id="{$reg->name}" {$params}>
        <label class="form-check-label" for="{$reg->name}">
        {$label}
        </label>
        </div>
EOT;
    } else if ($reg->type == 'hidden') {
        $html .= <<<EOT
        <input type="hidden" class="form-control" id="{$reg->name}" name="{$reg->name}" value="{$reg->value}" {$params}>
EOT;
    } else if ($reg->type == 'url') {
        $html .= <<<EOT
        {$label}<div class="input-group date" id="{$reg->name}" data-target-input="nearest" >
                                    <div class="input-group-prepend" data-target="#{$reg->name}" data-toggle="datetimepicker">
                                        <div class="input-group-text">https://</div>
                                    </div>
                                    <input type="text" class="form-control datetimepicker-input" name="{$reg->name}" value="{$reg->value}" autocomplete="off" data-target="#{$reg->name}" {$params}>
                                </div>
EOT;
    } else if ($reg->type == 'password') {
        $html .= <<<EOT
    {$label}<input type="password" class="form-control" maxlength="{$reg->max_length}" id="{$reg->name}" name="{$reg->name}" value="{$reg->value}" {$params} autocomplete="off"  placeholder="{$reg->label}">
EOT;
    } else {
        $html .= <<<EOT
    {$label}<input type="text" class="form-control {$class}" maxlength="{$reg->max_length}" id="{$reg->id}" name="{$reg->name}" value="{$reg->value}" {$params} autocomplete="off" placeholder="{$reg->label}">
EOT;
    }


    if(!empty($group)){
        $html .= '</div>';
    }

    if ($col != '0') $html .= '</div>';
    return $html;
}



function select2($reg, $col, $class = '', $params = '', $datas = array(), $placeholder = "")
{

    if (!isset($reg->id)) $reg->id = $reg->name;

    $options = count($datas) > 1 ? "<option value=''>{$placeholder}</option>" : '';
    foreach ($datas as $data) {
        $options .= "<optgroup label='".$data['grupo']."'>";
        foreach($data['lista'] as $item){
        $options .= "<option value='{$item->id}' " . ($item->id == $reg->value ? 'selected' : '') . ">{$item->text}</option>";
        }
        $options .= '</optgroup>';
    }
    $add = '';
    if ($reg->required) {
        $params .= ' required';
        $add = '<strong class="text-danger">*</strong>';
    }
    $label = '';
    $html = '';
    if ($col != '0') {
        $label = "<label for='{$reg->name}'>{$reg->label} {$add}</label>";
        $html .= <<<EOT
            <div class="form-group mb-2 col-md-{$col}">
EOT;
    }
    if ($reg->type == 'select') {
        $html .= <<<EOT
        {$label}<select class="form-select {$class}" id="{$reg->id}" name="{$reg->name}" {$params}>{$options}</select>
EOT;
    }
    if ($col != '0') $html .= '</div>';
    return $html;
}


function myview($reg, $col, $class = '', $params = '', $data = array())
{

    if (!isset($reg->id)) $reg->id = $reg->name;

    foreach ($data as $item) {
        if ($item->id == $reg->value) {
            $reg->value = $item->text;
            break;
        }
    }

    $label = '';
    $html = '';
    if ($col != '0') {
        $label = "<strong>{$reg->label}</strong>";
        $html .= <<<EOT
            <div class="form-group mb-2 col-md-{$col}">
EOT;
    }

    $html .= <<<EOT
        {$label}<p id="{$reg->name}" {$params} class="py-1 mb-0">{$reg->value}</p></div>
EOT;

    return $html;
}

// crear parrafos a partir de saltos de linea
function wpautop($pee, $br = 1)
{
    $pee = $pee . "\n"; // just to make things a little easier, pad the end
    $pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
    // Space things out a little
    $allblocks = '(?:table|thead|tfoot|caption|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|form|map|area|blockquote|address|math|style|input|p|h[1-6]|hr)';
    $pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
    $pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
    $pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
    if (strpos($pee, '<object') !== false) {
        $pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
        $pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
    }
    $pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
    // make paragraphs, including one at the end
    $pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
    $pee = '';
    foreach ($pees as $tinkle)
        $pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
    $pee = preg_replace('|<p>\s*?</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
    //$pee = preg_replace('!<p>([^<]+)\s*?(</(?:div|address|form)[^>]*>)!', "<p>$1</p>$2", $pee);
    $pee = preg_replace('|<p>|', "$1<p>", $pee);
    $pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
    $pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
    $pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
    $pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
    $pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);
    $pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);
    if ($br) {
        $pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', function ($matches) {
            return str_replace("\n", "<WPPreserveNewline />", $matches[0]);
        }, $pee);
        $pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
        $pee = str_replace('<WPPreserveNewline />', "\n", $pee);
    }
    $pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);
    $pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
    if (strpos($pee, '<pre') !== false)
        $pee = preg_replace_callback('!(<pre.*?>)(.*?)</pre>!is', 'clean_pre', $pee);
    $pee = preg_replace("|\n</p>$|", '</p>', $pee);
    //$pee = preg_replace('/<p>\s*?(' . get_shortcode_regex() . ')\s*<\/p>/s', '$1', $pee); // don't auto-p wrap shortcodes that stand alone
    return $pee;
}
