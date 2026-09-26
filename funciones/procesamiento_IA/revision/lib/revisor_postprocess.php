<?php
declare(strict_types=1);

// SEGURIDAD: respuesta cortada o vacia => NO decir "sin problemas". Devolver error.
if ($finish === 'length' || trim($content) === '') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'El revisor no entrego una respuesta completa (finish=' . ($finish ?: 'vacio') . '). No se puede confiar en el resultado; reintenta.',
        'usage'   => $usageOut,
        'raw'     => $content,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$clean  = trim(preg_replace('/```[a-z]*|```/i', '', $content));
$parsed = json_decode($clean, true);

if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'No se pudo interpretar la respuesta del revisor (JSON invalido).',
        'usage'   => $usageOut,
        'raw'     => $content,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'status' => 'success',
    'items'  => $parsed['items'],
    'raw'    => $content,
    'rid'    => $rid,
    'usage'  => $usageOut,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
