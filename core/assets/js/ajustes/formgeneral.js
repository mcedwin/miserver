function pre_wpautop(content) {
    // Funcion de Wordpress
    // We have a TON of cleanup to do. Line breaks are already stripped.

    // Protect pre|script tags
    content = content.replace(/<(pre|script)[^>]*>[\s\S]+?<\/\1>/g, function(a) {
        a = a.replace(/<br ?\/?>[\r\n]*/g, '<wp_temp>');
        return a.replace(/<\/?p( [^>]*)?>[\r\n]*/g, '<wp_temp>');
    });

    // Pretty it up for the source editor
    var blocklist1 = 'blockquote|ul|ol|li|table|thead|tbody|tr|th|td|div|h[1-6]|p';
    content = content.replace(new RegExp('\\s*</(' + blocklist1 + ')>\\s*', 'mg'), '</$1>\n');
    content = content.replace(new RegExp('\\s*<((' + blocklist1 + ')[^>]*)>', 'mg'), '\n<$1>');

    // Mark </p> if it has any attributes.
    content = content.replace(new RegExp('(<p [^>]+>.*?)</p>', 'mg'), '$1</p#>');

    // Sepatate <div> containing <p>
    content = content.replace(new RegExp('<div([^>]*)>\\s*<p>', 'mgi'), '<div$1>\n\n');

    // Remove <p> and <br />
    content = content.replace(new RegExp('\\s*<p>', 'mgi'), '');
    content = content.replace(new RegExp('\\s*</p>\\s*', 'mgi'), '\n\n');
    content = content.replace(new RegExp('\\n\\s*\\n', 'mgi'), '\n\n');
    content = content.replace(new RegExp('\\s*<br ?/?>\\s*', 'gi'), '\n');

    // Fix some block element newline issues
    content = content.replace(new RegExp('\\s*<div', 'mg'), '\n<div');
    content = content.replace(new RegExp('</div>\\s*', 'mg'), '</div>\n');
    content = content.replace(new RegExp('\\s*\\[caption([^\\[]+)\\[/caption\\]\\s*', 'gi'), '\n\n[caption$1[/caption]\n\n');
    content = content.replace(new RegExp('caption\\]\\n\\n+\\[caption', 'g'), 'caption]\n\n[caption');

    var blocklist2 = 'blockquote|ul|ol|li|table|thead|tr|th|td|h[1-6]|pre';
    content = content.replace(new RegExp('\\s*<((' + blocklist2 + ') ?[^>]*)\\s*>', 'mg'), '\n<$1>');
    content = content.replace(new RegExp('\\s*</(' + blocklist2 + ')>\\s*', 'mg'), '</$1>\n');
    content = content.replace(new RegExp('<li([^>]*)>', 'g'), '\t<li$1>');

    if (content.indexOf('<object') != -1) {
        content = content.replace(new RegExp('\\s*<param([^>]*)>\\s*', 'mg'), "<param$1>");
        content = content.replace(new RegExp('\\s*</embed>\\s*', 'mg'), '</embed>');
    }

    // Unmark special paragraph closing tags
    content = content.replace(new RegExp('</p#>', 'g'), '</p>\n');
    content = content.replace(new RegExp('\\s*(<p [^>]+>.*</p>)', 'mg'), '\n$1');

    // Trim whitespace
    content = content.replace(new RegExp('^\\s*', ''), '');
    content = content.replace(new RegExp('[\\s\\u00a0]*$', ''), '');

    // put back the line breaks in pre|script
    content = content.replace(/<wp_temp>/g, '\n');

    // Hope.
    return content;
}

