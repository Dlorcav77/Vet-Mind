<?php
###########################################
require_once("../config.php");
date_default_timezone_set('America/Santiago');
###########################################

$mysqli = conn();
$action = $_GET['action'] ?? 'ingresar';

if ($action === "modificar") {
    credenciales('certificado', 'modificar');
    $accion = "Modificar";

    $id = intval($_GET['id']);
    $stmt = $mysqli->prepare("
        SELECT 
            c.*, 
            p.nombre AS paciente, 
            p.especie, 
            p.raza, 
            p.sexo,
            t.nombre_completo AS propietario
        FROM certificados c
        LEFT JOIN pacientes p ON c.paciente_id = p.id
        LEFT JOIN tutores t ON p.tutor_id = t.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $fila = $res->fetch_assoc();

    $imagenesGuardadas = [];
    if (!empty($fila['imagenes_json'])) {
        $lista = json_decode($fila['imagenes_json'], true);
        if (is_array($lista)) {
            foreach ($lista as $nombre) {
                $imagenesGuardadas[] = '/' . ltrim($nombre, '/');
            }
        }
    }

    $mostrarImagenesAntiguas = false;
    if (!empty($fila['fecha_examen'])) {
        $fechaExamen = new DateTime($fila['fecha_examen']);
        $hoy = new DateTime();
        $diff = $hoy->diff($fechaExamen);
        $mostrarImagenesAntiguas = ($diff->days <= 7 && $fechaExamen <= $hoy);
    }
    
} else {
    credenciales('certificado', 'ingresar');
    $accion = "Ingresar";
    $fila = [
        'paciente_id'     => '',
        'tipo_estudio'    => '',
        'fecha_examen'    => date('Y-m-d'),
        'contenido_html'  => '',
        'estado'          => 'pendiente'
    ];
}

$configuracion_informe_id_actual = 0;

$stmtPlantillas = $mysqli->prepare("
    SELECT id, nombre_plantilla, es_predeterminada
    FROM configuracion_informes
    WHERE veterinario_id = ?
    ORDER BY es_predeterminada DESC, nombre_plantilla ASC, id ASC
");
$stmtPlantillas->bind_param("i", $usuario_id);
$stmtPlantillas->execute();
$resPlantillas = $stmtPlantillas->get_result();

$plantillas_diseno = [];
while ($rowPlantilla = $resPlantillas->fetch_assoc()) {
    $plantillas_diseno[] = $rowPlantilla;
}

if ($action === 'modificar' && !empty($fila['configuracion_informe_id'])) {
    $configuracion_informe_id_actual = (int)$fila['configuracion_informe_id'];
} else {
    foreach ($plantillas_diseno as $pl) {
        if ((int)$pl['es_predeterminada'] === 1) {
            $configuracion_informe_id_actual = (int)$pl['id'];
            break;
        }
    }

    if ($configuracion_informe_id_actual === 0 && !empty($plantillas_diseno)) {
        $configuracion_informe_id_actual = (int)$plantillas_diseno[0]['id'];
    }
}
?>
<div class="card" id="certificado" data-page-id="certificado">
    <div class="card-header pb-1">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <h1 class="h3 fw-bold mb-0"><?= $accion; ?> Informe</h1>

            <div class="w-100 w-md-auto" style="max-width: 320px;">
                <select name="configuracion_informe_id" id="configuracion_informe_id" class="form-select">
                    <option value="">Seleccione una plantilla de diseño</option>
                    <?php foreach ($plantillas_diseno as $plantillaDiseno): ?>
                        <option value="<?= (int)$plantillaDiseno['id'] ?>"
                            <?= $configuracion_informe_id_actual === (int)$plantillaDiseno['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($plantillaDiseno['nombre_plantilla']) ?>
                            <?= (int)$plantillaDiseno['es_predeterminada'] === 1 ? ' (Predeterminada)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form method="post" action="certificado/updCertificados.php" enctype="multipart/form-data">
            <div class="row g-1 mb-1">
                <?php include 'pacientes/paciente.php'; ?>
                <hr>
                <?php include 'tipo_examen/tipo_examen.php'; ?>
                <hr>
                <div class="bg-light p-2 rounded-4">
                    <?php include 'metodo_ingreso/metodo_ingreso.php'; ?>
                </div>
            </div>
            <input type="hidden" id="plantillaBase" name="plantillaBase" value="">
            <input type="hidden" name="veterinario_id" value="<?= $usuario_id ?>">
            <input type="hidden" name="action" value="<?= $action ?>">
            <?php if ($action === 'modificar'): ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="imagenes_antiguas" id="imagenes_antiguas">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-6 text-start">
                    <button type="button" class="btn btn-primary btn-lg" id="btnGuardarCertificado"><?= $accion ?></button>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" class="btn btn-secondary btn-lg" id="btnVistaPrevia">  
                        <i class="fas fa-eye me-2"></i> Vista previa
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalProcesarIA" tabindex="-1" aria-labelledby="procesarIALabel" >
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-magic"></i> Informe Procesado por IA</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
            <textarea id="editorIA" rows="15"></textarea>
            <div id="debug-host" style="display:none; max-height:60vh; overflow:auto; padding:8px;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="aceptarIA">Aceptar</button>
        </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVistaPrevia" tabindex="-1" aria-labelledby="vistaPreviaLabel" >
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="vistaPreviaLabel">Vista Previa del Certificado</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body p-0" style="background: #eee;">
            <div id="contenidoVistaPrevia" style="min-height:60vh;padding:0;background:#fff;"></div>
        </div>
        </div>
    </div>
</div>


<!-- <script src="../assets/ckeditor/ckeditor.js"></script> -->
<script>
    (function () {
        // solo la defino si no existe
        if (typeof window.ES_MODIFICAR === 'undefined') {
        window.ES_MODIFICAR = <?= $action === 'modificar' ? 'true' : 'false' ?>;
        } else {
        // si ya existe, la actualizamos igual por si cambiaste de ingresar a modificar
        window.ES_MODIFICAR = <?= $action === 'modificar' ? 'true' : 'false' ?>;
        }
    })();
</script>

<script>




$('#procesarIA').on('click', function () {
    let $btnProcesar = $(this);

    // validar datos paciente
    let pacienteData = obtenerDatosPaciente();
    if (!pacienteData) {
        Swal.fire('Tipo de Examen requerido', 'Debes seleccionar un tipo de examen antes de procesar.', 'warning');
        return;
    }

    $btnProcesar.prop('disabled', true);

    let tipoExamen = $('select[name="plantilla_informe_id"]').val();
    if (!tipoExamen) {
        Swal.fire('Tipo de Examen requerido', 'Debes seleccionar un tipo de examen antes de procesar.', 'warning');
        $btnProcesar.prop('disabled', false);
        return;
    }

    if (window.recorder && window.recorder.state === 'recording') {
        Swal.fire('Espera', 'Termina la grabación antes de procesar.', 'info');
        $btnProcesar.prop('disabled', false);
        return;
    }

    let esManual = $('#toggle_audio_manual').prop('checked');

    // ---------- MODO MANUAL ----------
    if (esManual) {
        let texto = '';
        if (CKEDITOR.instances['contenido_html']) {
            texto = (CKEDITOR.instances['contenido_html'].getData() || '').trim();
        } else {
            texto = $('#contenido_html').val().trim();
        }

        if (texto.length < 5) {
            Swal.fire('Error', 'Debes ingresar un texto antes de procesar.', 'warning');
            $btnProcesar.prop('disabled', false);
            return;
        }

        // aquí no mostramos swal largo, porque es rápido
        procesarTextoConGPT(texto).finally(() => {
            $btnProcesar.prop('disabled', false);
        });
        return;
    }

    // ---------- MODO AUDIO (2 pasos) ----------
    let audioFile = $('input[name="archivo_audio"]')[0].files[0];
    let audioFilename = $('#bloque-audio').data('audioFilename');

    if (!audioFile && !audioFilename) {
        Swal.fire('Error', 'Debes subir o grabar un audio antes de procesar.', 'warning');
        $btnProcesar.prop('disabled', false);
        return;
    }

    // 🟣 UN SOLO MODAL PARA TODO
    Swal.fire({
        title: 'Procesando con Vet-Mind...',
        html: 'Transcribiendo tu audio, espera un momento.',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    let formData = new FormData();
    if (audioFile) {
        formData.append('audio', audioFile);
    } else {
        formData.append('audio_filename', audioFilename);
    }
    for (const key in pacienteData) {
        formData.append(key, pacienteData[key]);
    }

    // 1) transcribir
    fetch('/funciones/GPT/transcribir_audio.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.status !== 'success') {
            throw new Error(resp.message || 'Error al transcribir.');
        }

        const textoTranscrito = (resp.texto || '').trim();
        if (!textoTranscrito) {
            throw new Error('La transcripción volvió vacía.');
        }

        // 🔁 actualizamos el MISMO modal y volvemos a poner el loader
        Swal.update({
            title: 'Procesando con Vet-Mind...',
            html: 'Generando el informe con la plantilla seleccionada...',
            showConfirmButton: false,
            allowOutsideClick: false
        });
        Swal.showLoading();   // 👈 este era el que faltaba

        let plantillaBase = $('#plantillaBase').val();
        let plantillaId = $('select[name="plantilla_informe_id"]').val();

        return $.post('/funciones/GPT/proceso_gpt.php', {
            texto: textoTranscrito,
            plantilla_base: plantillaBase,
            plantilla_id: plantillaId,
            ...pacienteData
        }, null, 'json');
    })
    .then(respGPT => {
        Swal.close();
        if (respGPT.status === 'success') {
            mostrarModalIA(respGPT.content);
        } else if (respGPT.status === 'dry_run') {
            const html = respGPT.debug_html || respGPT.content_demo || '<p><strong>DEBUG:</strong> Dry-run activo.</p>';
            mostrarModalDebug(html);
        } else {
            Swal.fire('Error', respGPT.message || 'Fallo al procesar con GPT.', 'error');
        }
    })
    .catch(err => {
        Swal.close();
        Swal.fire('Error', err.message || 'No se pudo procesar el audio.', 'error');
    })
    .finally(() => {
        $btnProcesar.prop('disabled', false);
    });


});







function procesarTextoConGPT(texto) {
    let pacienteData = obtenerDatosPaciente();
    if (!pacienteData) {
        Swal.fire('Datos del paciente requeridos', 'Debes ingresar o seleccionar un paciente con todos los datos completos antes de procesar.', 'warning');
        // OJO: aquí no hay un reject definido, así que esto no hace nada. Mejor solo return;
        return;
    }
    return new Promise((resolve, reject) => {
        Swal.fire({
            title: 'Procesando...',
            text: 'Generando informe...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        let plantillaBase = $('#plantillaBase').val();
        let plantillaId = $('select[name="plantilla_informe_id"]').val();
        let pacienteData = obtenerDatosPaciente();

        $.post('/funciones/GPT/proceso_gpt.php', { 
            texto: texto,
            plantilla_base: plantillaBase,
            plantilla_id: plantillaId,
            ...pacienteData 
        }, function (response) {
            console.log("Respuesta OK:", response);
            Swal.close();
            if (response.status === 'success') {
                mostrarModalIA(response.content);
                resolve(response);
                } else if (response.status === 'dry_run') {
                const html = response.debug_html || response.content_demo || '<p><strong>DEBUG:</strong> Dry-run activo.</p>';
                mostrarModalDebug(html);           // 👈 usa una función que NO use CKEditor
                console.log('DRY-RUN input:', response.input);
                console.log('DRY-RUN payload:', response.openai_payload);
                resolve(response);
                }
                else {
                Swal.fire('Error', response.message || 'Fallo al procesar.', 'error');
                reject(response);
            }
        }, 'json')
        .fail(function (xhr, status, error) {
            Swal.close();
            console.log("XHR fail:", xhr);
            console.log("Status:", status);
            console.log("Error:", error);
            // También puedes ver el responseText
            if (xhr && xhr.responseText) {
                console.log("Respuesta cruda:", xhr.responseText);
            }
            Swal.fire('Error', 'No se pudo conectar al servicio GPT.', 'error');
            reject(error);
        });

    });
}
function mostrarModalIA(content) {
    const $modal = $('#modalProcesarIA');

    $modal.off('shown.bs.modal').on('shown.bs.modal', function () {
        // 🧹 Destruye si existe
        if (CKEDITOR.instances['editorIA']) {
            CKEDITOR.instances['editorIA'].destroy(true);
        }

        // 🪄 Reemplaza y luego setea el contenido
        CKEDITOR.replace('editorIA', {
            height: 400,
            allowedContent: true,
            extraAllowedContent: 'span{*}(*)'
        });

        // Espera un poco para evitar que CKEditor no esté listo
        setTimeout(() => {
            if (CKEDITOR.instances['editorIA']) {
                CKEDITOR.instances['editorIA'].setData(content);
            }
        }, 100);
    });

    $modal.off('hidden.bs.modal').on('hidden.bs.modal', function () {
        if (CKEDITOR.instances['editorIA']) {
            CKEDITOR.instances['editorIA'].destroy(true);
        }
    });

    $modal.modal('show');
}
function mostrarModalDebug(html) {
  const $modal = $('#modalProcesarIA');

  // Destruye CKEditor si estaba activo
  if (CKEDITOR.instances['editorIA']) {
    CKEDITOR.instances['editorIA'].destroy(true);
  }

  // Oculta SOLO el textarea, NO la .modal-body
  $modal.find('#editorIA').hide();

  // Crea (o reutiliza) el host para debug
  let $host = $modal.find('#debug-host');
  if (!$host.length) {
    $host = $('<div id="debug-host" style="max-height:60vh; overflow:auto; padding:8px;"></div>');
    // lo insertamos justo después del textarea
    $modal.find('#editorIA').after($host);
  }

  // Inserta el HTML de debug y muéstralo
  $host.html(html).show();

  // Limpieza al cerrar: vaciar host y volver a mostrar el textarea
  $modal.off('hidden.bs.modal').on('hidden.bs.modal', function () {
    $host.empty().hide();
    $modal.find('#editorIA').show();
  });

  $modal.modal('show');
}





// Botón Aceptar del Modal
$('#aceptarIA').on('click', function () {
    let textoIA = CKEDITOR.instances['editorIA'].getData();

    // Mostrar el bloque manual y ocultar audio
    // $('#bloque-audio').slideUp();
    // $('#bloque-manual').slideDown();
    // $('#metodoSwitch').prop('checked', false);
    // $('#metodoLabel').text('Escribir manualmente');
    audio_manual_setMode('manual');


    destroyAllCKEditors();

    inicializarEditorContenido();

    // 🔥 Limpiar contenido HTML del textoIA
    textoIA = textoIA
    .replace(/<span[^>]*style=['"]?color:(orange|blue);?['"]?[^>]*>(.*?)<\/span>/gi, '$2')
    .replace(/(?:<[^>]+>)?Observaciones del Asistente:?<\/?.*?>?(?:<br\s*\/?>)?[\s\S]*$/i, '')
    .replace(/\s*\(\d+\)/g, '')
    // Reformatear sección CONCLUSION:
    .replace(/CONCLUSION:\s*((?:- .*?\.)(?:\s*- .*?\.)*)/i, function(match, contenido) {
        // Separa por " - " y vuelve a unir con saltos y tabulación
        const lineas = contenido
            .split(/\s*-\s+/)
            .filter(Boolean)
            .map(l => '&nbsp;&nbsp;- ' + l.trim() + '<br>')
            .join('');
        return 'CONCLUSION:<br>' + lineas;
    });


    // Aplicar el texto procesado
    CKEDITOR.instances['contenido_html'].setData(textoIA);

    $('#modalProcesarIA').modal('hide');
    Swal.fire('Éxito', 'El contenido procesado ha sido aplicado.', 'success');
});


function obtenerDatosPaciente() {
  const esManual = $('#toggle_manual').prop('checked');
  const datos = {};

  if (esManual) {
    // inputs manual_*
    $('input[name^="manual_"]').each(function () {
      const nombre = this.name.replace('manual_', '');
      datos[nombre] = ($(this).val() || '').trim();
    });

    // selects manuales
    const sexoVal = ($('#manual_sexo').val() || '').trim();
    if (sexoVal) datos['sexo'] = sexoVal;
  } else {
    // Modo "buscar paciente"
    datos['paciente']          = ($('#paciente_seleccionado').val() || '').trim();
    datos['especie']           = ($('#paciente_seleccionado').data('especie') || '').trim();
    datos['raza']              = ($('#paciente_seleccionado').data('raza') || '').trim();
    datos['fecha_nacimiento']  = ($('#paciente_seleccionado').data('fecha_nacimiento') || '').trim();
    datos['sexo']              = ($('#paciente_seleccionado').data('sexo') || '').trim();
  }

  // Tipo de examen (obligatorio para procesar IA)
  const tipo_examen = ($('select[name="plantilla_informe_id"] option:selected').text() || '').trim();
  datos['tipo_estudio'] = tipo_examen;

  datos['motivo_examen'] = ($('#motivo_examen').val() || '').trim();

  if (!tipo_examen || tipo_examen === 'Seleccione una plantilla') {
    return null;
  }
  return datos;
}




$('#btnVistaPrevia').on('click', function() {
    let esManual = $('#toggle_manual').is(':checked');

    let configuracionInformeId = $('#configuracion_informe_id').val() || '';
    if (!configuracionInformeId) {
        Swal.fire('Falta Plantilla', 'Debes seleccionar una plantilla de diseño.', 'warning');
        return;
    }

    if (!esManual) {
        let pacienteId = $('input[name="paciente_id"]').val() || 0;
        if (!pacienteId) {
            Swal.fire('Falta Paciente', 'Debes seleccionar un paciente.', 'warning');
            return;
        }
    } else {
        if (!validarPacienteManualUI()) {
            Swal.fire('Falta Paciente', 'Completa los datos del paciente manual antes de continuar.', 'warning');
            return;
        }
    }

    if (CKEDITOR.instances['contenido_html']) {
        if ($('#contenido_html').length > 0) {
            CKEDITOR.instances['contenido_html'].updateElement();
        }
    }
    let contenido = $('textarea[name="contenido_html"]').val()?.trim() || '';
    if (contenido.length < 5) {
        Swal.fire('Falta Contenido', 'El informe debe tener contenido.', 'warning');
        return;
    }

    let form = $('form')[0];
    let formData = new FormData(form);

    Swal.fire({
        title: 'Generando vista previa...',
        text: 'Por favor espera unos segundos.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.ajax({
        url: 'certificado/previewPDF.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(pdfUrl) {
            Swal.close();
            // console.log("URL PDF preview:", pdfUrl);
            $('#contenidoVistaPrevia').html('<iframe src="'+pdfUrl+'" style="width:100%;height:80vh;border:none;"></iframe>');
            $('#modalVistaPrevia').modal('show');
            window.nombreTempPDF = pdfUrl.split('/').pop(); 
        },
        error: function(xhr) {
            Swal.close();
            Swal.fire('Error', 'No se pudo generar la vista previa del PDF.', 'error');
        }
    });
});

$('#btnGuardarCertificado').on('click', function(e) {
    e.preventDefault();

    // Validaciones igual que en vista previa...
    let esManual = $('#toggle_manual').is(':checked');

    let configuracionInformeId = $('#configuracion_informe_id').val() || '';
    if (!configuracionInformeId) {
        Swal.fire('Falta Plantilla', 'Debes seleccionar una plantilla de diseño.', 'warning');
        return;
    }

    if (!esManual) {
        // Validación normal (modo buscar paciente)
        let pacienteId = $('input[name="paciente_id"]').val() || 0;
        if (!pacienteId) {
            Swal.fire('Falta Paciente', 'Debes seleccionar un paciente.', 'warning');
            return;
        }
    } else {
    if (!validarPacienteManualUI()) {
        Swal.fire('Falta Información', 'Completa todos los datos del paciente manual.', 'warning');
        return;
    }
    }

    if (archivosSeleccionados.length > LIMITE_IMAGENES) {
        Swal.fire({
            icon: 'warning',
            title: 'Demasiadas imágenes',
            html: 'Se pueden subir como máximo <b>' + LIMITE_IMAGENES + '</b> imágenes.<br>Elimina <b>' + (archivosSeleccionados.length - LIMITE_IMAGENES) + '</b> para poder guardar el informe.',
            confirmButtonText: 'Entendido',
            customClass: {
                title: 'fw-bold',
                popup: 'shadow rounded-4'
            }
        });
        return;
    }


    let plantillaId = $('select[name="plantilla_informe_id"]').val() || '';
    // if (!plantillaId) {
    //     Swal.fire('Falta Plantilla', 'Debes seleccionar un tipo de examen.', 'warning');
    //     return;
    // }

    if (CKEDITOR.instances['contenido_html']) {
        if ($('#contenido_html').length > 0) {
            CKEDITOR.instances['contenido_html'].updateElement();
        }
    }
    let contenido = $('textarea[name="contenido_html"]').val()?.trim() || '';
    if (contenido.length < 5) {
        Swal.fire('Falta Contenido', 'El informe debe tener contenido.', 'warning');
        return;
    }

    let form = $('form')[0];
    let formData = new FormData(form);

    if ($('#guardarMascota').is(':checked')) {
        formData.append('guardar_mascota', '1');
    }

    Swal.fire({
        title: 'Guardando certificado...',
        text: 'Por favor espera unos segundos.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.ajax({
        url: 'certificado/updCertificados.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            // console.log(response);
            Swal.close();
            if (response.status === 'success') {
               let certId = response.id || 0;
                if (certId) {
                    // inline en pestaña nueva (nombre bonito)
                    window.open('certificado/descargar.php?id=' + encodeURIComponent(certId), '_blank');
                } else {
                    // fallback por si algo vino sin id
                    let rutaPdf = response.rutaPdf || null;
                    if (rutaPdf) {
                        let urlPdf = rutaPdf.startsWith('/') ? rutaPdf : '/' + rutaPdf;
                        window.open(urlPdf, '_blank');
                    }
                }
                if (CKEDITOR.instances['contenido_html']) {
                    CKEDITOR.instances['contenido_html'].destroy(true);
                }

                // 👇 AQUI CARGAS LA NUEVA VISTA Y REINICIALIZAS CKEDITOR
                $('#content').load('certificado/lisCertificados.php', function() {
                    inicializarEditorContenido(); 
                });

            } else {
                Swal.fire('Error', response.message || 'No se pudo guardar el certificado.', 'error');
            }
        },
        error: function(xhr) {
            Swal.close();
            let msg = 'No se pudo guardar el certificado.';
            if (xhr.responseText) {
                try {
                    let res = JSON.parse(xhr.responseText);
                    if (res.message) msg += "\n" + res.message;
                    if (res.mysql_error) msg += "\n" + res.mysql_error;
                } catch(e) {
                    msg += "\n" + xhr.responseText;
                }
            }
            Swal.fire('Error', msg, 'error');
            console.error('AJAX error:', xhr);
        }
    });
});

$('#modalVistaPrevia').on('hidden.bs.modal', function () {
    if (window.nombreTempPDF) {
        $.ajax({
            url: 'certificado/tipo_examen/eliminar_temp_pdf.php', // 👈 Nuevo PHP
            type: 'POST',
            data: { pdf: window.nombreTempPDF },
            success: function (res) {
                // console.log('PDF temporal eliminado:', res);
                window.nombreTempPDF = null; // 🧹 Limpia variable
            },
            error: function () {
                console.error('Error al eliminar PDF temporal');
            }
        });
    }
});



function validarPacienteManualUI() {
  const okPaciente = !!($('input[name="manual_paciente"]').val() || '').trim();
  const okRaza     = !!($('input[name="manual_raza"]').val() || '').trim();     // ← hidden
  const okSexo     = !!($('#manual_sexo').val() || '').trim();
  const okEspecie  = !!($('input[name="manual_especie"]').val() || '').trim();  // ← hidden
  const okFecha    = true;
  return okPaciente && okRaza && okSexo && okEspecie && okFecha;
}



</script>
