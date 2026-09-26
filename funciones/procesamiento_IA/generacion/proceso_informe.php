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

// Interpretación clínica compartida por todos los generadores.
$interpretacionData = null;

$configFlujo = require dirname(__DIR__) . '/config/flujo.php';
$modoInterpretacion = (string)($configFlujo['interpretacion'] ?? 'actual');
$modoPrueba = trim((string)($_POST['modo_interpretacion_prueba'] ?? ''));

if ($modoPrueba !== '') {
    if (
        empty($configFlujo['banco_pruebas_habilitado'])
        || !in_array($modoPrueba, ['actual', 'php', 'ia'], true)
    ) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Modo de prueba no autorizado.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $modoInterpretacion = $modoPrueba;
}

header('X-VetMind-interpretacion: ' . $modoInterpretacion);

if ($modoInterpretacion !== 'actual') {
    require_once dirname(__DIR__) . '/interpretacion/proceso_interpretacion.php';

    $mysqliInterpretacion = null;

    try {
        $mysqliInterpretacion = conn();

        if (!$mysqliInterpretacion instanceof mysqli) {
            throw new RuntimeException('No se pudo conectar a la base de datos.');
        }

        $interpretacionData = interpretacion_ejecutar(
            $mysqliInterpretacion,
            [
                'usuario_id'    => $userId,
                'flujo_id'      => (string)($_POST['flujo_id'] ?? ''),
                'texto'         => (string)($_POST['texto'] ?? ''),
                'plantilla_base'=> (string)($_POST['plantilla_base'] ?? ''),
                'especie'       => (string)($_POST['especie'] ?? ''),
                'raza'          => (string)($_POST['raza'] ?? ''),
                'edad'          => (string)($_POST['edad'] ?? ''),
                'sexo'          => (string)($_POST['sexo'] ?? ''),
                'tipo_estudio'  => (string)($_POST['tipo_estudio'] ?? ''),
            ],
            (string)($OPENAI_API_KEY ?? ''),
            $modoPrueba !== '' ? $modoPrueba : null
        );
    } catch (Throwable $e) {
        app_log('interpretacion_error', [
            'modo' => $modoInterpretacion,
            'error' => $e->getMessage()
        ], 'ERROR');

        http_response_code(500);

        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo completar la interpretación clínica.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    } finally {
        if ($mysqliInterpretacion instanceof mysqli) {
            $mysqliInterpretacion->close();
        }
    }
}

define('VETMIND_GPT_DISPATCH', true);

require $rutaMotor;
exit;
