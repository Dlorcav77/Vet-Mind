<?php
declare(strict_types=1);

$configFlujo = require dirname(__DIR__)
    . '/procesamiento_IA/config/flujo.php';

if (($configFlujo['generacion'] ?? 'actual') === 'nuevo') {
    require dirname(__DIR__)
        . '/procesamiento_IA/generacion/proceso_informe.php';
    exit;
}

require __DIR__ . '/proceso_gpt_actual.php';
