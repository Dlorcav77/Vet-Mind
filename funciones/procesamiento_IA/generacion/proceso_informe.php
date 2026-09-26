<?php
// funciones/procesamiento_IA/generacion/proceso_informe.php

declare(strict_types=1);

header('X-VetMind-generacion: nuevo');

$ROOT_DIR = dirname(__DIR__, 3);
$FUNC_DIR = dirname(__DIR__, 2);
$GEN_DIR = __DIR__;

require_once($FUNC_DIR . '/session/funcionesSesion.php');

configurarErroresAplicacion(true);
iniciarSesionSegura();

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status'=>'error','message'=>'Método HTTP no permitido.']);
    exit;
}

$userId = (int)($_SESSION['usuario_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['status'=>'error','message'=>'Sesión no válida.']);
    exit;
}

validarTokenCsrf();

require_once($FUNC_DIR . '/conn/conn.php');
require_once($ROOT_DIR . '/configP.php');
require_once($FUNC_DIR . '/logs/logger.php');

require_once($GEN_DIR . '/lib/gpt_prompt.php');
require_once($GEN_DIR . '/lib/gpt_postprocess.php');
require_once($GEN_DIR . '/lib/gpt_origen.php');
require_once(dirname(__DIR__) . '/comun/ia_store.php');

$config = require dirname(__DIR__) . '/config/motores.php';
$configGen = $config['generacion'];

$motor = $configGen['motor_activo'];


$configMotor = $configGen['parametros'][$motor] ?? null;
$rutaRelativa = $configMotor['archivo'] ?? '';
$rutaMotor = dirname(__DIR__) . '/' . $rutaRelativa;

if (!is_array($configMotor) || !is_string($rutaRelativa)
    || $rutaRelativa === '' || !is_file($rutaMotor)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Motor de generación no disponible.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

define('VETMIND_GPT_DISPATCH', true);

require $rutaMotor;
exit;
