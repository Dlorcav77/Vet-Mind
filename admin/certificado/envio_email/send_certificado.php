<?php
// certificado/envio_email/send_certificado.php
header('Content-Type: application/json; charset=utf-8');

// primero cargamos config, que en tu caso ya arma la sesión
require_once __DIR__ . '/../../config.php';

// por si algún día lo llamas desde otro contexto sin sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    $usuario_id     = $_SESSION['usuario_id'] ?? 0;
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

    // limpiar correos
    $destinatarios = array_values(array_unique(array_map('trim', $destinatarios)));
    $emailRegex    = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
    $destinatarios = array_filter($destinatarios, fn($e) => preg_match($emailRegex, $e));
    if (!count($destinatarios)) {
        throw new Exception('No hay destinatarios válidos.');
    }

    // ====== traer datos del certificado ======
    $mysqli = conn();

    $sql = "
        SELECT 
            c.id,
            c.fecha_examen,
            c.archivo_pdf,
            c.tipo_ingreso,
            p.nombre AS paciente,
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

    $paciente    = $cert['paciente']    ?: '-';
    $propietario = $cert['propietario'] ?: '-';
    $tipoExamen  = $cert['tipo_examen'] ?: '-';
    $fechaExamen = $cert['fecha_examen'] ? date('d-m-Y', strtotime($cert['fecha_examen'])) : '-';

    $subject = "Informe de {$tipoExamen} - Paciente: {$paciente}";




    $body = '
    <div style="background:#f5f5f5;padding:20px 0;">
        <div style="max-width:540px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #eee;">
        <div style="padding:16px 20px 10px 20px;border-bottom:1px solid #eee;">
            <h2 style="margin:0;font-size:18px;font-family:Arial,sans-serif;color:#333;">
            Informe veterinario
            </h2>
            <p style="margin:4px 0 0 0;font-size:12px;color:#777;">Se adjunta el informe en PDF al final de este correo.</p>
        </div>
        <div style="padding:16px 20px 4px 20px;font-family:Arial,sans-serif;color:#333;">
            <p style="margin:0 0 10px 0;">Hola,</p>
            <p style="margin:0 0 12px 0;">Adjuntamos el informe del examen realizado.</p>
            <table cellspacing="0" cellpadding="0" style="width:100%;font-size:13px;">
            <tr>
                <td style="padding:4px 0;width:140px;color:#555;">Paciente:</td>
                <td style="padding:4px 0;"><strong>' . htmlspecialchars($paciente, ENT_QUOTES, "UTF-8") . '</strong></td>
            </tr>
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
            <p style="margin:14px 0 0 0;font-size:12px;color:#666;">
            *Si no esperaba este correo puede ignorarlo.
            </p>
        </div>
        <div style="margin-top:14px;padding:14px 20px;background:#fafafa;border-top:1px solid #eee;text-align:center;">
            <span style="display:inline-block;vertical-align:middle;margin-right:6px;">
            <img src="https://app.vet-mind.cl/assets/img/photos/logo0.1.png"
                alt="VetMind"
                style="height:36px;display:block;">
            </span>
            <span style="display:inline-block;vertical-align:middle;font-size:11px;color:#666;">
            Enviado desde la plataforma Vet-Mind.
            </span>
        </div>
        </div>
    </div>
    ';





    // ====== adjunto ======
    $attachments = [];
    if (!empty($cert['archivo_pdf'])) {
        $baseDir = realpath(__DIR__ . '/../../..'); // ajusta si tu raíz está en otro lado
        $rel     = ltrim($cert['archivo_pdf'], '/');
        $pdfPath = $baseDir ? $baseDir . '/' . $rel : null;

        if ($pdfPath && file_exists($pdfPath)) {
            $attachments[] = $pdfPath;
        }
    }

    // ====== usar servicio ======
    // ojo con el nombre del archivo (may/min)
    $svcPath = __DIR__ . '/../../../funciones/emailService.php';
    if (!file_exists($svcPath)) {
        throw new Exception('No se encontró el servicio de correo.');
    }
    require_once $svcPath;

    $mailer = new EmailService();
    
    // 1) primero intentamos con la sesión
    $nombreRemitente = $_SESSION['nombre_usuario'] ?? '';

    // 2) si no hay en sesión, lo buscamos en la tabla usuarios
    if ($nombreRemitente === '') {
        $stUser = $mysqli->prepare("SELECT nombres, apellidos FROM usuarios WHERE id = ? LIMIT 1");
        $stUser->bind_param('i', $usuario_id);
        $stUser->execute();
        $resUser = $stUser->get_result()->fetch_assoc();
        if ($resUser) {
            $nombreRemitente = trim('Dr. ' . $resUser['nombres'] . ' ' . $resUser['apellidos']);
        }
    }

    // 3) si al final tenemos un nombre, lo usamos
    if ($nombreRemitente !== '') {
        $mailer->overrideFrom($nombreRemitente);
    }

    $resp = $mailer->send($destinatarios, $subject, $body, $attachments);

    echo json_encode($resp['status'] === 'success'
        ? ['status' => 'success', 'message' => 'Correo enviado correctamente.']
        : ['status' => 'error',   'message' => $resp['message']]
    );

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
