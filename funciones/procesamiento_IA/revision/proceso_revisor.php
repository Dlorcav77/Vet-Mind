<?php
// funciones/GPT/proceso_ia/proceso_revisor.php

// IA revisora (2a pasada). Compara DICTADO vs INFORME y devuelve SOLO una lista
// de posibles inconsistencias. NO reescribe el informe. Motor: grok-4.3.
/** @var string $rid Generado por el motor de revisión incluido a continuación. */

declare(strict_types=1);

header('X-VetMind-revision: nuevo');

$ROOT_DIR = dirname(__DIR__, 3);   // /
$FUNC_DIR = dirname(__DIR__, 2);   // /funciones

require_once(
    $ROOT_DIR
    . '/funciones/session/funcionesSesion.php'
);

configurarErroresAplicacion(true);
iniciarSesionSegura();


if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status' => 'error',
        'message' => 'Método HTTP no permitido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


$userId =
    isset($_SESSION['usuario_id'])
        ? (int)$_SESSION['usuario_id']
        : 0;

if ($userId <= 0) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión no válida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


validarTokenCsrf();

require_once($FUNC_DIR . "/conn/conn.php");
require_once($ROOT_DIR . "/configP.php");
require_once($FUNC_DIR . "/logs/logger.php");
require_once(dirname(__DIR__) . "/comun/ia_store.php");

require_once(
    dirname(__DIR__)
    . "/interpretacion/lib/interpretacion_origen.php"
);

require_once(
    dirname(__DIR__)
    . "/interpretacion/lib/interpretacion_store.php"
);

date_default_timezone_set('America/Santiago');
header('Content-Type: application/json; charset=utf-8');




$mysqli = conn();

$dictado = trim((string)($_POST['dictado'] ?? ''));
$informe = trim((string)($_POST['informe'] ?? ''));
$plantilla = trim((string)($_POST['plantilla'] ?? ''));

$flujoId = trim(
    (string)($_POST['flujo_id'] ?? '')
);

if ($dictado === '' || $informe === '') {
    echo json_encode(['status'=>'error','message'=>'Falta dictado o informe.']);
    exit;
}

/*
 * Evidencia original del mismo flujo.
 *
 * $origenStt contiene:
 * - texto_a
 * - texto_b
 * - texto_doble
 * - motor_a
 * - motor_b
 * - resueltas
 * - discrepancias
 */
$origenStt = null;

/*
 * Interpretación estructurada utilizada durante
 * la generación del informe.
 */
$interpretacionRegistro = null;
$interpretacionData = null;

if ($flujoId !== '') {
    $origenStt = interpretacion_cargar_origen(
        $mysqli,
        $userId,
        $flujoId,
        $dictado
    );

    $interpretacionRegistro =
        interpretacion_cargar_resultado_flujo(
            $mysqli,
            $userId,
            $flujoId,
            $dictado
        );

    if (
        is_array($interpretacionRegistro)
        && isset($interpretacionRegistro['resultado'])
        && is_array($interpretacionRegistro['resultado'])
    ) {
        $interpretacionData =
            $interpretacionRegistro['resultado'];
    }
}

$config = require dirname(__DIR__) . '/config/motores.php';
$configRevision = $config['revision'];
$motor = $configRevision['motor_activo'];

$configMotor = $configRevision['parametros'][$motor] ?? null;

$rutaRelativa = is_array($configMotor)
    ? ($configMotor['archivo'] ?? '')
    : '';

$rutaMotor = dirname(__DIR__) . '/' . $rutaRelativa;

if (
    !is_string($rutaRelativa)
    || $rutaRelativa === ''
    || !is_file($rutaMotor)
) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Motor de revisión no disponible.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/lib/revisor_prompt.php';

define('VETMIND_REVISOR_DISPATCH', true);
require $rutaMotor;

$flujoIdRevision = $flujoId;

// guardar request en BD (ia_requests)
ia_guardar_request($mysqli, [
    'rid'               => $rid,
    'flujo_id'          => $flujoIdRevision,
    'tipo'              => 'revision',
    'plantilla_id'      => null,
    'provider'          => $motor,
    'model'             => $configMotor['model'],
    'input'             => ['dictado'=>$dictado, 'informe'=>$informe, 'plantilla'=>$plantilla],
    'system'            => $system,
    'prompt'            => $user,
    'content_final'     => $content,
    'prompt_tokens'     => $pt,
    'completion_tokens' => $ct,
    'total_tokens'      => $tt,
    'cost_usd'          => $cost,
    'datetime_ia'       => date('c'),
]);

if ($mysqli instanceof mysqli) {
    @$mysqli->close();
}

require __DIR__ . '/lib/revisor_postprocess.php';
