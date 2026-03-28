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
        'es_predeterminada'    => 0
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

$subtitulos = [];
if (!empty($fila['firma_subtitulo'])) {
    $decoded = json_decode($fila['firma_subtitulo'], true);
    if (is_array($decoded)) {
        $subtitulos = $decoded;
    } else {
        $subtitulos = [$fila['firma_subtitulo']];
    }
}
if (empty($subtitulos)) {
    $subtitulos[] = '';
}
?>
<style>
  .text-right h4, .text-right p, .text-right small {
    text-align: right;
  }
  #vista-previa-campos th,
  #vista-previa-campos td {
      padding: 2px 4px;
  }
  .select2-container--default .select2-results__option {
      color: #000;
      font-weight: 500;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered {
      color: #000;
      font-weight: 500;
  }
  #campo-select,
  #campo-select option {
      color: #000 !important;
      font-weight: 600;
  }
</style>

<div class="card" id="configuracion_informe" data-page-id="configuracion_informe">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><strong><?= $accion ?> Configuración de Informes</strong></h1>
    <a href="configuracion_informe/lisConfiguracion.php" class="btn btn-outline-secondary ajax-link">
      <i class="fas fa-arrow-left"></i> Volver
    </a>
  </div>

  <div class="card-body">
    <form method="post" action="configuracion_informe/updConfiguracion.php" enctype="multipart/form-data">
      <ul class="nav nav-tabs mb-3" id="configTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="colores-tab" data-bs-toggle="tab" data-bs-target="#colores" type="button" role="tab">Colores</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="campos-tab" data-bs-toggle="tab" data-bs-target="#campos" type="button" role="tab">Campos</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="firma-tab" data-bs-toggle="tab" data-bs-target="#firma" type="button" role="tab">Firma</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="footer-tab" data-bs-toggle="tab" data-bs-target="#footer" type="button" role="tab">Pie de Pagina</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="fecha-tab" data-bs-toggle="tab" data-bs-target="#fecha" type="button" role="tab">Fecha</button>
        </li>
      </ul>

      <div class="tab-content" id="configTabsContent">
        <div class="tab-pane fade show active" id="general" role="tabpanel">
          <div class="row mb-3 align-items-start">
            <div class="col-md-8 mb-3">
              <label for="nombre_plantilla" class="form-label">Nombre de la Plantilla</label>
              <input
                type="text"
                class="form-control"
                name="nombre_plantilla"
                id="nombre_plantilla"
                maxlength="150"
                value="<?= htmlspecialchars($fila['nombre_plantilla'] ?? '') ?>"
                placeholder="Ej: Ecografía abdominal felina"
              >
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label d-block">Plantilla predeterminada</label>
              <div class="form-check form-switch mt-2">
                <input
                  class="form-check-input"
                  type="checkbox"
                  name="es_predeterminada"
                  id="es_predeterminada"
                  value="1"
                  <?= !empty($fila['es_predeterminada']) ? 'checked' : '' ?>
                >
                <label class="form-check-label" for="es_predeterminada">
                  Usar como predeterminada
                </label>
              </div>
            </div>
            <div class="col-md-6 border-end pe-3 d-flex flex-column justify-content-between">
              <h5 class="fw-bold my-3"><i class="fas fa-image me-1"></i>Logo</h5>
              <div class="row">
                <div class="col-md-6 mb-2">
                  <label for="logo_position" class="form-label">Posición</label>
                  <select id="logo_position" name="logo_position" class="form-select select2">
                    <option value="left" <?= ($fila['logo_position'] ?? 'center') === 'left' ? 'selected' : '' ?> data-icon="fas fa-align-left">Izquierda</option>
                    <option value="center" <?= ($fila['logo_position'] ?? 'center') === 'center' ? 'selected' : '' ?> data-icon="fas fa-align-center">Centro</option>
                    <option value="right" <?= ($fila['logo_position'] ?? 'center') === 'right' ? 'selected' : '' ?> data-icon="fas fa-align-right">Derecha</option>
                  </select>
                </div>

                <div class="col-md-6 mb-2">
                  <label for="logo_size" class="form-label">Tamaño</label>
                  <select name="logo_size" class="form-select">
                    <option value="small" <?= ($fila['logo_size'] ?? 'medium') === 'small' ? 'selected' : '' ?>>Chico</option>
                    <option value="medium" <?= ($fila['logo_size'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Mediano</option>
                    <option value="large" <?= ($fila['logo_size'] ?? 'medium') === 'large' ? 'selected' : '' ?>>Grande</option>
                  </select>
                </div>

                <div class="col-md-12 mb-2">
                  <label for="logo" class="form-label">Nuevo</label>
                  <input type="file" class="form-control" name="logo" accept="image/*">
                </div>

                <div class="col-md-12 text-center mt-3">
                  <?php if (!empty($fila['logo_url'])): ?>
                    <div class="border p-2 rounded">
                      <img src="../<?= htmlspecialchars($fila['logo_url']) ?>" alt="Logo Actual" style="max-height:80px;">
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="col-md-6 ps-3 d-flex flex-column justify-content-between">
              <h5 class="fw-bold my-3"><i class="fas fa-tint me-1"></i>Marca de Agua</h5>
              <div class="row">
                <div class="col-md-6 mb-2">
                  <label class="form-label">Mostrar</label><br>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="mostrar_marca_agua" value="1" <?= !empty($fila['mostrar_marca_agua']) ? 'checked' : '' ?>>
                    <label class="form-check-label">Activar</label>
                  </div>
                </div>

                <div class="col-md-6 mb-2">
                  <label for="marca_agua_size" class="form-label">Tamaño</label>
                  <select name="marca_agua_size" class="form-select">
                    <option value="small" <?= ($fila['marca_agua_size'] ?? 'medium') === 'small' ? 'selected' : '' ?>>Chico</option>
                    <option value="medium" <?= ($fila['marca_agua_size'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Mediano</option>
                    <option value="large" <?= ($fila['marca_agua_size'] ?? 'medium') === 'large' ? 'selected' : '' ?>>Grande</option>
                  </select>
                </div>

                <div class="col-md-12 mb-2">
                  <label for="marca_agua" class="form-label">Nuevo</label>
                  <input type="file" class="form-control" name="marca_agua" accept="image/*">
                </div>

                <div class="col-md-12 text-center mt-3">
                  <?php if (!empty($fila['marca_agua_url'])): ?>
                    <div class="border p-2 rounded">
                      <img src="../<?= htmlspecialchars($fila['marca_agua_url']) ?>" alt="Marca de Agua Actual" style="max-height:80px;">
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <hr class="mt-4" style="border-top:1px solid <?= htmlspecialchars($fila['color_secundario'] ?? '#000') ?>;">

            <div class="col-md-12 mb-2">
              <label for="titulo_informe" class="form-label">Título del Informe</label>
              <input type="text" class="form-control" name="titulo_informe" maxlength="150"
                    value="<?= htmlspecialchars($fila['titulo_informe'] ?? 'INFORME ECOGRÁFICO') ?>"
                    placeholder="Ej: INFORME ECOGRÁFICO">
            </div>

            <div class="row mt-4">
              <div class="col-md-8 mb-2">
                <label for="subtitulo" class="form-label">Subtítulo del Informe</label>
                <input type="text" class="form-control" name="subtitulo" maxlength="150"
                      value="<?= htmlspecialchars($fila['subtitulo'] ?? '') ?>"
                      placeholder="Ej: DESCRIPCIÓN ECOGRÁFICA">
              </div>

              <div class="col-md-4 mb-2">
                <label for="subtitulo_align" class="form-label">Alineación del Subtítulo</label>
                <select name="subtitulo_align" id="subtitulo_align" class="form-select select2">
                  <option value="left" data-icon="fas fa-align-left" <?= ($fila['subtitulo_align'] ?? 'center') === 'left' ? 'selected' : '' ?>>Izquierda</option>
                  <option value="center" data-icon="fas fa-align-center" <?= ($fila['subtitulo_align'] ?? 'center') === 'center' ? 'selected' : '' ?>>Centro</option>
                  <option value="right" data-icon="fas fa-align-right" <?= ($fila['subtitulo_align'] ?? 'center') === 'right' ? 'selected' : '' ?>>Derecha</option>
                </select>
              </div>
            </div>

            <hr class="mt-4" style="border-top:1px solid <?= htmlspecialchars($fila['color_secundario'] ?? '#000') ?>;">

            <div class="row mt-4">
              <div class="col-md-6 mb-2">
                <label for="imagenes_por_fila" class="form-label">Cantidad de Imágenes por Fila</label>
                <select name="imagenes_por_fila" class="form-control">
                  <option value="1" <?= ($fila['imagenes_por_fila'] ?? '2') == '1' ? 'selected' : '' ?>>1 imagen por fila</option>
                  <option value="2" <?= ($fila['imagenes_por_fila'] ?? '2') == '2' ? 'selected' : '' ?>>2 imágenes por fila</option>
                  <option value="3" <?= ($fila['imagenes_por_fila'] ?? '2') == '3' ? 'selected' : '' ?>>3 imágenes por fila</option>
                  <option value="4" <?= ($fila['imagenes_por_fila'] ?? '2') == '4' ? 'selected' : '' ?>>4 imágenes por fila</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="campos" role="tabpanel">
          <h5 class="fw-bold my-4"><i class="fas fa-list me-2"></i> Configuración de Campos</h5>
          <p class="text-muted">Agrega, quita o reordena los campos que aparecerán en el bloque de datos del informe.</p>

          <table class="table table-bordered">
            <thead>
              <tr>
                <th>Orden</th>
                <th>Etiqueta</th>
                <th>Visible</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="campos-lista">
              <?php foreach ($campos_configurados as $campo): ?>
                <tr data-id="<?= $campo['id'] ?>" data-campo-id="<?= $campo['campo_id'] ?>">
                  <td class="orden"><i class="fas fa-arrows-alt-v"></i></td>
                  <td><?= htmlspecialchars($campo['etiqueta']) ?></td>
                  <td class="text-center">
                    <input type="checkbox" name="campos[<?= $campo['id'] ?>][visible]" value="1" <?= $campo['visible'] ? 'checked' : '' ?>>
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger eliminar-campo"><i class="fas fa-trash"></i></button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <input type="hidden" name="campos_ids_actuales" id="campos_ids_actuales">
          <input type="hidden" name="campos_orden" id="campos_orden">

          <hr class="mt-4">

          <div class="d-flex align-items-center mb-3">
            <select id="campo-select" class="form-select me-2" style="max-width:300px;">
              <?php foreach ($campos_permitidos as $id => $etiqueta): ?>
                <option value="<?= $id ?>"><?= htmlspecialchars($etiqueta) ?></option>
              <?php endforeach; ?>
            </select>

            <button type="button" id="agregar-campo" class="btn btn-success">
              <i class="fas fa-plus"></i> Agregar Campo
            </button>
          </div>

          <h6 class="mt-4 fw-bold">Vista Previa del Bloque de Datos</h6>
          <div class="border p-3 rounded">
            <table class="table table-bordered mb-0 bg-light">
              <tbody id="vista-previa-campos"></tbody>
            </table>
          </div>
        </div>

        <div class="tab-pane fade" id="colores" role="tabpanel">
          <h5 class="fw-bold my-4"><i class="fas fa-palette me-2"></i> Configuración de Colores</h5>
          <div class="row mb-3">
            <div class="col-md-6 mb-2">
              <label for="color_primario" class="form-label">Color Primario</label>
              <input type="color" class="form-control form-control-color" name="color_primario" value="<?= htmlspecialchars($fila['color_primario'] ?? '#3498db') ?>" title="Elige un color primario">
            </div>
            <div class="col-md-6 mb-2">
              <label for="color_secundario" class="form-label">Color Secundario</label>
              <input type="color" class="form-control form-control-color" name="color_secundario" value="<?= htmlspecialchars($fila['color_secundario'] ?? '#2ecc71') ?>" title="Elige un color secundario">
            </div>
          </div>

          <div class="card mt-3 p-3">
            <h6 class="mb-2">Vista Previa</h6>
            <div class="d-flex">
              <div class="flex-fill text-center p-2" style="background-color: <?= htmlspecialchars($fila['color_primario'] ?? '#3498db'); ?>; color: #fff;">
                Color Primario
              </div>
              <div class="flex-fill text-center p-2" style="background-color: <?= htmlspecialchars($fila['color_secundario'] ?? '#2ecc71'); ?>; color: #fff;">
                Color Secundario
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="firma" role="tabpanel">
          <div class="row mb-3">
            <div class="col-md-6 mb-2">
              <label class="form-label">¿Mostrar firma escaneada?</label><br>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="mostrar_firma_imagen" value="1" <?= !empty($fila['mostrar_firma_imagen']) ? 'checked' : '' ?>>
                <label class="form-check-label">Activar</label>
              </div>
            </div>

            <div class="col-md-6 mb-2">
              <label for="firma_imagen" class="form-label">Subir imagen de firma</label>
              <input type="file" class="form-control" name="firma_imagen" accept="image/*">
            </div>

            <div class="col-md-12 text-center mt-3">
              <?php if (!empty($fila['firma_imagen_url'])): ?>
                <div class="border p-2 rounded">
                  <img src="../<?= htmlspecialchars($fila['firma_imagen_url']) ?>" alt="Firma Actual" style="max-height:80px;">
                </div>
              <?php endif; ?>
            </div>
          </div>

          <hr class="mt-4">

          <div class="row mb-3">
            <div class="col-md-12 mb-2">
              <label for="firma_nombre" class="form-label">Nombre en la Firma</label>
              <input type="text" class="form-control" name="firma_nombre" maxlength="150"
                    value="<?= htmlspecialchars($fila['firma_nombre'] ?? '') ?>" placeholder="Ej: Dra. Mariana Veliz">
            </div>

            <div class="col-md-12 mb-2">
              <label for="firma_titulo" class="form-label">Título Profesional</label>
              <input type="text" class="form-control" name="firma_titulo" maxlength="150"
                    value="<?= htmlspecialchars($fila['firma_titulo'] ?? '') ?>" placeholder="Ej: Médico Veterinario">
            </div>

            <div class="col-md-12 mb-2">
              <label class="form-label">Subtítulos</label>

              <div id="firma-subtitulos-container">
                <?php foreach ($subtitulos as $sub): ?>
                  <div class="input-group mb-2">
                    <input type="text" name="firma_subtitulos[]" class="form-control"
                          value="<?= htmlspecialchars($sub) ?>" placeholder="Ej: Diplomada en Imagenología">
                    <button type="button" class="btn btn-danger eliminar-subtitulo">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                <?php endforeach; ?>
              </div>

              <button type="button" id="agregar-subtitulo" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Agregar línea
              </button>
            </div>

            <div class="col-md-6 mb-2">
              <label for="firma_align" class="form-label">Posición de la Firma</label>
              <select name="firma_align" id="firma_align" class="form-select select2">
                <option value="left" data-icon="fas fa-align-left" <?= ($fila['firma_align'] ?? 'center') === 'left' ? 'selected' : '' ?>>Izquierda</option>
                <option value="center" data-icon="fas fa-align-center" <?= ($fila['firma_align'] ?? 'center') === 'center' ? 'selected' : '' ?>>Centro</option>
                <option value="right" data-icon="fas fa-align-right" <?= ($fila['firma_align'] ?? 'center') === 'right' ? 'selected' : '' ?>>Derecha</option>
              </select>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="footer" role="tabpanel">
          <div class="row mb-3">
            <div class="col-md-12 mb-2">
              <label for="footer_texto" class="form-label">Texto del Footer</label>
              <textarea class="form-control" name="footer_texto" rows="3" maxlength="500"
                        placeholder="Ej: Este informe debe ser interpretado por un médico veterinario tratante."><?= htmlspecialchars($fila['footer_texto'] ?? '') ?></textarea>
            </div>

            <div class="col-md-6 mb-2">
              <label for="footer_align" class="form-label">Alineación del Footer</label>
              <select name="footer_align" id="footer_align" class="form-select select2">
                <option value="left" data-icon="fas fa-align-left" <?= ($fila['footer_align'] ?? 'center') === 'left' ? 'selected' : '' ?>>Izquierda</option>
                <option value="center" data-icon="fas fa-align-center" <?= ($fila['footer_align'] ?? 'center') === 'center' ? 'selected' : '' ?>>Centro</option>
                <option value="right" data-icon="fas fa-align-right" <?= ($fila['footer_align'] ?? 'center') === 'right' ? 'selected' : '' ?>>Derecha</option>
              </select>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="fecha" role="tabpanel">
          <div class="row mb-3">
            <div class="col-md-6 mb-2">
              <label class="form-label">¿Mostrar Fecha en el Informe?</label><br>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="mostrar_fecha" value="1" <?= !empty($fila['mostrar_fecha']) ? 'checked' : '' ?>>
                <label class="form-check-label">Activar</label>
              </div>
            </div>

            <div class="col-md-6 mb-2">
              <label for="formato_fecha" class="form-label">Formato de la Fecha</label>
              <select name="formato_fecha" class="form-control">
                <option value="{{day}} de {{month}} del {{year}}" <?= ($fila['formato_fecha'] ?? '') === '{{day}} de {{month}} del {{year}}' ? 'selected' : '' ?>>
                  dd de MM del aaaa (Ej: 04 de julio del 2025)
                </option>
                <option value="{{day}}/{{month}}/{{year}}" <?= ($fila['formato_fecha'] ?? '') === '{{day}}/{{month}}/{{year}}' ? 'selected' : '' ?>>
                  dd/MM/aaaa (Ej: 04/julio/2025)
                </option>
                <option value="{{month}} {{day}}, {{year}}" <?= ($fila['formato_fecha'] ?? '') === '{{month}} {{day}}, {{year}}' ? 'selected' : '' ?>>
                  MM dd, aaaa (Ej: julio 04, 2025)
                </option>
                <option value="{{year}}-{{month}}-{{day}}" <?= ($fila['formato_fecha'] ?? '') === '{{year}}-{{month}}-{{day}}' ? 'selected' : '' ?>>
                  aaaa-MM-dd (Ej: 2025-julio-04)
                </option>
              </select>
            </div>

            <div class="col-md-6 mb-2">
              <label for="lugar_fecha" class="form-label">Lugar para la fecha (opcional)</label>
              <input type="text" name="lugar_fecha" class="form-control"
                    value="<?= htmlspecialchars($fila['lugar_fecha'] ?? '') ?>" placeholder="Ej: Santiago">
            </div>

            <div class="col-md-6 mb-2">
              <label for="fecha_align" class="form-label">Posición de la Fecha</label>
              <select name="fecha_align" id="fecha_align" class="form-select select2">
                <option value="left" data-icon="fas fa-align-left" <?= ($fila['fecha_align'] ?? 'right') === 'left' ? 'selected' : '' ?>>Izquierda</option>
                <option value="center" data-icon="fas fa-align-center" <?= ($fila['fecha_align'] ?? 'right') === 'center' ? 'selected' : '' ?>>Centro</option>
                <option value="right" data-icon="fas fa-align-right" <?= ($fila['fecha_align'] ?? 'right') === 'right' ? 'selected' : '' ?>>Derecha</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <?php if ($action === 'modificar'): ?>
        <input type="hidden" name="id" value="<?= (int)$fila['id'] ?>">
      <?php endif; ?>

      <input type="hidden" name="action" value="<?= $action ?>">

      <button type="submit" class="btn btn-primary mt-3"><?= $accion ?></button>
    </form>
  </div>
</div>

<script>
$('form').on('submit', function(e) {
    e.preventDefault();

    let idsActuales = [];
    let ordenesActualizados = {};

    $('#campos-lista tr').each(function(index) {
        const id = $(this).data('id');
        if (id && String(id).indexOf('nuevo-') !== 0) {
            idsActuales.push(id);
            ordenesActualizados[id] = index + 1;
        }
    });

    $('#campos_ids_actuales').val(idsActuales.join(','));

    $('#campos_orden').val(JSON.stringify(ordenesActualizados));

    let formData = new FormData(this);

    $.ajax({
        url: $(this).attr('action'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            let jsonResponse = JSON.parse(response);
            if (jsonResponse.status === 'success') {
                $('#content').load('configuracion_informe/lisConfiguracion.php');
                Swal.fire('Éxito', jsonResponse.message, 'success');
            } else {
                Swal.fire('Error', jsonResponse.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'No se pudo guardar la configuración.', 'error');
        }
    });
});

$(document).ready(function () {
    $('.select2').select2({
        templateResult: formatState,
        templateSelection: formatState,
        minimumResultsForSearch: Infinity,
        width: '100%'
    });

    function formatState(state) {
        if (!state.id) return state.text;
        var icon = $(state.element).data('icon');
        if (icon) {
            return $('<span><i class="' + icon + '"></i> ' + state.text + '</span>');
        }
        return state.text;
    }

    $("#campos-lista").sortable({
        handle: ".orden",
        update: function() {
            actualizarVistaPrevia();
        }
    });

    actualizarOpcionesSelect();
    actualizarVistaPrevia();
});

$(document).on('click', '#agregar-subtitulo', function () {
    const html = `
        <div class="input-group mb-2">
            <input type="text" name="firma_subtitulos[]" class="form-control" placeholder="Nueva línea">
            <button type="button" class="btn btn-danger eliminar-subtitulo">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    $('#firma-subtitulos-container').append(html);
});

$(document).on('click', '.eliminar-subtitulo', function () {
    $(this).closest('.input-group').remove();
});

$(document).on("click", ".eliminar-campo", function() {
    $(this).closest('tr').remove();
    actualizarVistaPrevia();
    actualizarOpcionesSelect();
});

$('#agregar-campo').on('click', function () {
    const selectedId = $('#campo-select').val();
    const selectedText = $('#campo-select option:selected').text();

    if (!selectedId) {
        Swal.fire('Atención', 'No hay más campos disponibles para agregar.', 'warning');
        return;
    }

    if ($('#campos-lista tr[data-campo-id="' + selectedId + '"]').length > 0) {
        Swal.fire('Atención', 'Este campo ya está agregado.', 'warning');
        return;
    }

    const newRow = `
        <tr data-id="nuevo-${selectedId}" data-campo-id="${selectedId}">
            <td class="orden"><i class="fas fa-arrows-alt-v"></i></td>
            <td>${selectedText}</td>
            <td class="text-center">
                <input type="checkbox" name="campos_nuevos[${selectedId}][visible]" value="1" checked>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger eliminar-campo"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `;
    $('#campos-lista').append(newRow);

    actualizarOpcionesSelect();
    actualizarVistaPrevia();
});

$(document).on('input change', '#campos-lista input', function () {
    actualizarVistaPrevia();
});

function actualizarVistaPrevia() {
    let campos = [];

    $('#campos-lista tr').each(function () {
        let etiqueta = $(this).find('td:nth-child(2)').text().trim();
        let visible = $(this).find('input[type="checkbox"]').is(':checked');

        if (etiqueta !== '' && visible) {
            campos.push(etiqueta);
        }
    });

    let html = '';

    for (let i = 0; i < campos.length; i += 2) {
        html += '<tr>';
        html += `<th style="width: 15%; white-space: nowrap;">${campos[i]}:</th><td style="width: 35%;"></td>`;

        if (campos[i + 1]) {
            html += `<th style="width: 15%; white-space: nowrap;">${campos[i + 1]}:</th><td style="width: 35%;"></td>`;
        } else {
            html += '<td colspan="3"></td>';
        }

        html += '</tr>';
    }

    $('#vista-previa-campos').html(html);
}

function actualizarOpcionesSelect() {
    $('#campo-select option').prop('disabled', false);

    $('#campos-lista tr').each(function () {
        const campoId = $(this).data('campo-id');
        $('#campo-select option[value="' + campoId + '"]').prop('disabled', true);
    });

    const $primerHabilitado = $('#campo-select option:not(:disabled)').first();

    if ($('#campo-select option:selected').prop('disabled')) {
        $('#campo-select').val($primerHabilitado.length ? $primerHabilitado.val() : '');
    }

    if ($('#campo-select option:not(:disabled)').length === 0) {
        $('#campo-select').val('');
    }
}
</script>