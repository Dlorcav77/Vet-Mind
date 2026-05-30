<?php
// admin/certificado/plantilla_pdf.php

/**
 * Esta plantilla se incluye desde buildInformeHtml().
 * Las variables principales vienen preparadas desde funcionesCertificado.php.
 *
 * @var array $config
 * @var array $paciente
 * @var array $campos
 * @var array $imagenes
 * @var string $fecha
 * @var string $descripcion
 */

$align_map = ['left' => 'left', 'center' => 'center', 'right' => 'right'];
$logo_align = $align_map[$config['logo_position']] ?? 'center';
$firma_align = $align_map[$config['firma_align']] ?? 'center';
$fecha_align = $align_map[$config['fecha_align']] ?? 'flex-end';
$footer_align = $align_map[$config['footer_align']] ?? 'center';
$subtitulo_align = $align_map[$config['subtitulo_align']] ?? 'center';

$logo_sizes = ['small' => '50px', 'medium' => '80px', 'large' => '120px'];
$logo_height = $logo_sizes[$config['logo_size']] ?? '80px';

$agua_sizes = ['small' => '50%', 'medium' => '70%', 'large' => '90%'];
$marca_width = $agua_sizes[$config['marca_agua_size']] ?? '30%';

$imagenes_por_fila = intval($config['imagenes_por_fila'] ?? 2);
if ($imagenes_por_fila <= 0) {
    $imagenes_por_fila = 2;
}
$ancho_imagen_td = (100 / $imagenes_por_fila) . '%';

$lugar = trim($config['lugar_fecha'] ?? '');
$fecha_dt = new DateTime($fecha);
$dia = $fecha_dt->format('j');
$anio = $fecha_dt->format('Y');
$meses = [
    'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo',
    'April' => 'abril', 'May' => 'mayo', 'June' => 'junio',
    'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre',
    'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre'
];
$mes_en = $fecha_dt->format('F');
$mes_es = ucfirst($meses[$mes_en] ?? strtolower($mes_en));

$formato_fecha = $config['formato_fecha'] ?? '{{day}}/{{month}}/{{year}}';
$fecha_str = str_replace(['{{day}}', '{{month}}', '{{year}}'], [$dia, $mes_es, $anio], $formato_fecha);

$firma_subtitulos = [];

if (!empty($config['firma_subtitulo'])) {
    $decoded_subtitulos = json_decode($config['firma_subtitulo'], true);

    if (is_array($decoded_subtitulos)) {
        $firma_subtitulos = array_filter(array_map('trim', $decoded_subtitulos));
    } else {
        $texto_subtitulo = trim((string)$config['firma_subtitulo']);
        if ($texto_subtitulo !== '') {
            $firma_subtitulos[] = $texto_subtitulo;
        }
    }
}

function base64Image($path) {
    if (!$path) {
        return null;
    }

    if (strpos($path, 'data:image/') === 0) {
        return $path;
    }

    $fullPath = realpath(__DIR__ . '/../../' . ltrim($path, '/'));
    if ($fullPath && file_exists($fullPath)) {
        $mime = mime_content_type($fullPath);
        $data = base64_encode(file_get_contents($fullPath));
        return "data:$mime;base64,$data";
    }

    return null;
}

