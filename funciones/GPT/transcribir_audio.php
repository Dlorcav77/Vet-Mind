<?php
// funciones/GPT/transcribir_audio.php

declare(strict_types=1);

header(
    'Content-Type: application/json; charset=utf-8'
);

date_default_timezone_set(
    'America/Santiago'
);


$ROOT_DIR = dirname(__DIR__, 2);


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
 * Motor por defecto.
 */
$motor_stt = 'assembly_v3';


$motoresPermitidos = [
    'assembly',
    'assembly_v3',
    'deepgram',
    'grok',
    'openai_4o'
];


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


if ($motor_stt === 'grok') {

    require_once(
        __DIR__
        . '/transcripciones/transcribir_audio_grok.php'
    );

    exit;
}


if ($motor_stt === 'deepgram') {

    require_once(
        __DIR__
        . '/transcripciones/transcribir_audio_deepgram.php'
    );

    exit;
}


if ($motor_stt === 'openai_4o') {

    require_once(
        __DIR__
        . '/transcripciones/transcribir_audio_openai_4o.php'
    );

    exit;
}


if ($motor_stt === 'assembly_v3') {

    require_once(
        __DIR__
        . '/transcripciones/transcribir_audio_assembly_v3.php'
    );

    exit;
}


/*
 * "assembly" se mantiene en la lista histórica,
 * pero actualmente no tiene dispatcher activo.
 */
http_response_code(400);

echo json_encode([
    'status'  => 'error',
    'message' =>
        'Motor STT no reconocido: '
        . $motor_stt
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;