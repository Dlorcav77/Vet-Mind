<?php
###########################################
require_once("../config.php");
credenciales('configuracion_informe', 'listar');
###########################################

$mysqli = conn();
global $usuario_id, $acceso_aplicaciones;

// Traer la configuración del veterinario actual
$sel = "SELECT * FROM configuracion_informes WHERE veterinario_id = ? LIMIT 1";
$stmt = $mysqli->prepare($sel);
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$fila = $res->fetch_assoc();

?>
<style>
  .table-sm td, .table-sm th {
    padding: 0.2rem 0.55rem;
    vertical-align: top;
  }
  .table-sm td:first-child,
  .table-sm td:nth-child(3) {
    background-color: <?= htmlspecialchars($fila['color_secundario'] ?? '#2ecc71') ?>;
    font-weight: bold;
  }
</style>
<div id="configuracion_informe" data-page-id="configuracion_informe">
  <h1 class="h3 mb-3"><strong>Configuración de Informes</strong></h1>
  <div class="card vista-previa-informe">
    <div class="card-body">
        <?php if ($fila): 
          $map_justify = [
            'left' => 'start',
            'center' => 'center',
            'right' => 'end'
          ];
          $justify_class = $map_justify[$fila['firma_align']] ?? 'center';

          $map_fecha_justify = [
            'left' => 'start',
            'center' => 'center',
            'right' => 'end'
          ];
          $fecha_justify_class = $map_fecha_justify[$fila['fecha_align']] ?? 'end';

          // Tamaños en px
          $tamaños_logo = ['small' => '50px', 'medium' => '80px', 'large' => '120px'];
          $logo_height = $tamaños_logo[$fila['logo_size'] ?? 'medium'];

          // Tamaños en %
          $tamaños_agua = ['small' => '20%', 'medium' => '30%', 'large' => '50%'];
          $marca_width = $tamaños_agua[$fila['marca_agua_size'] ?? 'medium'];
        ?>
        <div class="mb-4">
          <h5>Vista previa del informe:</h5>
          <div class="border p-5" style="position:relative; background-color:#fff; padding: 2rem; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
            <div class="d-flex justify-content-end gap-2 mb-2">
              <div style="
                width: 40px;
                height: 40px;
                border-radius: 8px;
                background: <?= htmlspecialchars($fila['color_primario'] ?? '#000') ?>;
                border: 2px solid #ccc;
                box-shadow: 0 0 5px rgba(0,0,0,0.2);
              " title="Primario: <?= htmlspecialchars($fila['color_primario'] ?? '#000') ?>"></div>

              <div style="
                width: 40px;
                height: 40px;
                border-radius: 8px;
                background: <?= htmlspecialchars($fila['color_secundario'] ?? '#000') ?>;
                border: 2px solid #ccc;
                box-shadow: 0 0 5px rgba(0,0,0,0.2);
              " title="Secundario: <?= htmlspecialchars($fila['color_secundario'] ?? '#000') ?>"></div>
            </div>
            <?php if (!empty($fila['marca_agua_url']) && $fila['mostrar_marca_agua']): ?>
              <img src="../<?= htmlspecialchars($fila['marca_agua_url']) ?>" alt="Marca de Agua"
                  style="position:absolute; opacity:0.05; width:<?= $marca_width ?>; top:50%; left:50%; transform:translate(-50%, -50%); z-index:0;">
            <?php endif; ?>
            <!-- Logo -->
            <div class="text-<?= htmlspecialchars($fila['logo_position'] ?? 'center') ?>" style="z-index:1;">
              <?php if (!empty($fila['logo_url'])): ?>
                <img src="../<?= htmlspecialchars($fila['logo_url']) ?>" alt="Logo" style="max-height:<?= $logo_height ?>;">
              <?php endif; ?>
            </div>
            <div class="text-center my-4">
              <h2 style="
                color: <?= htmlspecialchars($fila['color_primario'] ?? '#000') ?>;
                font-weight: 600; /* Negrita fuerte */
                font-size: 1.5rem; /* Más grande que antes */
                letter-spacing: 0.7px; /* Un poco de espacio entre letras */
                font-family: 'Times New Roman', 'Segoe UI', Arial, Helvetica, sans-serif;
              ">
                <?= htmlspecialchars($fila['titulo_informe'] ?? 'INFORME ECOGRÁFICO') ?>
              </h2>
            </div>
            <?php
            $stmt_campos = $mysqli->prepare(
                "SELECT cp.etiqueta
                FROM configuracion_informe_campos cic
                JOIN campos_permitidos cp ON cic.campo_id = cp.id
                WHERE cic.veterinario_id = ? AND cic.visible = 1
                ORDER BY cic.orden ASC"
            );
            $stmt_campos->bind_param("i", $usuario_id);
            $stmt_campos->execute();
            $result_campos = $stmt_campos->get_result();

            $campos = [];
            while ($campo = $result_campos->fetch_assoc()) {
                $campos[] = $campo['etiqueta'];
            }

            if (!empty($campos)): ?>
              <table class="table table-sm table-bordered mb-4" style="border: 1px solid #000; font-size: 14px;">
                <tbody>
                  <?php
                  for ($i = 0; $i < count($campos); $i += 2) {
                      echo "<tr>";
                      echo "<td style='font-weight:bold; width: 15%;'>" . htmlspecialchars($campos[$i]) . ":</td>";

                      if (isset($campos[$i + 1])) {
                          // 🔥 Normal: par
                          echo "<td style='width: 35%;'></td>";
                          echo "<td style='font-weight:bold; width: 15%;'>" . htmlspecialchars($campos[$i + 1]) . ":</td>";
                          echo "<td style='width: 35%;'></td>";
                      } else {
                          // 🔥 Última fila impar: ocupa las 3 celdas derechas
                          echo "<td colspan='3' style='width: 85%;'></td>";
                      }
                      echo "</tr>";
                  }
                  ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="alert alert-secondary mt-4 text-center">
                <i class="fas fa-info-circle"></i> No hay campos configurados para mostrar.
              </div>
            <?php endif; 
            if (!empty($fila['subtitulo'])): ?>
              <div class="text-<?= htmlspecialchars($fila['subtitulo_align'] ?? 'center') ?>" style="margin-top:0.5rem;">
                <h5 style="
                  color: <?= htmlspecialchars($fila['color_primario'] ?? '#2ecc71') ?>;
                  font-weight: 600;
                  font-size: 1rem;
                  margin-bottom: 1rem;
                ">
                  <?= htmlspecialchars($fila['subtitulo']) ?>
                </h5>
              </div>
            <?php endif; ?>
            <div style="padding: 1.5rem; margin: 1.5rem 0; background-color: #f8f9fa; border: 1px dashed #ccc; border-radius: 8px; text-align: center; color: #888;">
              <em>[Aquí se mostrará el contenido del informe]</em>
            </div>
            <!-- Firma -->
            <div class="my-4 d-flex justify-content-<?= $justify_class ?> me-5">
              <div class="text-center">
                <?php if (($fila['mostrar_firma_imagen'] ?? 0) && !empty($fila['firma_imagen_url'])): ?>
                  <img src="../<?= htmlspecialchars($fila['firma_imagen_url']) ?>" alt="Firma Escaneada" style="max-height:100px; display:block; margin:0 auto 0px;">
                <?php endif; ?>
                <h4 style="color:#555; margin-bottom:0;">
                  <?= htmlspecialchars($fila['firma_nombre'] ?? 'Nombre de la Firma') ?>
                </h4>
                <p style="color:#555; margin:0;">
                  <?= htmlspecialchars($fila['firma_titulo'] ?? 'Título Profesional') ?>
                </p>
                <small style="color:#555;">
                  <?= htmlspecialchars($fila['firma_subtitulo'] ?? 'Subtítulo') ?>
                </small>
              </div>
            </div>
            <!-- Fecha -->
            <?php if ($fila['mostrar_fecha']): ?>
              <div class="d-flex justify-content-<?= $fecha_justify_class ?> mb-2">
                <div style="color:#555;">
                  <?php
                  $lugar = trim($fila['lugar_fecha'] ?? '');
                  // Datos dinámicos
                  $fecha = new DateTime();
                  $dia   = $fecha->format('j');
                  $anio  = $fecha->format('Y');
                  // Traducir mes a español
                  $meses = [
                      'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo',
                      'April' => 'abril', 'May' => 'mayo', 'June' => 'junio',
                      'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre',
                      'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre'
                  ];
                  $mes_en = $fecha->format('F');
                  $mes_es = $meses[$mes_en] ?? strtolower($mes_en);
                  $mes_es = ucfirst($mes_es);
                  // Formato desde DB con placeholders
                  $formato = $fila['formato_fecha'] ?? '{{day}}/{{month}}/{{year}}';
                  // Reemplazar placeholders por valores
                  $fecha_str = str_replace(
                      ['{{day}}', '{{month}}', '{{year}}'],
                      [$dia, $mes_es, $anio],
                      $formato
                  );
                  // Capitalizar
                  $fecha_str = ucfirst($fecha_str);
                  // Mostrar lugar si existe
                  echo ($lugar ? htmlspecialchars($lugar) . ", " : '') . $fecha_str;
                  ?>
                </div>
              </div>
            <?php endif; ?>
            <hr style="border-top:1px solid <?= htmlspecialchars($fila['color_secundario'] ?? '#000') ?>;">
            <?php
            // 🖼️ Simulación de cuadrícula de imágenes
            $imagenes_por_fila = (int)($fila['imagenes_por_fila'] ?? 2);
            $total_fotos = 4; // Cantidad de espacios de ejemplo a mostrar
            ?>
            <div class="mt-3">
              <div class="row g-2">
                <?php for ($i = 0; $i < $total_fotos; $i++): ?>
                  <div class="col-<?= 12 / $imagenes_por_fila ?>">
                    <div class="card shadow-sm border-0" style="background-color: #e9ecef; height: 100px; border-radius: 8px;">
                      <div class="d-flex align-items-center justify-content-center h-100 text-muted" style="font-size: 0.9rem;">
                        Imagen <?= $i + 1 ?>
                      </div>
                    </div>
                  </div>
                <?php endfor; ?>
              </div>
            </div>
            <hr style="border-top:1px solid <?= htmlspecialchars($fila['color_secundario'] ?? '#000') ?>;">
            <!-- Footer -->
            <?php if (!empty($fila['footer_texto'])): ?>
              <div class="text-<?= htmlspecialchars($fila['footer_align'] ?? 'center') ?>">
                <small style="color:#888;">
                  <?= nl2br(htmlspecialchars($fila['footer_texto'])) ?>
                </small>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="text-end">
          <?php if (in_array('modificar', $acceso_aplicaciones['configuracion_informe'] ?? [])): ?>
            <a href="configuracion_informe/configuracion.php?action=modificar" class="btn btn-primary ajax-link">
              <i class="fas fa-edit"></i> Modificar configuración
            </a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p>No hay configuración registrada. Haz clic en “Configurar” para crear una.</p>
        <?php if (in_array('ingresar', $acceso_aplicaciones['configuracion_informe'] ?? [])): ?>
          <a href="configuracion_informe/configuracion.php?action=ingresar" class="btn btn-primary ajax-link">
            <i class="fas fa-plus"></i> Configurar
          </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>