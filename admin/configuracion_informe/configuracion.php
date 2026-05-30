<?php
// admin/configuracion_informe/configuracion.php
###########################################
require_once("../config.php");
###########################################

$mysqli = conn();
$action = $_GET['action'] ?? 'ingresar';
$accion = ($action === 'modificar') ? 'Modificar' : 'Configurar';
$configuracion_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === "modificar") {
    credenciales('configuracion_informe', 'modificar');

    if ($configuracion_id <= 0) {
        die("Plantilla no válida.");
    }

    $stmt = $mysqli->prepare("
        SELECT *
        FROM configuracion_informes
        WHERE id = ? AND veterinario_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $configuracion_id, $_SESSION['usuario_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $fila = $res->fetch_assoc();

    if (!$fila) {
        die("Plantilla no encontrada.");
    }
} else {
    credenciales('configuracion_informe', 'ingresar');

    $fila = [
        'nombre_plantilla'     => 'Nueva plantilla',
        'logo_url'             => '',
        'logo_position'        => 'center',
        'marca_agua_url'       => '',
        'mostrar_marca_agua'   => 0,
        'color_primario'       => '#3498db',
        'color_secundario'     => '#2ecc71',
        'footer_texto'         => '',
        'footer_align'         => 'center',
        'firma_nombre'         => '',
        'firma_titulo'         => '',
        'firma_subtitulo'      => '',
        'mostrar_fecha'        => 1,
        'formato_fecha'        => '{{day}} de {{month}} del {{year}}',
        'fecha_align'          => 'right',
        'logo_size'            => 'medium',
        'marca_agua_size'      => 'medium',
        'imagenes_por_fila'    => '2',
        'titulo_informe'       => 'INFORME ECOGRÁFICO',
        'subtitulo'            => '',
        'subtitulo_align'      => 'center',
        'mostrar_firma_imagen' => 0,
        'firma_imagen_url'     => '',
        'lugar_fecha'          => '',
        'es_predeterminada'    => 0,
        'layout_tipo'          => 'clasico',
        'layout_config_json'   => null
    ];
}

// Cargar todos los campos permitidos activos
$campos_permitidos = [];
$res = $mysqli->query("SELECT id, etiqueta FROM campos_permitidos WHERE activo = 1 ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    $campos_permitidos[$row['id']] = $row['etiqueta'];
}

// Cargar campos configurados solo si es modificar
$campos_configurados = [];
if ($action === 'modificar') {
    $stmt_campos = $mysqli->prepare("
        SELECT cic.id, cic.campo_id, cp.etiqueta, cic.visible, cic.orden
        FROM configuracion_informe_campos cic
        JOIN campos_permitidos cp ON cic.campo_id = cp.id
        WHERE cic.configuracion_informe_id = ?
        ORDER BY cic.orden ASC, cic.id ASC
    ");
    $stmt_campos->bind_param("i", $configuracion_id);
    $stmt_campos->execute();
    $result_campos = $stmt_campos->get_result();

    while ($campo = $result_campos->fetch_assoc()) {
        $campos_configurados[] = $campo;
    }
}

$campos_fijos_ids = [1, 5];

$campos_fijos_map = [
    1 => 'Paciente',
    5 => 'Propietario'
];

$campos_configurados_por_campo_id = [];
foreach ($campos_configurados as $campoCfg) {
    $campos_configurados_por_campo_id[(int)$campoCfg['campo_id']] = $campoCfg;
}

foreach ($campos_fijos_ids as $campoFijoId) {
    if (!isset($campos_configurados_por_campo_id[$campoFijoId])) {
        $campos_configurados[] = [
            'id'       => 'fijo-' . $campoFijoId,
            'campo_id' => $campoFijoId,
            'etiqueta' => $campos_fijos_map[$campoFijoId] ?? ('Campo ' . $campoFijoId),
            'visible'  => 1,
            'orden'    => 0
        ];
    }
}

usort($campos_configurados, function ($a, $b) {
    return ((int)($a['orden'] ?? 0)) <=> ((int)($b['orden'] ?? 0));
});

$subtitulos = [];

if (!empty($fila['firma_subtitulo'])) {
    $decoded_subtitulos = json_decode($fila['firma_subtitulo'], true);

    if (is_array($decoded_subtitulos)) {
        $subtitulos = array_filter(array_map('trim', $decoded_subtitulos));
    } else {
        $texto_subtitulo = trim((string)$fila['firma_subtitulo']);

        if ($texto_subtitulo !== '') {
            $subtitulos[] = $texto_subtitulo;
        }
    }
}

$layout_config = [];

if (!empty($fila['layout_config_json'])) {
    $decoded_layout_config = json_decode($fila['layout_config_json'], true);

    if (is_array($decoded_layout_config)) {
        $layout_config = $decoded_layout_config;
    }
}

$clinica_config_default = [
    'institucion_nombre' => 'Instituto Neurológico Veterinario',
    'direccion'          => 'Pepe Vila #25, La Reina, Santiago, Chile',
    'telefonos'          => '22 356 39 89 - 22 356 39 90',
    'correo'             => 'contacto@institutoneurologico.cl',
    'web'                => 'inev.cl'
];

$clinica_config = array_merge(
    $clinica_config_default,
    is_array($layout_config['clinica'] ?? null) ? $layout_config['clinica'] : []
);

?>
<link rel="stylesheet" href="configuracion_informe/css/configuracion_informe.css?v=<?= time() ?>">
<div class="configuracion-informe-wrapper" id="configuracion_informe" data-page-id="configuracion_informe">
  <div class="config-toolbar">
    <div class="config-toolbar-title">
      <?= htmlspecialchars($accion) ?>
    </div>

    <div class="config-toolbar-controls">
      <div class="config-toolbar-field config-toolbar-name">
        <label for="nombre_plantilla" class="form-label mb-1">Nombre</label>
        <input
          type="text"
          class="form-control"
          name="nombre_plantilla"
          id="nombre_plantilla"
          form="configuracion_informe_form"
          maxlength="150"
          value="<?= htmlspecialchars($fila['nombre_plantilla'] ?? '') ?>"
          placeholder="Ej: Ecografía abdominal felina"
        >
      </div>

      <div class="config-toolbar-field config-toolbar-layout">
        <label for="layout_tipo" class="form-label mb-1">Plantilla</label>
        <select
          name="layout_tipo"
          id="layout_tipo"
          class="form-select select2"
          form="configuracion_informe_form"
        >
          <option value="clasico" <?= ($fila['layout_tipo'] ?? 'clasico') === 'clasico' ? 'selected' : '' ?>>
            Clásico
          </option>
          <option value="clinica" <?= in_array(($fila['layout_tipo'] ?? 'clasico'), ['clinica', 'inev'], true) ? 'selected' : '' ?>>
            Clínica
          </option>
        </select>
      </div>



      <div class="config-toolbar-field config-toolbar-colores">
        <input
          type="color"
          class="color-input-hidden"
          name="color_primario"
          id="color_primario"
          form="configuracion_informe_form"
          value="<?= htmlspecialchars($fila['color_primario'] ?? '#3498db') ?>"
          title="Elige un color primario"
        >

        <input
          type="color"
          class="color-input-hidden"
          name="color_secundario"
          id="color_secundario"
          form="configuracion_informe_form"
          value="<?= htmlspecialchars($fila['color_secundario'] ?? '#2ecc71') ?>"
          title="Elige un color secundario"
        >

        <label class="form-label mb-1 d-block">Colores</label>

        <div class="config-toolbar-colores-buttons">
          <button
            type="button"
            class="color-mini-button"
            id="btn_color_primario"
            data-color-target="color_primario"
            style="background-color: <?= htmlspecialchars($fila['color_primario'] ?? '#3498db'); ?>;"
            title="Cambiar color primario"
          ></button>

          <button
            type="button"
            class="color-mini-button"
            id="btn_color_secundario"
            data-color-target="color_secundario"
            style="background-color: <?= htmlspecialchars($fila['color_secundario'] ?? '#2ecc71'); ?>;"
            title="Cambiar color secundario"
          ></button>
        </div>
      </div>
      <div class="config-toolbar-field config-toolbar-default">
        <label class="form-label mb-1 d-block">Por Defecto</label>
        <div class="form-check form-switch mb-1">
          <input
            class="form-check-input"
            type="checkbox"
            name="es_predeterminada"
            id="es_predeterminada"
            form="configuracion_informe_form"
            value="1"
            <?= !empty($fila['es_predeterminada']) ? 'checked' : '' ?>
          >
        </div>
      </div>

      <div class="config-toolbar-field config-toolbar-preview">
        <label class="form-label mb-1 d-block">&nbsp;</label>
        <button
          type="button"
          class="btn btn-outline-primary btn-preview-circle"
          id="btn-vista-previa-plantilla"
          title="Vista previa"
          aria-label="Vista previa"
        >
          <i class="fas fa-eye"></i>
        </button>
      </div>
    </div>
  </div>

  <div class="configuracion-informe-body">
    <form id="configuracion_informe_form" method="post" action="configuracion_informe/updConfiguracion.php" enctype="multipart/form-data">
      <ul class="nav nav-tabs mb-3" id="configTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
            General
          </button>
        </li>

        <li class="nav-item" role="presentation">
          <button class="nav-link" id="campos-tab" data-bs-toggle="tab" data-bs-target="#campos" type="button" role="tab">
            Campos
          </button>
        </li>

        <li class="nav-item" role="presentation">
          <button class="nav-link" id="firma-tab" data-bs-toggle="tab" data-bs-target="#firma" type="button" role="tab">
            Firma y Cierre
          </button>
        </li>

        <?php
          $mostrar_tab_layout_config = in_array(($fila['layout_tipo'] ?? 'clasico'), ['clinica', 'inev'], true);
        ?>

        <li class="nav-item <?= $mostrar_tab_layout_config ? '' : 'd-none' ?>" role="presentation">
          <button class="nav-link" id="layout-config-tab" data-bs-toggle="tab" data-bs-target="#layout-config" type="button" role="tab">
            Clinica
          </button>
        </li>
      </ul>

      <div class="tab-content" id="configTabsContent">





        <?php include __DIR__ . '/tabs/general.php'; ?>

        <?php include __DIR__ . '/tabs/campos.php'; ?>

        <?php include __DIR__ . '/tabs/firma.php'; ?>

        <?php include __DIR__ . '/tabs/clinica.php'; ?>


      <?php if ($action === 'modificar'): ?>
        <input type="hidden" name="id" value="<?= (int)$fila['id'] ?>">
      <?php endif; ?>

      <input type="hidden" name="action" value="<?= $action ?>">

      <button type="submit" class="btn btn-primary mt-3"><?= $accion ?></button>
    </form>
  </div>
</div>
<div class="modal fade" id="modalVistaPreviaPlantilla" tabindex="-1" aria-labelledby="modalVistaPreviaPlantillaLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content modal-preview-plantilla">
      <div class="modal-header">
        <h5 class="modal-title" id="modalVistaPreviaPlantillaLabel">
          <i class="fas fa-eye me-2"></i> Vista previa de plantilla
        </h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div id="vista-previa-plantilla-loading" class="text-center py-5 d-none">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
          </div>
          <div class="mt-2 text-muted">Generando vista previa...</div>
        </div>

        <div id="vista-previa-plantilla-contenido"></div>
      </div>
    </div>
  </div>
</div>
<script src="configuracion_informe/js/configuracion_informe.js?v=<?= time() ?>"></script>