<?php
declare(strict_types=1);

@set_time_limit(480);
header('Content-Type: application/json; charset=utf-8');

$FUNC_DIR = dirname(__DIR__, 2);
$ROOT_DIR = dirname(__DIR__, 3);

require_once $FUNC_DIR . '/session/funcionesSesion.php';

configurarErroresAplicacion(true);
iniciarSesionSegura();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

$userId = (int)($_SESSION['usuario_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida.']);
    exit;
}

validarTokenCsrf();

require_once $ROOT_DIR . '/configP.php';
require_once $FUNC_DIR . '/conn/conn.php';
require_once __DIR__ . '/lib/banco_store.php';

$modo = trim((string)($_POST['modo'] ?? ''));
$pruebaId = (int)($_POST['prueba_id'] ?? 0);
$iniciada = false;
$inicio = 0;
$mysqli = null;

try {
    $flujo = require dirname(__DIR__) . '/config/flujo.php';

    if (empty($flujo['banco_pruebas_habilitado'])) {
        throw new RuntimeException('El banco de pruebas está deshabilitado.');
    }

    if (!in_array($modo, ['actual', 'php', 'ia'], true)) {
        throw new InvalidArgumentException('Modo de prueba no válido.');
    }

    $mysqli = conn();

    if (!$mysqli instanceof mysqli) {
        throw new RuntimeException('No se pudo conectar a la base de datos.');
    }

    $motores = require dirname(__DIR__) . '/config/motores.php';
    $generador = $motores['generacion']['motor_activo'];
    $modelo = $motores['generacion']['parametros'][$generador]['model'];

    // La primera ejecución crea el caso a partir de un informe existente.
    if ($pruebaId === 0) {
        if ($modo !== 'actual') {
            throw new InvalidArgumentException(
                'La primera variante debe ser actual.'
            );
        }

        $requestId = (int)($_POST['request_id'] ?? 0);

        $stmt = $mysqli->prepare(
            "SELECT r.input_json, r.flujo_id,
                    t.id AS transcripcion_id,
                    t.texto_a, t.texto_doble
             FROM ia_requests r
             INNER JOIN ia_transcripciones t
                ON t.flujo_id = r.flujo_id
             WHERE r.id = ?
               AND r.tipo = 'informe'
               AND t.usuario_id = ?
             ORDER BY t.id DESC
             LIMIT 1"
        );

        $stmt->bind_param('ii', $requestId, $userId);
        $stmt->execute();
        $caso = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$caso) {
            throw new RuntimeException(
                'No se encontró un informe con una transcripción propia.'
            );
        }

        $entrada = json_decode(
            (string)$caso['input_json'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $flujoId = (string)$caso['flujo_id'];
        $transcripcionId = (int)$caso['transcripcion_id'];
        $textoOriginal = trim(
            (string)$caso['texto_a'] . (string)$caso['texto_doble']
        );

        if (!is_array($entrada)
            || trim((string)($entrada['texto'] ?? '')) !== $textoOriginal
            || trim((string)($entrada['plantilla_base'] ?? '')) === '') {
            throw new RuntimeException(
                'El caso no contiene el dictado y la plantilla originales.'
            );
        }

        $entradaJson = json_encode(
            $entrada,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );

        $stmt = $mysqli->prepare(
            'INSERT INTO ia_pruebas
                (usuario_id, transcripcion_id, flujo_id,
                 entrada_json, generador_motor, generador_modelo)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'iissss',
            $userId,
            $transcripcionId,
            $flujoId,
            $entradaJson,
            $generador,
            $modelo
        );

        $stmt->execute();
        $pruebaId = (int)$stmt->insert_id;
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare(
            'SELECT p.*, t.texto_a, t.texto_doble
             FROM ia_pruebas p
             INNER JOIN ia_transcripciones t
                ON t.id = p.transcripcion_id
               AND t.usuario_id = p.usuario_id
             WHERE p.id = ? AND p.usuario_id = ?
             LIMIT 1'
        );

        $stmt->bind_param('ii', $pruebaId, $userId);
        $stmt->execute();
        $caso = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$caso) {
            throw new RuntimeException('Caso de prueba no encontrado.');
        }

        $entrada = json_decode(
            (string)$caso['entrada_json'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $flujoId = (string)$caso['flujo_id'];

        if ($generador !== $caso['generador_motor']
            || $modelo !== $caso['generador_modelo']) {
            throw new RuntimeException(
                'Cambió el generador desde que comenzó la prueba.'
            );
        }

        if (!is_array($entrada)
            || trim((string)($entrada['texto'] ?? ''))
                !== trim((string)$caso['texto_a'] . (string)$caso['texto_doble'])) {
            throw new RuntimeException(
                'El dictado de la prueba no coincide con su transcripción.'
            );
        }
    }

    // Impedir que una variante ya ejecutada se registre dos veces.
    $stmt = $mysqli->prepare(
        'SELECT id FROM ia_prueba_resultados
         WHERE prueba_id = ? AND modo = ? LIMIT 1'
    );
    $stmt->bind_param('is', $pruebaId, $modo);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existente) {
        throw new RuntimeException('Esta variante ya tiene un resultado registrado.');
    }

    // Identificar las nuevas interpretaciones de esta ejecución.
    $ultimoId = 0;

    if ($modo !== 'actual') {
        $stmt = $mysqli->prepare(
            'SELECT COALESCE(MAX(id), 0) AS ultimo
             FROM ia_interpretaciones
             WHERE usuario_id = ? AND flujo_id = ?'
        );
        $stmt->bind_param('is', $userId, $flujoId);
        $stmt->execute();
        $ultimoId = (int)$stmt->get_result()->fetch_assoc()['ultimo'];
        $stmt->close();
    }

    // Reutilizar exclusivamente los campos habituales del generador.
    $post = [];

    foreach ([
        'paciente', 'especie', 'raza', 'edad', 'sexo',
        'tipo_estudio', 'motivo', 'plantilla_base',
        'plantilla_id', 'texto'
    ] as $campo) {
        if (isset($entrada[$campo])) {
            $post[$campo] = (string)$entrada[$campo];
        }
    }

    $post['flujo_id'] = $flujoId;
    $post['modo_interpretacion_prueba'] = $modo;

    // Liberar la sesión antes de llamar al generador.
    $cookie = session_name() . '=' . session_id();
    $csrf = tokenCsrf();
    session_write_close();

    $host = (string)($_SERVER['SERVER_NAME'] ?? '');

    if (!preg_match('/^[a-z0-9.-]+$/i', $host)) {
        throw new RuntimeException('Host del servidor no válido.');
    }

    $url = 'https://' . $host . '/funciones/GPT/proceso_gpt.php';

    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException('No se pudo iniciar cURL.');
    }

    $inicio = hrtime(true);
    $iniciada = true;

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_COOKIE => $cookie,
        CURLOPT_HTTPHEADER => ['X-CSRF-Token: ' . $csrf],
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 420,
    ]);

    $respuesta = curl_exec($ch);
    $errorCurl = curl_errno($ch) ? curl_error($ch) : '';
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $duracionMs = (int)round((hrtime(true) - $inicio) / 1000000);

    if ($errorCurl !== '') {
        throw new RuntimeException('Error de conexión: ' . $errorCurl);
    }

    $generacion = json_decode((string)$respuesta, true);

    if ($http !== 200 || !is_array($generacion)
        || ($generacion['status'] ?? '') !== 'success'
        || empty($generacion['rid'])) {
        throw new RuntimeException(
            'Falló la generación: '
            . (string)($generacion['message'] ?? 'HTTP ' . $http)
        );
    }

    $interpretacionId = null;

    if ($modo !== 'actual') {
        $motorInterpretacion = $modo === 'php'
            ? 'php'
            : $motores['interpretacion']['motor_activo'];

        $stmt = $mysqli->prepare(
            "SELECT id FROM ia_interpretaciones
             WHERE id > ?
               AND usuario_id = ?
               AND flujo_id = ?
               AND motor = ?
               AND estado = 'completado'
             ORDER BY id DESC LIMIT 1"
        );

        $stmt->bind_param(
            'iiss',
            $ultimoId,
            $userId,
            $flujoId,
            $motorInterpretacion
        );

        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$fila) {
            throw new RuntimeException(
                'La generación terminó, pero no se encontró su interpretación.'
            );
        }

        $interpretacionId = (int)$fila['id'];
    }

    $resultadoId = banco_registrar_resultado(
        $mysqli,
        $pruebaId,
        $userId,
        $modo,
        (string)$generacion['rid'],
        $interpretacionId,
        $duracionMs
    );

    echo json_encode([
        'status' => 'success',
        'prueba_id' => $pruebaId,
        'resultado_id' => $resultadoId,
        'modo' => $modo,
        'rid' => $generacion['rid'],
        'interpretacion_id' => $interpretacionId,
        'duracion_ms' => $duracionMs,
        'usage' => $generacion['usage'] ?? null
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    $duracionMs = $inicio > 0
        ? (int)round((hrtime(true) - $inicio) / 1000000)
        : null;

    if ($iniciada && $pruebaId > 0 && $mysqli instanceof mysqli) {
        try {
            $stmt = $mysqli->prepare(
                'INSERT INTO ia_prueba_resultados
                    (prueba_id, modo, estado, duracion_total_ms, error_detalle)
                 VALUES (?, ?, "fallido", ?, ?)'
            );

            $mensaje = $e->getMessage();

            $stmt->bind_param(
                'isis',
                $pruebaId,
                $modo,
                $duracionMs,
                $mensaje
            );
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $ignorado) {
            error_log('VetMind: error al registrar una prueba fallida.');
        }
    }

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'prueba_id' => $pruebaId ?: null,
        'modo' => $modo,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
