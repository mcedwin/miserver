$(document).ready(function() {

    var $table1;

    var $dt = $('#mitabla1'),
        conf = {
            data_source: window.location.href + '?json=1',
            cactions: ".ocform",
            order: [
                [0, "desc"]
            ],
            onrow: function(data) {
                html = `<div style='width:100px'>
                <a class="btn btn-danger delete1 btn-sm" href="{baseurl}/databases/borrar1/{id}"><i class="fas fa-trash-alt"></i></a></td>
                </div>`;
                html = replaceAll(html, "{baseurl}", base_url);
                html = replaceAll(html, "{id}", data.DT_RowId);
                return html;
            }
        };

    $('.ocform input,.ocform select').change(function() {
        $table1.draw();
        return false;
    })
    $('.ocform').submit(function() {
        $table1.draw();
        return false;
    })

    $table1 = $dt.load_simpleTable(conf);


    $(document).on('click', '.delete1', function() {
        $this = $(this);
        $.bsAlert.confirm("¿Desea eliminar el registro?", function() {
            $this.myprocess(() => $table1.draw());
        });
        return false;
    });


    $(document).on('click', '.edit1', function() {
        $(this).mydialog(function(dlg) { dlg.load_img(); }, () => $table1.draw())
        return false;
    });

    $(document).on('click', '.new1', function() {
        $(this).mydialog(function(dlg) { dlg.load_img(); }, () => $table1.draw())
        return false;
    });


});



$(document).ready(function() {

    var $table2;

    var $dt = $('#mitabla2'),
        conf = {
            data_source: window.location.href + '?json=2',
            cactions: ".ocform",
            order: [
                [0, "desc"]
            ],
            onrow: function(data) {
                html = `<div style='width:100px'>
                <a class="btn btn-success edit2 btn-sm" href="{baseurl}/databases/editar2/{id}"><i class="fas fa-edit"></i></a>
                <a class="btn btn-danger delete2 btn-sm" href="{baseurl}/databases/borrar2/{id}"><i class="fas fa-trash-alt"></i></a></td>
                </div>`;
                html = replaceAll(html, "{baseurl}", base_url);
                html = replaceAll(html, "{id}", data.DT_RowId);
                return html;
            }
        };

    $('.ocform input,.ocform select').change(function() {
        $table2.draw();
        return false;
    })
    $('.ocform').submit(function() {
        $table2.draw();
        return false;
    })

    $table2 = $dt.load_simpleTable(conf);


    $(document).on('click', '.delete2', function() {
        $this = $(this);
        $.bsAlert.confirm("¿Desea eliminar el registro?", function() {
            $this.myprocess(() => $table.draw());
        });
        return false;
    });


    $(document).on('click', '.edit2', function() {
        $(this).mydialog(function(dlg) { dlg.load_img(); }, () => $table2.draw())
        return false;
    });

    $(document).on('click', '.new2', function() {
        $(this).mydialog(function(dlg) { dlg.load_img(); }, () => $table2.draw())
        return false;
    });


});


$(document).ready(function() {

    var $table3;

    var $dt = $('#mitabla3'),
        conf = {
            data_source: window.location.href + '?json=3',
            cactions: ".ocform",
            order: [
                [0, "desc"]
            ],
            onrow: function(data) {
                html = `<div style='width:100px'>
                <a class="btn btn-danger delete3 btn-sm" href="{baseurl}/databases/borrar3/{idu}/{ids}"><i class="fas fa-trash-alt"></i></a></td>
                </div>`;
                html = replaceAll(html, "{baseurl}", base_url);
                html = replaceAll(html, "{idu}", data.DT_RowIdU);
				html = replaceAll(html, "{ids}", data.DT_RowIdS);
                return html;
            }
        };

    $('.ocform input,.ocform select').change(function() {
        $table3.draw();
        return false;
    })
    $('.ocform').submit(function() {
        $table3.draw();
        return false;
    })

    $table3 = $dt.load_simpleTable(conf);


    $(document).on('click', '.delete3', function() {
        $this = $(this);
        $.bsAlert.confirm("¿Desea eliminar el registro?", function() {
            $this.myprocess(() => $table3.draw());
        });
        return false;
    });


    $(document).on('click', '.edit3', function() {
        $(this).mydialog(function(dlg) { dlg.load_img(); }, () => $table3.draw())
        return false;
    });

    $(document).on('click', '.new3', function() {
        $(this).mydialog(function(dlg) { dlg.load_img(); }, () => $table3.draw())
        return false;
    });

 


});