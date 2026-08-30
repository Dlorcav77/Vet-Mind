<?php
// admin/certificado/envio_email/send_certificados_masivo.php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config.php';

$tmpDir = null;
$tmpFiles = [];

/*
 * Límites propios de VetMind.
 *
 * Brevo permite hasta 20 MB para el mensaje transaccional
 * completo. Dejamos margen para codificación MIME/base64,
 * cuerpo HTML y cabeceras.
 */
const VM_MAIL_MAX_INFORMES = 15;
const VM_MAIL_MAX_PDFS_BYTES = 12 * 1024 * 1024;

function vmMailSlug($text) {
    $text = trim((string)$text);

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($ascii !== false) {
        $text = $ascii;
    }

    $text = strtolower($text);
    $text = preg_replace('/\s+/', '_', $text);
    $text = preg_replace('/[^a-z0-9\-_]/', '', $text);
    $text = preg_replace('/_+/', '_', $text);
    $text = trim($text, '_');

    return $text !== '' ? $text : 'informe';
}

function vmCodigoSlug($text) {
    $text = trim((string)$text);

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($ascii !== false) {
        $text = $ascii;
    }

    $text = strtolower($text);
    $text = preg_replace('/\s+/', '', $text);
    return preg_replace('/[^a-z0-9\-_]/', '', $text);
}

