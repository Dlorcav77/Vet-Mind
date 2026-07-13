<?php
// admin/certificado/pdf/plantillas/planilla_pdf_clinica.php

/**
 * Esta plantilla se incluye desde pdf/funcionesCertificado.php.
 * Las variables principales vienen preparadas desde buildInformeHtml().
 *
 * @var array $config
 * @var array $paciente
 * @var array $campos
 * @var array $imagenes
 * @var string $fecha
 * @var string $descripcion
 */

$color_primario = $config['color_primario'] ?? '#4f7fb7';
$color_secundario = $config['color_secundario'] ?? '#d6771f';

$marca_agua_sizes = [
    'small'  => '52%',
    'medium' => '68%',
    'large'  => '82%'
];

$marca_agua_size = $config['marca_agua_size'] ?? 'medium';
$marca_agua_width = $marca_agua_sizes[$marca_agua_size] ?? $marca_agua_sizes['medium'];

$layout_config = [];

if (!empty($config['layout_config_json'])) {
    $decoded = json_decode($config['layout_config_json'], true);

    if (is_array($decoded)) {
        if (isset($decoded['clinica']) && is_array($decoded['clinica'])) {
            $layout_config = $decoded['clinica'];
        } elseif (isset($decoded['inev']) && is_array($decoded['inev'])) {
            $layout_config = $decoded['inev'];
        } else {
            $layout_config = $decoded;
        }
    }
}

$institucion_nombre = trim($layout_config['institucion_nombre'] ?? '');
$institucion_direccion = trim($layout_config['direccion'] ?? '');
$institucion_telefonos = trim($layout_config['telefonos'] ?? '');
$institucion_correo = trim($layout_config['correo'] ?? '');
$institucion_web = trim($layout_config['web'] ?? '');

$datos_institucion_visibles = array_filter([
    $institucion_direccion,
    $institucion_telefonos,
    $institucion_correo,
    $institucion_web
], function ($valor) {
    return trim((string)$valor) !== '';
});

$total_datos_institucion = count($datos_institucion_visibles);

$tiene_datos_institucion = $total_datos_institucion > 0;

$clase_total_datos_institucion = 'datos-count-' . $total_datos_institucion;

$footer_texto = trim($config['footer_texto'] ?? '');

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

$align_permitidos = ['left', 'center', 'right'];

$firma_align = $config['firma_align'] ?? 'right';
if (!in_array($firma_align, $align_permitidos, true)) {
    $firma_align = 'right';
}

$footer_align = $config['footer_align'] ?? 'center';
if (!in_array($footer_align, $align_permitidos, true)) {
    $footer_align = 'center';
}

$fecha_align = $config['fecha_align'] ?? 'right';
if (!in_array($fecha_align, $align_permitidos, true)) {
    $fecha_align = 'right';
}

$subtitulo_align = $config['subtitulo_align'] ?? 'left';
if (!in_array($subtitulo_align, $align_permitidos, true)) {
    $subtitulo_align = 'left';
}

$logo_size = $config['logo_size'] ?? 'large';

$logo_sizes_clinica = [
    'small' => [
        'width' => '115px',
        'max_width' => '115px',
        'max_height' => '128px'
    ],
    'medium' => [
        'width' => '145px',
        'max_width' => '145px',
        'max_height' => '170px'
    ],
    'large' => [
        'width' => '180px',
        'max_width' => '180px',
        'max_height' => '205px'
    ]
];

$logo_config_clinica = $logo_sizes_clinica[$logo_size] ?? $logo_sizes_clinica['large'];

$firma_margin_map = [
    'left'   => '72px auto 0 0',
    'center' => '72px auto 0 auto',
    'right'  => '72px 36px 0 auto'
];

$firma_img_margin_map = [
    'left'   => '0 auto 8px 0',
    'center' => '0 auto 8px auto',
    'right'  => '0 0 8px auto'
];

$firma_margin = $firma_margin_map[$firma_align] ?? $firma_margin_map['right'];
$firma_img_margin = $firma_img_margin_map[$firma_align] ?? $firma_img_margin_map['right'];

