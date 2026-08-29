<?php

// admin/certificado/envio_email/send_certificado.php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config.php';

function _vm_slug_filename($text) {
    $text = (string)$text;
    $text = trim($text);

    $t = @iconv(
        'UTF-8',
        'ASCII//TRANSLIT//IGNORE',
        $text
    );

    if ($t !== false) {
        $text = $t;
    }

    $text = strtolower($text);
    $text = preg_replace('/\s+/', '_', $text);
    $text = preg_replace('/[^a-z0-9\-_]/', '', $text);
    $text = preg_replace('/_+/', '_', $text);
    $text = trim($text, '_');

    return $text !== ''
        ? $text
        : 'informe';
}

function _vm_slug_codigo($text) {
    $text = (string)$text;
    $text = trim($text);

    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($t !== false) $text = $t;

    $text = strtolower($text);
    $text = preg_replace('/\s+/', '', $text);
    $text = preg_replace('/[^a-z0-9\-_]/', '', $text);
    return $text;
}

function _vm_nombre_adjunto_pdf($paciente, $codigoPaciente = '') {
    $base = _vm_slug_filename($paciente);
    $codigo = _vm_slug_codigo($codigoPaciente);

    if ($codigo !== '') {
        return $base . '(' . $codigo . ').pdf';
    }

    return $base . '.pdf';
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Método no permitido.');
    }

    validarTokenCsrf();
    credenciales('certificado', 'listar');

    $usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
    $certificado_id = isset($_POST['certificado_id']) ? (int)$_POST['certificado_id'] : 0;
    $destinatarios  = $_POST['destinatarios'] ?? [];

    if (!$usuario_id) {
        throw new Exception('Sesión no válida.');
    }

    if (!$certificado_id) {
        throw new Exception('Falta certificado_id.');
    }

    if (!is_array($destinatarios) || count($destinatarios) === 0) {
        throw new Exception('Debes enviar al menos un destinatario.');
    }

    $destinatarios = array_values(array_unique(array_map('trim', $destinatarios)));
    $emailRegex    = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
    $destinatarios = array_filter($destinatarios, fn($e) => preg_match($emailRegex, $e));

    if (!count($destinatarios)) {
        throw new Exception('No hay destinatarios válidos.');
    }

    $mysqli = conn();

    $sql = "
        SELECT 
            c.id,
            c.fecha_examen,
            c.archivo_pdf,
            c.tipo_ingreso,
            c.manual_data,
            p.nombre AS paciente,
            p.codigo_paciente AS codigo_paciente,
            t.nombre_completo AS propietario,
            pi.nombre AS tipo_examen
        FROM certificados c
        LEFT JOIN pacientes p           ON c.paciente_id   = p.id
        LEFT JOIN tutores t             ON p.tutor_id      = t.id
        LEFT JOIN plantilla_informe pi  ON c.tipo_estudio  = pi.id
        WHERE c.id = ? AND c.veterinario_id = ?
        LIMIT 1
    ";
    $st = $mysqli->prepare($sql);
    $st->bind_param('ii', $certificado_id, $usuario_id);
    $st->execute();
    $res  = $st->get_result();
    $cert = $res->fetch_assoc();

    if (!$cert) {
        throw new Exception('Certificado no encontrado o sin permisos.');
    }

    $paciente       = trim((string)($cert['paciente'] ?? ''));
    $propietario    = trim((string)($cert['propietario'] ?? ''));
    $tipoExamen     = trim((string)($cert['tipo_examen'] ?? ''));
    $fechaExamen    = !empty($cert['fecha_examen']) ? date('d-m-Y', strtotime($cert['fecha_examen'])) : '-';
    $codigoPaciente = trim((string)($cert['codigo_paciente'] ?? ''));

    if (!empty($cert['manual_data'])) {
        $m = json_decode($cert['manual_data'], true);

        if (is_array($m)) {
            if ($paciente === '') {
                $paciente = trim((string)($m['paciente'] ?? ''));
            }
            if ($propietario === '') {
                $propietario = trim((string)($m['propietario'] ?? ($m['tutor_nombre'] ?? '')));
            }
            if ($codigoPaciente === '') {
                $codigoPaciente = trim((string)($m['codigo_paciente'] ?? ($m['cod_paciente'] ?? '')));
            }
        }
    }

    if ($paciente === '')    $paciente = '-';
    if ($propietario === '') $propietario = '-';
    if ($tipoExamen === '')  $tipoExamen = '-';

    $subject = "Informe de {$tipoExamen} - Paciente: {$paciente}";

    $body = '
    <div style="background:#f5f5f5;padding:20px 0;">
        <div style="max-width:540px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #eee;">
            <div style="padding:16px 20px 10px 20px;border-bottom:1px solid #eee;">
                <h2 style="margin:0;font-size:18px;font-family:Arial,sans-serif;color:#333;">Informe veterinario</h2>
                <p style="margin:4px 0 0 0;font-size:12px;color:#777;">Se adjunta el informe en PDF al final de este correo.</p>
            </div>
            <div style="padding:16px 20px 4px 20px;font-family:Arial,sans-serif;color:#333;">
                <p style="margin:0 0 10px 0;">Hola,</p>
                <p style="margin:0 0 12px 0;">Adjuntamos el informe del examen realizado.</p>
                <table cellspacing="0" cellpadding="0" style="width:100%;font-size:13px;">
                    <tr>
                        <td style="padding:4px 0;width:140px;color:#555;">Paciente:</td>
                        <td style="padding:4px 0;"><strong>' . htmlspecialchars($paciente, ENT_QUOTES, "UTF-8") . '</strong></td>
                    </tr>' .
                    ($codigoPaciente !== '' && $codigoPaciente !== '-'
                        ? '<tr>
                            <td style="padding:4px 0;color:#555;">Cod. Paciente:</td>
                            <td style="padding:4px 0;">' . htmlspecialchars($codigoPaciente, ENT_QUOTES, "UTF-8") . '</td>
                        </tr>'
                        : ''
                    ) . '
                    <tr>
                        <td style="padding:4px 0;color:#555;">Propietario:</td>
                        <td style="padding:4px 0;">' . htmlspecialchars($propietario, ENT_QUOTES, "UTF-8") . '</td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;color:#555;">Tipo de examen:</td>
                        <td style="padding:4px 0;">' . htmlspecialchars($tipoExamen, ENT_QUOTES, "UTF-8") . '</td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;color:#555;">Fecha de examen:</td>
                        <td style="padding:4px 0;">' . $fechaExamen . '</td>
                    </tr>
                </table>
                <p style="margin:14px 0 0 0;font-size:12px;color:#666;">*Si no esperaba este correo puede ignorarlo.</p>
            </div>
            <div style="margin-top:14px;padding:14px 20px;background:#fafafa;border-top:1px solid #eee;text-align:center;">
                <span style="display:inline-block;vertical-align:middle;margin-right:6px;">
                    <img src="https://app.vet-mind.cl/assets/img/photos/logo0.1.png" alt="VetMind" style="height:36px;display:block;">
                </span>
                <span style="display:inline-block;vertical-align:middle;font-size:11px;color:#666;">
                    Enviado desde la plataforma Vet-Mind.
                </span>
            </div>
        </div>
    </div>';

    $attachments = [];
    $tmpDir = null;
    $tmpFile = null;

    $codigoPaciente = $cert['codigo_paciente'] ?? '';

    if (($codigoPaciente === '' || $codigoPaciente === null) && !empty($cert['manual_data'])) {
        $m = json_decode($cert['manual_data'], true);
        if (is_array($m)) {
            $codigoPaciente = $m['codigo_paciente'] ?? ($m['cod_paciente'] ?? '');
        }
    }

    $nombreAdjunto = _vm_nombre_adjunto_pdf($paciente, (string)$codigoPaciente);

    if (!empty($cert['archivo_pdf'])) {
        $baseDir = realpath(__DIR__ . '/../../..');
        $rel     = ltrim($cert['archivo_pdf'], '/');
        $pdfPath = $baseDir ? $baseDir . '/' . $rel : null;

        if ($pdfPath && is_file($pdfPath)) {
            $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                    . 'vetmind_mail_' . (int)$usuario_id . '_' . (int)$certificado_id . '_' . uniqid();
            @mkdir($tmpDir, 0700, true);

            $tmpFile = $tmpDir . DIRECTORY_SEPARATOR . $nombreAdjunto;

            if (@copy($pdfPath, $tmpFile)) {
                $attachments[] = $tmpFile;
            } else {
                $attachments[] = $pdfPath;
                $tmpFile = null;
            }
        }
    }

    $svcPath = __DIR__ . '/../../../funciones/emailService.php';
    if (!file_exists($svcPath)) {
        throw new Exception('No se encontró el servicio de correo.');
    }

    require_once $svcPath;

    $mailer = new EmailService();

    $nombreRemitente = $_SESSION['nombre_usuario'] ?? '';

    if ($nombreRemitente === '') {
        $stUser = $mysqli->prepare("SELECT nombres, apellidos FROM usuarios WHERE id = ? LIMIT 1");
        $stUser->bind_param('i', $usuario_id);
        $stUser->execute();
        $resUser = $stUser->get_result()->fetch_assoc();

        if ($resUser) {
            $nombreRemitente = trim('Dr. ' . $resUser['nombres'] . ' ' . $resUser['apellidos']);
        }
    }

    if ($nombreRemitente !== '') {
        $mailer->overrideFrom($nombreRemitente);
    }

    $resp = $mailer->send($destinatarios, $subject, $body, $attachments);

    if ($tmpFile && is_file($tmpFile)) {
        @unlink($tmpFile);
    }
    if ($tmpDir && is_dir($tmpDir)) {
        @rmdir($tmpDir);
    }

    echo json_encode(
        $resp['status'] === 'success'
            ? ['status' => 'success', 'message' => 'Correo enviado correctamente.']
            : ['status' => 'error', 'message' => $resp['message']]
    );

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}