window.VETMIND_DATATABLE_LANGUAGE = {
    decimal: "",
    emptyTable: "No hay información",
    info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
    infoEmpty: "Mostrando 0 a 0 de 0 registros",
    infoFiltered: "(Filtrado de _MAX_ total registros)",
    lengthMenu: "Mostrar _MENU_ registros",
    loadingRecords: "Cargando...",
    processing: "Procesando...",
    search: "",
    searchPlaceholder: "Buscar...",
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
};

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

        // Si ya está inicializada, conservamos su estado actual:
        // búsqueda, página, orden y filtros.
        if ($.fn.DataTable.isDataTable(this)) {
            return;
        }

        const $tabla = $(this);

        const columnaExportable = function (indice, data, nodo) {
            const titulo = $(nodo)
                .text()
                .trim()
                .toLowerCase();

            return titulo !== 'acciones';
        };

        $tabla.find('thead th').each(function (indice) {
            const titulo = $(this).text().trim().toLowerCase();

            if (titulo === 'acciones') {
                $(this).addClass('dt-col-acciones');

                $tabla.find('tbody tr').each(function () {
                    $(this)
                        .children('td')
                        .eq(indice)
                        .addClass('dt-col-acciones');
                });
            }
        });

        $(this).DataTable({
            responsive: true,
            dom:
            '<"dt-toolbar"Bf>'
            + 'rt'
            + '<"dt-footer"ip>',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel me-1"></i> Excel',
                    title: document.title || 'Exportación',

                    exportOptions: {
                        columns: columnaExportable
                    }
                },

                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                    title: document.title || 'Exportación',

                    exportOptions: {
                        columns: columnaExportable
                    }
                },

                {
                    extend: 'print',
                    text: '<i class="fas fa-print me-1"></i> Imprimir',
                    title: document.title || 'Exportación',

                    exportOptions: {
                        columns: columnaExportable
                    }
                }
            ],
            language: window.VETMIND_DATATABLE_LANGUAGE
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

function inicializarEditorContenido() {}

// Carga contenido por AJAX (sin CKEditor)
function cargarConEditor(href) {
    $('#content').html('');
    $('#content').load(href);
}