function calcularEdadPdfClasico($fechaNacimientoValor) {
    $fechaNacimientoValor = trim((string)$fechaNacimientoValor);

    if ($fechaNacimientoValor === '') {
        return '';
    }

    try {
        $fechaNacimiento = new DateTime($fechaNacimientoValor);
        $hoy = new DateTime();

        if ($fechaNacimiento > $hoy) {
            return '';
        }

        $diff = $hoy->diff($fechaNacimiento);

        $anios = (int)$diff->y;
        $meses = (int)$diff->m;

        $partes = [];

        if ($anios > 0) {
            $partes[] = $anios . ' ' . ($anios === 1 ? 'año' : 'años');
        }

        if ($meses > 0) {
            $partes[] = $meses . ' ' . ($meses === 1 ? 'mes' : 'meses');
        }

        if (empty($partes)) {
            return '0 meses';
        }

        return implode(' ', $partes);
    } catch (Throwable $e) {
        return '';
    }
}
?>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Times New Roman', Arial, sans-serif;
            color: #333;
            background: #fff;
            margin: 0;
            padding: 0;
            position: relative;
        }
        .header {
            text-align: <?= $logo_align ?>;
            margin-top: -10;    
        }
        .header img {
            max-height: <?= $logo_height ?>;
        }
        .titulo {
            color: <?= htmlspecialchars($config['color_primario']) ?>;
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            margin: 2px 0 8px 0;
            letter-spacing: 0.7px;
        }
        .subtitulo {
            text-align: <?= $subtitulo_align ?>;
            color: <?= htmlspecialchars($config['color_primario']) ?>;
            font-weight: 600;
            font-size: 1rem;
            margin: 0 0 12px 0;
        }
        table.datos-paciente {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
        }

        table.datos-paciente td {
            padding: 3px 6px;
            border: 1px solid #ccc;
            vertical-align: top;
            color: #000;
        }

        table.datos-paciente td.titulo {
            background-color: <?= htmlspecialchars($config['color_secundario']) ?>;
            font-weight: bold;
            color: #000;
        }

        table.datos-paciente td.titulo-celda {
            background-color: <?= htmlspecialchars($config['color_secundario']) ?>;
            font-weight: bold;
            color: #000;
            text-align: left;
            width: 15%;
            font-size: 14px;
        }

        table.datos-paciente td.no-borde {
            border-right: none;
            border-left: none;
        }

        .descripcion {
            margin-top: 8px;
        }

        .descripcion p {
            margin: 0 0 2px 0;
            line-height: 1.15;
        }

        .descripcion div {
            margin: 0;
            line-height: 1.15;
        }

        .descripcion .vm-pdf-page-break {
            display: block;
            height: 0;
            margin: 0;
            padding: 0;
            border: 0;
            page-break-after: always;
            break-after: page;
            line-height: 0;
            font-size: 0;
        }

        .descripcion .vm-pdf-page-spacer {
            display: block;
            height: 32px;
            margin: 0;
            padding: 0;
            border: 0;
            line-height: 0;
            font-size: 0;
        }

        .descripcion .vm-pdf-page-break::after {
            content: "";
            display: block;
            height: 0;
        }

        .descripcion br {
            line-height: 1.15;
        }

        .descripcion ul,
        .descripcion ol {
            margin: 2px 0 2px 18px;
            padding: 0;
        }

        .descripcion li {
            margin: 0 0 2px 0;
            line-height: 1.15;
        }

        .descripcion .tableWrapper {
            width: 100%;
            margin: 12px 0 14px 0;
            overflow: visible;
        }

        .descripcion table,
        .descripcion .vm-tiptap-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 16px 0 18px 0;
            font-size: 12px;
        }

        .descripcion th,
        .descripcion td {
            border: 1px solid #bfc9d6;
            padding: 6px 8px;
            vertical-align: top;
            text-align: left;
            color: #000;
        }

        .descripcion th {
            background: #f3f6fa;
            font-weight: bold;
        }

        .descripcion td p,
        .descripcion th p {
            margin: 0;
            line-height: 1.15;
        }

        .descripcion table ul,
        .descripcion table ol {
            margin: 2px 0 2px 16px;
            padding: 0;
        }

        .descripcion table li {
            margin: 0 0 2px 0;
            line-height: 1.15;
        }

        .contenido-principal {
            width: 100%;
        }

        .contenido-centrado {
            max-width: 90%;
            margin: 0 auto;
        }

        .imagenes {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            table-layout: fixed;
        }

        .imagenes td {
            padding: 3px;
            width: <?= htmlspecialchars($ancho_imagen_td) ?>;
            vertical-align: top;
        }

        .imagenes img {
            display: block;
            width: 100%;
            max-height: 235px;
            height: auto;
            border-radius: 4px;
            border: 1px solid #ccc;
            object-fit: contain;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .firma {
            text-align: <?= $firma_align ?>; 
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .firma > div {
            display: inline-block;
            text-align: center;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .firma img {
            max-height: 80px;
            display: block;
            margin: 0 auto 5px;
        }
        .firma h4 {
            margin: 0;
            color: #555;
        }
        .firma p, .firma small {
            margin: 0;
            color: #555;
        }
        .fecha {
            text-align: <?= $fecha_align ?>;
            color: #555;
            font-size: 12px;
            margin-top: 15px;
            margin-left: 25px;
            font-weight: bold;
        }

        @page {
            margin: 40px 20px 90px 20px;
        }

        .footer-text {
            position: fixed;
            bottom: -70px;
            left: 0;
            right: 0;
            width: 100%;
            text-align: <?= $footer_align ?>;
            color: #888;
            font-size: 15px;
            padding: 0 10px;
            background-color: #fff;
            z-index: 999;
        }

        .marca-agua {
            position: absolute;
            opacity: 0.05;
            width: <?= $marca_width ?>;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
        }

    </style>
</head>
<body>
<?php if (!empty($config['footer_texto'])): ?>
    <div class="footer-text">
        <?= nl2br(htmlspecialchars($config['footer_texto'])) ?>
    </div>
<?php endif; ?>

<div class="contenido-principal">
    <?php if (!empty($config['marca_agua_url']) && $config['mostrar_marca_agua']): ?>
        <img src="<?= base64Image($config['marca_agua_url']) ?>" class="marca-agua">
    <?php endif; ?>

    <div class="header">
        <?php if (!empty($config['logo_url'])): ?>
            <img src="<?= base64Image($config['logo_url']) ?>" alt="Logo">
        <?php endif; ?>
    </div>

    <div class="titulo"><?= htmlspecialchars($config['titulo_informe'] ?? 'INFORME ECOGRÁFICO') ?></div>

    <div class="contenido-centrado">
        <table class="datos-paciente">
            <tbody>
                <?php
                for ($i = 0; $i < count($campos); $i += 2):

                    echo "<tr>";
                    
                    $etiqueta = htmlspecialchars($campos[$i]['etiqueta']);
                    $campoNombre = $campos[$i]['campo'];

                    if ($campoNombre == 'edad') {
                        $valorCampo = calcularEdadPdfClasico($paciente['fecha_nacimiento'] ?? '');
                    } elseif ($campoNombre == 'fecha_nacimiento' && !empty($paciente['fecha_nacimiento'])) {
                        $fechaNacimiento = new DateTime($paciente['fecha_nacimiento']);
                        $valorCampo = $fechaNacimiento->format('d-m-Y');
                    } else {
                        $valorCampo = $paciente[$campoNombre] ?? '';
                    }

                    $colspan = (count($campos) % 2 !== 0 && $i + 1 == count($campos)) ? " colspan='3'" : '';
                    echo "<td class='titulo-celda' style='white-space: nowrap;'>{$etiqueta}:</td>";
                    echo "<td$colspan>" . htmlspecialchars($valorCampo) . "</td>";

                    if (isset($campos[$i + 1])) {
                        $etiqueta2 = htmlspecialchars($campos[$i + 1]['etiqueta']);
                        $campoNombre2 = $campos[$i + 1]['campo'];

                        if ($campoNombre2 == 'edad') {
                            $valorCampo2 = calcularEdadPdfClasico($paciente['fecha_nacimiento'] ?? '');
                        } elseif ($campoNombre2 == 'fecha_nacimiento' && !empty($paciente['fecha_nacimiento'])) {
                            $fechaNacimiento = new DateTime($paciente['fecha_nacimiento']);
                            $valorCampo2 = $fechaNacimiento->format('d-m-Y');
                        } else {
                            $valorCampo2 = $paciente[$campoNombre2] ?? '';
                        }

                        echo "<td class='titulo-celda' style='white-space: nowrap;'>{$etiqueta2}:</td>";
                        echo "<td>" . htmlspecialchars($valorCampo2) . "</td>";
                    }

                    echo "</tr>";
                endfor;
                ?>
            </tbody>
        </table>

        <?php if (!empty($config['subtitulo'])): ?>
            <div class="subtitulo"><?= htmlspecialchars($config['subtitulo']) ?></div>
        <?php endif; ?>
        <div class="descripcion">
            <?= $descripcion ?>
            <div style="margin-top: 10px;">Saluda atentamente a usted.</div>
        </div>
    </div>

    <div class="firma">
        <div>
            <?php if (!empty($config['firma_imagen_url']) && $config['mostrar_firma_imagen']): ?>
                <img src="<?= base64Image($config['firma_imagen_url']) ?>" alt="Firma">
            <?php endif; ?>

            <h4><?= htmlspecialchars($config['firma_nombre'] ?? 'Nombre de la Firma') ?></h4>
            <p><?= htmlspecialchars($config['firma_titulo'] ?? 'Título Profesional') ?></p>

            <?php foreach ($firma_subtitulos as $linea_subtitulo): ?>
                <small style="display:block;">
                    <?= htmlspecialchars($linea_subtitulo) ?>
                </small>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($config['mostrar_fecha']): ?>
        <div class="fecha">
            <?= ($lugar ? htmlspecialchars($lugar) . ", " : '') . $fecha_str ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($imagenes)): ?>
    <table class="imagenes">
        <tr>
        <?php foreach ($imagenes as $index => $img): ?>
            <td>
                <img src="<?= base64Image($img) ?>" alt="Imagen">
            </td>
            <?php
            if (($index + 1) % (intval($config['imagenes_por_fila']) ?: 2) == 0):
                echo '</tr><tr>';
            endif;
            ?>
        <?php endforeach; ?>
        </tr>
    </table>
<?php endif; ?>
</body>
</html>