function vmNombrePdf($paciente, $codigo = '') {
    $nombre = vmMailSlug($paciente);
    $codigo = vmCodigoSlug($codigo);

    return $codigo !== ''
        ? $nombre . '(' . $codigo . ').pdf'
        : $nombre . '.pdf';
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Método no permitido.');
    }

    validarTokenCsrf();
    credenciales('certificado', 'listar');

    $usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
    $ids = $_POST['certificado_ids'] ?? [];
    $destinatario = trim((string)($_POST['destinatario'] ?? ''));

    if (!$usuarioId) {
        throw new Exception('Sesión no válida.');
    }

    if (!is_array($ids)) {
        throw new Exception('La selección de informes no es válida.');
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

    if (!$ids) {
        throw new Exception('Selecciona al menos un informe.');
    }

    if (count($ids) > VM_MAIL_MAX_INFORMES) {
        throw new Exception(
            'Puedes enviar un máximo de ' .
            VM_MAIL_MAX_INFORMES .
            ' informes por correo.'
        );
    }

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El correo del destinatario no es válido.');
    }

    $mysqli = conn();

    $sql = "
        SELECT
            c.id,
            c.fecha_examen,
            c.archivo_pdf,
            c.manual_data,
            p.nombre AS paciente,
            p.codigo_paciente,
            t.nombre_completo AS propietario,
            pi.nombre AS tipo_examen
        FROM certificados c
        LEFT JOIN pacientes p ON c.paciente_id = p.id
        LEFT JOIN tutores t ON p.tutor_id = t.id
        LEFT JOIN plantilla_informe pi ON c.tipo_estudio = pi.id
        WHERE c.id = ?
          AND c.veterinario_id = ?
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);
    $informes = [];

    foreach ($ids as $id) {
        $stmt->bind_param('ii', $id, $usuarioId);
        $stmt->execute();

        $cert = $stmt->get_result()->fetch_assoc();

        if (!$cert) {
            throw new Exception('Uno de los informes no existe o no tienes permisos para utilizarlo.');
        }

        $paciente = trim((string)($cert['paciente'] ?? ''));
        $propietario = trim((string)($cert['propietario'] ?? ''));
        $codigo = trim((string)($cert['codigo_paciente'] ?? ''));

        if (!empty($cert['manual_data'])) {
            $manual = json_decode($cert['manual_data'], true);

            if (is_array($manual)) {
                if ($paciente === '') {
                    $paciente = trim((string)($manual['paciente'] ?? ''));
                }

                if ($propietario === '') {
                    $propietario = trim((string)($manual['propietario'] ?? ($manual['tutor_nombre'] ?? '')));
                }

                if ($codigo === '') {
                    $codigo = trim((string)($manual['codigo_paciente'] ?? ($manual['cod_paciente'] ?? '')));
                }
            }
        }

        $cert['paciente'] = $paciente !== '' ? $paciente : '-';
        $cert['propietario'] = $propietario !== '' ? $propietario : '-';
        $cert['codigo_paciente'] = $codigo;
        $cert['tipo_examen'] = trim((string)($cert['tipo_examen'] ?? '')) ?: '-';

        $informes[] = $cert;
    }

    $baseDir = realpath(__DIR__ . '/../../..');

    if (!$baseDir) {
        throw new Exception('No se pudo resolver la ruta de los archivos.');
    }

    $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'vetmind_mail_mass_'
        . $usuarioId
        . '_'
        . uniqid();

    if (!@mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
        throw new Exception('No se pudo preparar el envío.');
    }

    $attachments = [];
    $nombresUsados = [];
    $totalPdfBytes = 0;
    $historialInformes = [];

    foreach ($informes as $cert) {
        $archivoPdf = trim((string)($cert['archivo_pdf'] ?? ''));

        if ($archivoPdf === '') {
            throw new Exception('El informe de ' . $cert['paciente'] . ' no tiene PDF disponible.');
        }

        $pdfPath = $baseDir . '/' . ltrim($archivoPdf, '/');

        if (!is_file($pdfPath)) {
            throw new Exception(
                'No se encontró el PDF de ' .
                $cert['paciente'] .
                '.'
            );
        }

        $tamanoPdf = filesize($pdfPath);

        if ($tamanoPdf === false) {
            throw new Exception(
                'No se pudo comprobar el tamaño del PDF de ' .
                $cert['paciente'] .
                '.'
            );
        }

        $totalPdfBytes += $tamanoPdf;

        if ($totalPdfBytes > VM_MAIL_MAX_PDFS_BYTES) {
            $pesoActual = number_format(
                $totalPdfBytes / 1024 / 1024,
                1,
                ',',
                '.'
            );

            throw new Exception(
                'Los informes seleccionados pesan ' .
                $pesoActual .
                ' MB. El máximo permitido por envío es 12 MB. ' .
                'Quita uno o más informes e intenta nuevamente.'
            );
        }

        $nombre = vmNombrePdf(
            $cert['paciente'],
            $cert['codigo_paciente']
        );

        $nombreOriginal = $nombre;
        $contador = 2;

        while (isset($nombresUsados[$nombre])) {
            $nombre = preg_replace(
                '/\.pdf$/i',
                '_' . $contador . '.pdf',
                $nombreOriginal
            );
            $contador++;
        }

        $nombresUsados[$nombre] = true;

        $tmpFile = $tmpDir . DIRECTORY_SEPARATOR . $nombre;

        if (!@copy($pdfPath, $tmpFile)) {
            throw new Exception(
                'No se pudo preparar el PDF de ' .
                $cert['paciente'] .
                '.'
            );
        }

        $tmpFiles[] = $tmpFile;
        $attachments[] = $tmpFile;

        $historialInformes[] = [
            'id' => (int)$cert['id'],
            'paciente' => $cert['paciente'],
            'propietario' => $cert['propietario'],
            'tipo_examen' => $cert['tipo_examen'],
            'fecha_examen' => $cert['fecha_examen'] ?? null,
            'nombre_pdf' => $nombre
        ];
    }

    $cantidad = count($informes);

    $subject = $cantidad === 1
        ? 'Informe veterinario - ' . $informes[0]['paciente']
        : 'Informes veterinarios - ' . $cantidad . ' informes';

    $filas = '';

    foreach ($informes as $cert) {
        $fecha = !empty($cert['fecha_examen'])
            ? date('d-m-Y', strtotime($cert['fecha_examen']))
            : '-';

        $filas .= '
            <tr>
                <td style="padding:7px;border-bottom:1px solid #eee;"><strong>'
                    . htmlspecialchars($cert['paciente'], ENT_QUOTES, 'UTF-8')
                    . '</strong></td>
                <td style="padding:7px;border-bottom:1px solid #eee;">'
                    . htmlspecialchars($cert['tipo_examen'], ENT_QUOTES, 'UTF-8')
                    . '</td>
                <td style="padding:7px;border-bottom:1px solid #eee;">'
                    . htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8')
                    . '</td>
            </tr>';
    }

    $body = '
        <div style="background:#f5f5f5;padding:20px 0;">
            <div style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #eee;border-radius:8px;overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #eee;">
                    <h2 style="margin:0;font:18px Arial,sans-serif;color:#333;">Informes veterinarios</h2>
                    <p style="margin:5px 0 0;font:12px Arial,sans-serif;color:#777;">
                        Se adjuntan ' . $cantidad . ' informe(s) en formato PDF.
                    </p>
                </div>

                <div style="padding:16px 20px;font-family:Arial,sans-serif;color:#333;">
                    <p>Hola,</p>
                    <p>Adjuntamos los siguientes informes veterinarios:</p>

                    <table cellspacing="0" cellpadding="0" style="width:100%;font-size:13px;border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="padding:7px;text-align:left;border-bottom:1px solid #ddd;">Paciente</th>
                                <th style="padding:7px;text-align:left;border-bottom:1px solid #ddd;">Examen</th>
                                <th style="padding:7px;text-align:left;border-bottom:1px solid #ddd;">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>' . $filas . '</tbody>
                    </table>
                </div>

                <div style="padding:14px 20px;background:#fafafa;border-top:1px solid #eee;text-align:center;">
                    <span style="font-size:11px;color:#666;">Enviado desde la plataforma Vet-Mind.</span>
                </div>
            </div>
        </div>
    ';

    $svcPath = __DIR__ . '/../../../funciones/emailService.php';

    if (!file_exists($svcPath)) {
        throw new Exception('No se encontró el servicio de correo.');
    }

    require_once $svcPath;

    $mailer = new EmailService();

    $nombreRemitente = $_SESSION['nombre_usuario'] ?? '';

    if ($nombreRemitente === '') {
        $stUser = $mysqli->prepare("SELECT nombres, apellidos FROM usuarios WHERE id = ? LIMIT 1");
        $stUser->bind_param('i', $usuarioId);
        $stUser->execute();

        $usuario = $stUser->get_result()->fetch_assoc();

        if ($usuario) {
            $nombreRemitente = trim('Dr. ' . $usuario['nombres'] . ' ' . $usuario['apellidos']);
        }
    }

    if ($nombreRemitente !== '') {
        $mailer->overrideFrom($nombreRemitente);
    }

    require_once __DIR__ . '/email_historial.php';

    $historialId = vmCrearHistorialEmail(
        $mysqli,
        $usuarioId,
        'masivo',
        [$destinatario],
        $subject,
        $historialInformes
    );

    $resp = $mailer->send(
        [$destinatario],
        $subject,
        $body,
        $attachments
    );

    if (($resp['status'] ?? '') !== 'success') {
        $mensajeError = $resp['message'] ?? 'No se pudo enviar el correo.';

        vmFinalizarHistorialEmail(
            $mysqli,
            $historialId,
            'error',
            $mensajeError
        );

        throw new Exception($mensajeError);
    }

    vmFinalizarHistorialEmail(
        $mysqli,
        $historialId,
        'success'
    );

    echo json_encode([
        'status' => 'success',
        'message' => $cantidad .
            ' informe(s) enviado(s) correctamente a ' .
            $destinatario .
            '.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);

} finally {
    foreach ($tmpFiles as $tmpFile) {
        if (is_file($tmpFile)) {
            @unlink($tmpFile);
        }
    }

    if ($tmpDir && is_dir($tmpDir)) {
        @rmdir($tmpDir);
    }
}