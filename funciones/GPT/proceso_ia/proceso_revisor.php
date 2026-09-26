<?php
declare(strict_types=1);

$configFlujo = require dirname(__DIR__, 2)
    . '/procesamiento_IA/config/flujo.php';

if (($configFlujo['revision'] ?? 'actual') === 'nuevo') {
    require dirname(__DIR__, 2)
        . '/procesamiento_IA/revision/proceso_revisor.php';
    exit;
}

require __DIR__ . '/proceso_revisor_actual.php';
