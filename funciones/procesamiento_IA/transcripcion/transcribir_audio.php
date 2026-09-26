<?php
// // funciones/procesamiento_IA/transcripcion/transcribir_audio.php

declare(strict_types=1);

header(
    'Content-Type: application/json; charset=utf-8'
);

date_default_timezone_set(
    'America/Santiago'
);


$ROOT_DIR = dirname(__DIR__, 3);


require_once(
    $ROOT_DIR
    . '/configP.php'
);

require_once(
    $ROOT_DIR
    . '/funciones/session/funcionesSesion.php'
);


configurarErroresAplicacion(true);
iniciarSesionSegura();


/*
 * Este endpoint solo acepta POST.
 */
if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    http_response_code(405);
    header('Allow: POST');

    echo json_encode([
        'status'  => 'error',
        'message' => 'Método HTTP no permitido.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


/*
 * Sesión VetMind real obligatoria.
 */
$userId =
    isset($_SESSION['usuario_id'])
        ? (int)$_SESSION['usuario_id']
        : 0;


if ($userId <= 0) {

    http_response_code(401);

    echo json_encode([
        'status'  => 'error',
        'message' => 'Sesión no válida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


/*
 * Toda petición debe pertenecer a la sesión
 * que la originó.
 */
validarTokenCsrf();


/*
 * Configuración centralizada de los motores STT.
 */
$configStt = (require __DIR__ . '/../config/motores.php')['transcripcion'];

$motor_stt = $configStt['motor_por_defecto'];
$motoresPermitidos = array_keys($configStt['parametros']);


if (!empty($_POST['motor'])) {

    $motorSolicitado =
        strtolower(
            trim(
                (string)$_POST['motor']
            )
        );


    if (
        !in_array(
            $motorSolicitado,
            $motoresPermitidos,
            true
        )
    ) {
        http_response_code(400);

        echo json_encode([
            'status'  => 'error',
            'message' => 'Motor STT no válido.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }


    $motor_stt =
        $motorSolicitado;
}


/*
 * Los archivos de motores solo pueden ejecutarse
 * pasando por este dispatcher.
 */
define(
    'VETMIND_STT_DISPATCH',
    true
);



$configMotor = $configStt['parametros'][$motor_stt] ?? null;
$rutaRelativa = $configMotor['archivo'] ?? '';
$rutaMotor = dirname(__DIR__) . '/' . $rutaRelativa;

if (!is_array($configMotor) || !is_string($rutaRelativa)
    || $rutaRelativa === '' || !is_file($rutaMotor)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Motor STT no disponible.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require $rutaMotor;
exit;
