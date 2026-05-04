<?php
// admin/certificado/services/limpiar_temporales_certificados_cli.php

require_once __DIR__ . '/limpiar_temporales_certificados.php';

$resultado = limpiarTemporalesCertificados();

if (PHP_SAPI === 'cli') {
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;