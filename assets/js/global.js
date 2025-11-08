// 🔥 Delegación global para cualquier botón dropdown
$(document).on('click', '.dropdown-toggle', function (e) {
    e.preventDefault();
    e.stopPropagation();
    let dd = bootstrap.Dropdown.getInstance(this); // Si ya existe
    if (!dd) {
        dd = new bootstrap.Dropdown(this); // Si no existe, créalo
    }
    dd.toggle();
});

// ✅ Inicializa DataTables para todas las tablas con .datatable
function initDataTables() {
    $('.datatable').each(function () {
        // 🛠️ Evita reinit si ya está inicializada
        if ($.fn.DataTable.isDataTable(this)) {
            $(this).DataTable().destroy();
        }
        $(this).DataTable({
            responsive: true,
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Excel',
                    title: document.title || 'Exportación',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'pdfHtml5',
                    text: 'PDF',
                    title: document.title || 'Exportación',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'print',
                    text: 'Imprimir',
                    title: document.title || 'Exportación',
                    exportOptions: { columns: ':visible' }
                }
            ],
            language: {
                decimal: "",
                emptyTable: "No hay información",
                info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                infoEmpty: "Mostrando 0 a 0 de 0 registros",
                infoFiltered: "(Filtrado de _MAX_ total registros)",
                lengthMenu: "Mostrar _MENU_ registros",
                loadingRecords: "Cargando...",
                processing: "Procesando...",
                search: "Buscar:",
                zeroRecords: "No se encontraron resultados",
                paginate: {
                    first: "Primero",
                    last: "Último",
                    next: "Siguiente",
                    previous: "Anterior"
                },
                buttons: {
                    excel: "Excel",
                    pdf: "PDF",
                    print: "Imprimir"
                }
            }
        });
         // 🩹 Agrega id y name al buscador para evitar warnings
        let $inputBuscar = $(this).closest('.dataTables_wrapper').find('div.dataTables_filter input[type="search"]');
        $inputBuscar.attr({
            id: 'inputBuscar_' + $(this).attr('id'),
            name: 'inputBuscar_' + $(this).attr('id')
        });
    });
}

// ✅ Inicializa dropdowns
function initDropdowns() {
    document.querySelectorAll('.dropdown-toggle').forEach(el => {
        if (!bootstrap.Dropdown.getInstance(el)) {
            new bootstrap.Dropdown(el);
        }
    });
}

// ✅ Inicializa al cargar y tras AJAX
$(document).ready(function () {
    initDataTables();
    initDropdowns();
});
$(document).on('ajaxComplete', function () {
    initDataTables();
    initDropdowns();
});





// Destruye todas las instancias de CKEditor
function destroyAllCKEditors() {
    for (let instancia in CKEDITOR.instances) {
        if (CKEDITOR.instances.hasOwnProperty(instancia)) {
            CKEDITOR.instances[instancia].destroy(true);
        }
    }
}

// Inicializa CKEditor si no existe y el textarea está visible
function inicializarEditorContenido() {
    $('[data-editor="ckeditor"]').each(function () {
        let id = $(this).attr('id');
        let $el = $(this);

        if (!id || !$el.length || !$el.is(':visible')) return;

        // 🧹 Asegura limpieza de instancia previa
        if (CKEDITOR.instances[id]) {
            console.log(`⛔ Destruyendo instancia previa: ${id}`);
            CKEDITOR.instances[id].destroy(true);
        }

        // Solo si todavía no existe, creamos
        if (!CKEDITOR.instances[id] && document.getElementById(id)) {
            CKEDITOR.replace(id, {
                height: 300,
                allowedContent: true,
                extraAllowedContent: 'span{*}(*)'
            });
            // console.log(`✅ CKEditor creado para: ${id}`);
            // console.log("Instancias actuales:", CKEDITOR.instances);
        }
    });
}



// Se ejecuta cuando cargas contenido por AJAX
function cargarConEditor(href) {
    destroyAllCKEditors();

    // 🧹 Limpia el contenido anterior antes de cargar uno nuevo
    $('#content').html(''); // o .empty()

    $('#content').load(href, function () {
        setTimeout(() => {
            inicializarEditorContenido();
        }, 300);
    });
}



if ($('#contenido_html').length && !CKEDITOR.instances['contenido_html']) {
    inicializarEditorContenido();
}
