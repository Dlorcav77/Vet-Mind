<?php
declare(strict_types=1);

$configFlujo = require dirname(__DIR__) . '/procesamiento_IA/config/flujo.php';

if (($configFlujo['transcripcion'] ?? 'actual') === 'nuevo') {
    require dirname(__DIR__) . '/procesamiento_IA/transcripcion/transcribir_doble.php';
    exit;
}

require __DIR__ . '/transcribir_doble_actual.php';