$fecha_dt = new DateTime($fecha);
$dia = $fecha_dt->format('j');
$anio = $fecha_dt->format('Y');

$meses = [
    'January' => 'enero',
    'February' => 'febrero',
    'March' => 'marzo',
    'April' => 'abril',
    'May' => 'mayo',
    'June' => 'junio',
    'July' => 'julio',
    'August' => 'agosto',
    'September' => 'septiembre',
    'October' => 'octubre',
    'November' => 'noviembre',
    'December' => 'diciembre'
];

$mes_en = $fecha_dt->format('F');
$mes_es = $meses[$mes_en] ?? strtolower($mes_en);
$fecha_emision = $dia . ' ' . $mes_es . ' ' . $anio;

$lugar = trim($config['lugar_fecha'] ?? '');
$mes_es_formateado = ucfirst($mes_es);
$formato_fecha = $config['formato_fecha'] ?? '{{day}}/{{month}}/{{year}}';

$fecha_str = str_replace(
    ['{{day}}', '{{month}}', '{{year}}'],
    [$dia, $mes_es_formateado, $anio],
    $formato_fecha
);

$fecha_str = ucfirst($fecha_str);
$fecha_informe_str = ($lugar !== '' ? $lugar . ', ' : '') . $fecha_str;

$imagenes_por_fila = (int)($config['imagenes_por_fila'] ?? 2);

if ($imagenes_por_fila < 1) {
    $imagenes_por_fila = 2;
}

if ($imagenes_por_fila > 4) {
    $imagenes_por_fila = 4;
}

$ancho_imagen_td = (100 / $imagenes_por_fila) . '%';

function base64ImageClinica($path) {
    if (!$path) {
        return null;
    }

    if (strpos($path, 'data:image/') === 0) {
        return $path;
    }

    $fullPath = realpath(__DIR__ . '/../../../../' . ltrim($path, '/'));

    if ($fullPath && file_exists($fullPath)) {
        $mime = mime_content_type($fullPath);
        $data = base64_encode(file_get_contents($fullPath));

        return "data:$mime;base64,$data";
    }

    return null;
}

