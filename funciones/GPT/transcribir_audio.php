<?php
// funciones/GPT/transcribir_audio.php

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Santiago');

require_once(dirname(__DIR__, 2) . "/configP.php");

session_start();

/////////////////////////////////////////////////////////////////
// Motor de transcripción manual para pruebas.
// Valores:
// - ''       => usa AssemblyAI en este mismo archivo (comportamiento actual)
// - 'assembly'=> igual que '' (AssemblyAI)
// - 'grok'   => deriva a funciones/GPT/transcribir_audio_grok.php
// $motor_stt = 'deepgram';
$motor_stt = 'assembly_v3';
// $motor_stt = 'openai_4o';
// $motor_stt = '';

$motor_stt = strtolower(trim($motor_stt));


// Banco STT: con test_token salta la sesión (userId ficticio) y fuerza el motor.
if (isset($_POST['test_token']) && hash_equals('gondolengua', (string)$_POST['test_token'])) {
    $_SESSION['usuario_id'] = 999999;
    if (!empty($_POST['motor'])) {
        $motor_stt = strtolower(trim((string)$_POST['motor']));
    }
}

// Flujo doble / autenticado: elegir motor por POST (sin test_token).
$motoresPermitidos = ['assembly', 'assembly_v3', 'deepgram', 'grok', 'openai_4o'];
if (!empty($_POST['motor'])) {
    $m = strtolower(trim((string)$_POST['motor']));
    if (in_array($m, $motoresPermitidos, true)) {
        $motor_stt = $m;
    }
}

if ($motor_stt === 'grok') {
    require_once(__DIR__ . '/transcripciones/transcribir_audio_grok.php');
    exit;
}
if ($motor_stt === 'deepgram') {
    require_once(__DIR__ . '/transcripciones/transcribir_audio_deepgram.php');
    exit;
}
if ($motor_stt === 'openai_4o') {
    require_once(__DIR__ . '/transcripciones/transcribir_audio_openai_4o.php');
    exit;
}
if ($motor_stt === 'assembly_v3') {
    require_once(__DIR__ . '/transcripciones/transcribir_audio_assembly_v3.php');
    exit;
}

echo json_encode([
    'status'  => 'error',
    'message' => 'Motor STT no reconocido: ' . $motor_stt
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