function wpautop(pee) {
    // Funcion de Wordpress
    var blocklist = 'table|thead|tfoot|caption|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|form|blockquote|address|math|p|h[1-6]';

    pee = pee + "\n\n";
    pee = pee.replace(new RegExp('<br />\\s*<br />', 'gi'), "\n\n");
    pee = pee.replace(new RegExp('(<(?:' + blocklist + ')[^>]*>)', 'gi'), "\n$1");
    pee = pee.replace(new RegExp('(</(?:' + blocklist + ')>)', 'gi'), "$1\n\n");
    pee = pee.replace(new RegExp("\\r\\n|\\r", 'g'), "\n");
    pee = pee.replace(new RegExp("\\n\\s*\\n+", 'g'), "\n\n");
    pee = pee.replace(new RegExp('([\\s\\S]+?)\\n\\n', 'mg'), "<p>$1</p>\n");
    pee = pee.replace(new RegExp('<p>\\s*?</p>', 'gi'), '');
    pee = pee.replace(new RegExp('<p>\\s*(</?(?:' + blocklist + ')[^>]*>)\\s*</p>', 'gi'), "$1");
    pee = pee.replace(new RegExp("<p>(<li.+?)</p>", 'gi'), "$1");
    pee = pee.replace(new RegExp('<p>\\s*<blockquote([^>]*)>', 'gi'), "<blockquote$1><p>");
    pee = pee.replace(new RegExp('</blockquote>\\s*</p>', 'gi'), '</p></blockquote>');
    pee = pee.replace(new RegExp('<p>\\s*(</?(?:' + blocklist + ')[^>]*>)', 'gi'), "$1");
    pee = pee.replace(new RegExp('(</?(?:' + blocklist + ')[^>]*>)\\s*</p>', 'gi'), "$1");
    pee = pee.replace(new RegExp('\\s*\\n', 'gi'), "<br />\n");
    pee = pee.replace(new RegExp('(</?(?:' + blocklist + ')[^>]*>)\\s*<br />', 'gi'), "$1");
    pee = pee.replace(new RegExp('<br />(\\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)>)', 'gi'), '$1');
    pee = pee.replace(new RegExp('(?:<p>|<br ?/?>)*\\s*\\[caption([^\\[]+)\\[/caption\\]\\s*(?:</p>|<br ?/?>)*', 'gi'), '[caption$1[/caption]');
    // pee = pee.replace(new RegExp('^((?:&nbsp;)*)\\s', 'mg'), '$1&nbsp;');

    // Fix the pre|script tags
    pee = pee.replace(/<(pre|script)[^>]*>[\s\S]+?<\/\1>/g, function(a) {
        a = a.replace(/<br ?\/?>[\r\n]*/g, '\n');
        return a.replace(/<\/?p( [^>]*)?>[\r\n]*/g, '\n');
    });

    return pee;
}
/* Fin de Funciones para formatear el contenido */

$(document).ready(function() {

    $("#conf_portada_presentacion").text(wpautop($("#conf_portada_presentacion").text()))
    tinymce.init({
        selector: "#conf_portada_presentacion",
        menubar: false,
        toolbar: 'bold italic | alignleft aligncenter | bullist numlist | link image',
        object_resizing: false,
        plugins: 'paste code lists advlist link',
        paste_as_text: true,
        relative_urls: false,
        remove_script_host: false,
        setup: (editor) => {
            editor.on('submit', function() {
                return false;
            });
            editor.on('change', function() {
                $("#conf_portada_presentacion").text(pre_wpautop(editor.getContent()));
            });
            editor.on('init', function() {
                $("#conf_portada_presentacion").text(pre_wpautop(editor.getContent()));
            });
        }
    });


    $("#conf_portada_alerta").text(wpautop($("#conf_portada_alerta").text()))
    tinymce.init({
        selector: "#conf_portada_alerta",
        menubar: false,
        toolbar: 'bold italic | alignleft aligncenter | bullist numlist | link image',
        object_resizing: false,
        plugins: 'paste code lists advlist link',
        paste_as_text: true,
        relative_urls: false,
        remove_script_host: false,
        setup: (editor) => {
            editor.on('submit', function() {
                return false;
            });
            editor.on('change', function() {
                $("#conf_portada_alerta").text(pre_wpautop(editor.getContent()));
            });
            editor.on('init', function() {
                $("#conf_portada_alerta").text(pre_wpautop(editor.getContent()));
            });
        }
    });

    $('#form').submit(function() {
        // data.contenido = pre_wpautop(tinyMCE.get('conf_portada_presentacion').getContent());
        $(this).mysave((data) => document.location = data.redirect);

        return false;
    });


});