function iconoClinicaSvg($tipo, $color) {
    $color = trim((string)$color);

    if ($color === '') {
        $color = '#d17822';
    }

    $paths = [
        'direccion' => '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5z"/>',
        'telefono'  => '<path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1C10.61 21 3 13.39 3 4c0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.24.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>',
        'correo'    => '<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/>',
        'web'       => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm6.93 6h-2.95a15.5 15.5 0 0 0-1.38-3.02A8.04 8.04 0 0 1 18.93 8zM12 4.04c.83 1.2 1.48 2.53 1.88 3.96h-3.76A14.4 14.4 0 0 1 12 4.04zM4.26 14A8.4 8.4 0 0 1 4 12c0-.69.09-1.36.26-2h3.33A16.9 16.9 0 0 0 7.5 12c0 .68.03 1.35.09 2H4.26zm.81 2h2.95c.33 1.08.79 2.1 1.38 3.02A8.04 8.04 0 0 1 5.07 16zm2.95-8H5.07A8.04 8.04 0 0 1 9.4 4.98 15.5 15.5 0 0 0 8.02 8zM12 19.96A14.4 14.4 0 0 1 10.12 16h3.76A14.4 14.4 0 0 1 12 19.96zM14.31 14H9.69c-.07-.66-.11-1.33-.11-2s.04-1.34.11-2h4.62c.07.66.11 1.33.11 2s-.04 1.34-.11 2zm.29 5.02A15.5 15.5 0 0 0 15.98 16h2.95a8.04 8.04 0 0 1-4.33 3.02zM16.41 14c.06-.65.09-1.32.09-2s-.03-1.35-.09-2h3.33c.17.64.26 1.31.26 2s-.09 1.36-.26 2h-3.33z"/>',
    ];

    $path = $paths[$tipo] ?? $paths['direccion'];

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '">' . $path . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function limpiarValorClinica($valor) {
    return trim((string)($valor ?? ''));
}

function calcularEdadClinica($paciente) {
    if (!empty($paciente['edad'])) {
        return limpiarValorClinica($paciente['edad']);
    }

    if (empty($paciente['fecha_nacimiento'])) {
        return '';
    }

    try {
        $fechaNacimiento = new DateTime($paciente['fecha_nacimiento']);
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

function armarDatoPacienteClinica($paciente) {
    $partes = [];

    $nombre = limpiarValorClinica($paciente['paciente'] ?? '');
    $especie = limpiarValorClinica($paciente['especie'] ?? '');
    $raza = limpiarValorClinica($paciente['raza'] ?? '');
    $edad = calcularEdadClinica($paciente);

    if ($nombre !== '') {
        $partes[] = $nombre;
    }

    if ($especie !== '') {
        $partes[] = $especie;
    }

    if ($raza !== '') {
        $partes[] = $raza;
    }

    if ($edad !== '') {
        $partes[] = $edad;
    }

    return implode(', ', $partes);
}

function formatearFechaCampoClinica($fecha) {
    $fecha = limpiarValorClinica($fecha);

    if ($fecha === '') {
        return '';
    }

    try {
        $dt = new DateTime($fecha);
        return $dt->format('d-m-Y');
    } catch (Throwable $e) {
        return $fecha;
    }
}

function obtenerValorCampoClinica($campoNombre, $paciente, $fecha_emision) {
    $campoNombre = trim((string)$campoNombre);

    if ($campoNombre === 'edad') {
        return calcularEdadClinica($paciente);
    }

    if ($campoNombre === 'fecha_nacimiento') {
        return formatearFechaCampoClinica($paciente['fecha_nacimiento'] ?? '');
    }

    if ($campoNombre === 'fecha_emision') {
        return $fecha_emision;
    }

    return limpiarValorClinica($paciente[$campoNombre] ?? '');
}

function agruparCamposPdfClinicaPorOrden($campos) {
    $filas = [];

    foreach ($campos as $index => $campo) {
        $orden = (int)($campo['orden'] ?? 0);

        if ($orden >= 10) {
            $grupoOrden = (int)(floor($orden / 10) * 10);
            $claveFila = 'grupo-' . $grupoOrden;
        } else {
            $claveFila = 'campo-' . $index;
        }

        if (!isset($filas[$claveFila])) {
            $filas[$claveFila] = [];
        }

        $filas[$claveFila][] = $campo;
    }

    return $filas;
}

$logo_src = !empty($config['logo_url']) ? base64ImageClinica($config['logo_url']) : null;
$firma_src = (!empty($config['firma_imagen_url']) && !empty($config['mostrar_firma_imagen']))
    ? base64ImageClinica($config['firma_imagen_url'])
    : null;
$marca_agua_src = (!empty($config['marca_agua_url']) && !empty($config['mostrar_marca_agua']))
    ? base64ImageClinica($config['marca_agua_url'])
    : null;

$filas_campos_clinica = agruparCamposPdfClinicaPorOrden($campos);
?>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 24px 0 0 0;
        }

        @page :first {
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #111111;
            font-family: Calibri, Arial, Helvetica, sans-serif;
            font-size: 14px;
        }

        .page {
            position: relative;
            width: 100%;
            min-height: 1068px;
            background-color: #ffffff;
            <?php if ($marca_agua_src): ?>
            background-image: url("<?= $marca_agua_src ?>");
            background-repeat: no-repeat;
            background-position: right 112%;
            background-size: <?= htmlspecialchars($marca_agua_width) ?> auto;
            <?php endif; ?>
            overflow: hidden;
            page-break-after: auto;
        }

        .imagenes-page {
            page-break-before: always;
            page-break-after: auto;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0;
            padding: 0;
        }

        .header-table td {
            border: none;
            margin: 0;
            padding: 0;
        }

        .header-logo-cell {
            width: 25%;
            vertical-align: top;
            padding: 6px 8px 0 10px !important;
        }

        .header-logo {
            width: <?= htmlspecialchars($logo_config_clinica['width']) ?>;
            max-width: <?= htmlspecialchars($logo_config_clinica['max_width']) ?>;
            max-height: <?= htmlspecialchars($logo_config_clinica['max_height']) ?>;
            display: block;
            margin: 0 auto;
        }

        .header-info-cell {
            width: 75%;
            vertical-align: top;
            padding: 4px 0 0 0 !important;
        }

        .titulo-barra {
            background: <?= htmlspecialchars($color_secundario) ?>;
            color: #ffffff;
            text-align: center;
            font-size: 22px;
            letter-spacing: .3px;
            padding: 11px 10px;
            text-transform: uppercase;
            margin: 0 0 8px 0;
            line-height: 1.15;
        }

        .datos-institucion {
            background: <?= htmlspecialchars($color_primario) ?>;
            color: #dfe9f6;
            padding: 12px 28px 10px 28px;
            min-height: 108px;
            font-size: 15px;
            line-height: 1.32;
            text-align: center;
        }

        .datos-institucion.sin-detalle {
            min-height: 72px;
            padding-top: 28px;
        }

        .institucion-nombre {
            font-weight: bold;
            font-size: 16px;
            margin: 0 0 8px 0;
            color: #f4f8fd;
            text-align: center;
        }

        .institucion-nombre.sin-detalle {
            margin-bottom: 0;
        }

        .institucion-tabla {
            border-collapse: collapse;
            color: #dfe9f6;
            font-size: 15px;
            line-height: 1.18;
            width: 78%;
            margin: 0 auto;
            text-align: left;
        }

        .institucion-tabla td {
            padding: 1px 0 !important;
            color: #dfe9f6;
            vertical-align: top;
        }

                .datos-institucion.datos-count-4 .institucion-tabla td {
            height: 18px;
            vertical-align: middle;
        }

        .datos-institucion.datos-count-3 .institucion-tabla td {
            height: 24px;
            vertical-align: middle;
        }

        .datos-institucion.datos-count-2 .institucion-tabla td {
            height: 34px;
            vertical-align: middle;
        }

        .datos-institucion.datos-count-1 .institucion-tabla td {
            height: 58px;
            vertical-align: middle;
        }

        .institucion-icono {
            width: 34px;
            text-align: center;
            padding: 2px 10px 0 0 !important;
            vertical-align: top;
        }

        .icono-clinica-img {
            width: 13px;
            height: 13px;
            display: inline-block;
        }

        .institucion-web {
            font-weight: bold;
            color: #ffffff !important;
        }


        .contenido {
            position: relative;
            z-index: 2;
            padding: 30px 48px 10px 48px;
        }

        .titulo-seccion {
            margin: 0 0 6px 0;
            color: <?= htmlspecialchars($color_secundario) ?>;
            font-size: 24px;
            line-height: 1;
            font-weight: 600;
            text-transform: uppercase;
        }

        .antecedentes {
            font-size: 14px;
            line-height: 1.38;
            margin-bottom: 12px;
            color: #000000;
        }

        .antecedentes div {
            margin-bottom: 1px;
        }

        .separador {
            height: 3px;
            background: <?= htmlspecialchars($color_primario) ?>;
            margin: 14px -10px 12px -10px;
        }

        .descripcion {
            font-size: 14px;
            line-height: 1.22;
            color: #000000;
            margin-top: 12px;
        }

        .descripcion p {
            margin: 0 0 5px 0;
            line-height: 1.22;
        }

        .descripcion div {
            margin: 0;
            line-height: 1.22;
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
            line-height: 1.22;
        }

        .descripcion ul,
        .descripcion ol {
            margin: 4px 0 4px 18px;
            padding: 0;
        }

        .descripcion li {
            margin: 0 0 3px 0;
            line-height: 1.22;
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
            margin: 12px 0 14px 0;
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

        .saludo {
            margin-top: 10px;
        }

        .firma-box {
            width: 290px;
            margin: <?= $firma_margin ?>;
            text-align: <?= htmlspecialchars($firma_align) ?>;
            font-size: 16px;
            line-height: 1.35;
            color: #000000;
            page-break-inside: avoid;
        }

        .firma-saludo {
            margin-bottom: 24px;
            font-size: 20px;
            font-weight: 600;
        }

        .firma-img {
            max-height: 72px;
            max-width: 220px;
            display: block;
            margin: <?= $firma_img_margin ?>;
        }

        .fecha-informe {
            text-align: <?= htmlspecialchars($fecha_align) ?>;
            color: #555555;
            font-size: 13px;
            font-weight: bold;
            margin: 18px 0 0 0;
            page-break-inside: avoid;
        }

        .footer-texto {
            position: fixed;
            left: 70px;
            right: 70px;
            bottom: 28px;
            text-align: <?= htmlspecialchars($footer_align) ?>;
            font-size: 10px;
            line-height: 1.15;
            color: #111111;
            z-index: 50;
        }

        .footer-linea {
            position: fixed;
            left: 36px;
            right: 36px;
            bottom: 52px;
            height: 2px;
            background: <?= htmlspecialchars($color_primario) ?>;
            z-index: 50;
        }

        .footer-barra {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: 24px;
            background: <?= htmlspecialchars($color_primario) ?>;
            z-index: 50;
        }

.imagenes-page {
    page-break-before: always;
    page-break-after: auto;
    width: auto;
    padding: 26px 24px 64px 24px;
}

.imagenes-titulo {
    margin: 0 0 14px 0;
    color: <?= htmlspecialchars($color_secundario) ?>;
    font-size: 23px;
    line-height: 1;
    font-weight: 400;
    text-transform: uppercase;
}

.imagenes-tabla {
    width: 100%;
    margin: 0 auto;
    border-collapse: collapse;
    table-layout: fixed;
}

.imagenes-tabla td {
    width: <?= htmlspecialchars($ancho_imagen_td) ?>;
    padding: 2px 3px;
    vertical-align: top;
    text-align: center;
}

.imagenes-tabla img {
    width: 100%;
    max-height: <?= $imagenes_por_fila === 1 ? '500px' : ($imagenes_por_fila === 2 ? '235px' : '170px') ?>;
    object-fit: contain;
    border-radius: 8px;
    display: block;
    margin: 0 auto;
}
.imagenes-titulo-espacio {
    height: 0;
    margin: 0 0 14px 0;
    line-height: 0;
    font-size: 0;
}
    </style>
</head>

<body>

<?php if ($footer_texto !== ''): ?>
    <div class="footer-texto">
        <?= nl2br(htmlspecialchars($footer_texto)) ?>
    </div>
<?php endif; ?>

<div class="footer-linea"></div>
<div class="footer-barra"></div>

<div class="page">

    <table class="header-table">
        <tr>
            <td class="header-logo-cell" width="25%">
                <?php if ($logo_src): ?>
                    <img src="<?= $logo_src ?>" alt="Logo" class="header-logo">
                <?php endif; ?>
            </td>

            <td class="header-info-cell" width="75%">
                <div class="titulo-barra">
                    <?= htmlspecialchars($config['titulo_informe'] ?? 'INFORME ECOGRÁFICO') ?>
                </div>

                <div class="datos-institucion <?= $tiene_datos_institucion ? $clase_total_datos_institucion : 'sin-detalle' ?>">
                    <?php if ($institucion_nombre !== ''): ?>
                        <div class="institucion-nombre <?= $tiene_datos_institucion ? '' : 'sin-detalle' ?>">
                            <?= htmlspecialchars($institucion_nombre) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($tiene_datos_institucion): ?>
                        <table class="institucion-tabla">
                            <?php if ($institucion_direccion !== ''): ?>
                                <tr>
                                    <td class="institucion-icono">
                                        <img src="<?= iconoClinicaSvg('direccion', $color_secundario) ?>" class="icono-clinica-img" alt="">
                                    </td>
                                    <td><?= htmlspecialchars($institucion_direccion) ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($institucion_telefonos !== ''): ?>
                                <tr>
                                    <td class="institucion-icono">
                                        <img src="<?= iconoClinicaSvg('telefono', $color_secundario) ?>" class="icono-clinica-img" alt="">
                                    </td>
                                    <td><?= htmlspecialchars($institucion_telefonos) ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($institucion_correo !== ''): ?>
                                <tr>
                                    <td class="institucion-icono">
                                        <img src="<?= iconoClinicaSvg('correo', $color_secundario) ?>" class="icono-clinica-img" alt="">
                                    </td>
                                    <td><?= htmlspecialchars($institucion_correo) ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($institucion_web !== ''): ?>
                                <tr>
                                    <td class="institucion-icono">
                                        <img src="<?= iconoClinicaSvg('web', $color_secundario) ?>" class="icono-clinica-img" alt="">
                                    </td>
                                    <td class="institucion-web"><?= htmlspecialchars($institucion_web) ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>

    <div class="contenido">
        <h2 class="titulo-seccion">ANTECEDENTES GENERALES</h2>

        <div class="antecedentes">
            <?php foreach ($filas_campos_clinica as $camposFila): ?>
                <?php
                    $campoPrincipal = $camposFila[0] ?? null;

                    if (!$campoPrincipal) {
                        continue;
                    }

                    $etiquetaPrincipal = trim((string)($campoPrincipal['etiqueta'] ?? ''));

                    if ($etiquetaPrincipal === '') {
                        continue;
                    }

                    $valoresFila = [];

                    foreach ($camposFila as $campoFila) {
                        $campoNombre = trim((string)($campoFila['campo'] ?? ''));
                        $valorCampo = obtenerValorCampoClinica($campoNombre, $paciente, $fecha_emision);

                        if ($valorCampo !== '') {
                            $valoresFila[] = $valorCampo;
                        }
                    }
                ?>

                <div>
                    <strong><?= htmlspecialchars($etiquetaPrincipal) ?>:</strong>
                    <?= htmlspecialchars(implode(', ', $valoresFila)) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="separador"></div>

        <?php if (!empty($config['subtitulo'])): ?>
            <h2 class="titulo-seccion" style="text-align: <?= htmlspecialchars($subtitulo_align) ?>;">
                <?= htmlspecialchars($config['subtitulo']) ?>
            </h2>
        <?php endif; ?>

        <div class="descripcion">
            <?= $descripcion ?>

            <!-- <div class="saludo">
                Saluda atentamente a usted.
            </div> -->
        </div>

        <div class="firma-box">
            <div class="firma-saludo">Atentamente,</div>

            <?php if ($firma_src): ?>
                <img src="<?= $firma_src ?>" alt="Firma" class="firma-img">
            <?php endif; ?>

            <div><?= htmlspecialchars($config['firma_nombre'] ?? 'Nombre de la firma') ?></div>
            <div><?= htmlspecialchars($config['firma_titulo'] ?? 'Título profesional') ?></div>

            <?php foreach ($firma_subtitulos as $linea): ?>
                <small style="display:block;">
                    <?= htmlspecialchars($linea) ?>
                </small>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($config['mostrar_fecha'])): ?>
            <div class="fecha-informe">
                <?= htmlspecialchars($fecha_informe_str) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($imagenes)): ?>
    <?php
        $imagenes_por_pagina = $imagenes_por_fila * 4;
        $paginas_imagenes = array_chunk($imagenes, $imagenes_por_pagina);
    ?>

    <?php foreach ($paginas_imagenes as $pagina_index => $imagenes_pagina): ?>
        <div class="page imagenes-page">
            <?php if ($pagina_index === 0): ?>
                <h2 class="imagenes-titulo">IMÁGENES</h2>
            <?php else: ?>
                <div class="imagenes-titulo-espacio"></div>
            <?php endif; ?>

            <table class="imagenes-tabla">
                <?php foreach (array_chunk($imagenes_pagina, $imagenes_por_fila) as $fila_imagenes): ?>
                    <tr>
                        <?php foreach ($fila_imagenes as $img): ?>
                            <td>
                                <img src="<?= base64ImageClinica($img) ?>" alt="Imagen">
                            </td>
                        <?php endforeach; ?>

                        <?php
                            $faltantes = $imagenes_por_fila - count($fila_imagenes);
                        ?>

                        <?php for ($i = 0; $i < $faltantes; $i++): ?>
                            <td></td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>