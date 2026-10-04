<?php

declare(strict_types=1);

/** @var string $finish */
/** @var string $content */
/** @var string $informe */
/** @var string $rid */
/** @var array $usageOut */


/*
 * Respuesta incompleta o vacía:
 * nunca interpretar esto como "sin problemas".
 */
if ($finish === 'length' || trim($content) === '') {
    echo json_encode([
        'status' => 'error',
        'message' =>
            'El revisor no entregó una respuesta completa '
            . '(finish=' . ($finish ?: 'vacío') . '). '
            . 'No se puede confiar en el resultado.',
        'usage' => $usageOut ?? null,
        'raw' => $content,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


/*
 * Limpiar fences por seguridad, aunque el prompt exige JSON puro.
 */
$clean = trim(
    preg_replace(
        '/```[a-z]*|```/i',
        '',
        $content
    ) ?? $content
);

$parsed = json_decode($clean, true);


/*
 * Validar estructura básica.
 */
if (
    !is_array($parsed)
    || !isset($parsed['items'])
    || !is_array($parsed['items'])
) {
    echo json_encode([
        'status' => 'error',
        'message' =>
            'No se pudo interpretar la respuesta del revisor '
            . '(JSON inválido).',
        'usage' => $usageOut ?? null,
        'raw' => $content,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


/*
 * Confirmar que efectivamente respondió nuestro prompt de revisión.
 */
if (
    ($parsed['debug_revision'] ?? '')
    !== 'vetmind_grok_ok'
) {
    echo json_encode([
        'status' => 'error',
        'message' =>
            'La respuesta del revisor no contiene '
            . 'la firma de validación esperada.',
        'usage' => $usageOut ?? null,
        'raw' => $content,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


/*
 * Tipos permitidos por el nuevo revisor.
 */
$tiposPermitidos = [
    'hallazgo_bajado',
    'atributo_no_reemplazado',
    'cambio_medida',
    'cambio_lateralidad',
    'omitido',
    'organo_omitido',
    'discrepancia_negacion',
    'discrepancia_stt',
    'inventado',
    'mismas_caracteristicas_literal',
];

$severidadesPermitidas = [
    'alta',
    'media',
    'baja',
];


/*
 * Solo estos tipos pueden existir legítimamente sin un texto
 * concreto que subrayar en el informe.
 */
$tiposSinObjetivoPermitido = [
    'omitido',
    'organo_omitido',
];


/*
 * Convertir el HTML final a texto comparable con lo que ve
 * el veterinario.
 *
 * Reemplazamos etiquetas por espacios para evitar unir palabras.
 */
$informeTexto = preg_replace(
    '/<[^>]+>/u',
    ' ',
    $informe
) ?? $informe;

$informeTexto = html_entity_decode(
    $informeTexto,
    ENT_QUOTES | ENT_HTML5,
    'UTF-8'
);

$informeTexto = trim(
    preg_replace(
        '/\s+/u',
        ' ',
        $informeTexto
    ) ?? $informeTexto
);


/*
 * Normaliza únicamente espacios y entidades.
 * No cambia palabras ni contenido clínico.
 */
$normalizarTexto = static function (string $texto): string {
    $texto = html_entity_decode(
        $texto,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    return trim(
        preg_replace(
            '/\s+/u',
            ' ',
            $texto
        ) ?? $texto
    );
};


/*
 * Limitar campos de texto evita que una respuesta anómala
 * termine llenando revision_visual con contenido excesivo.
 */
$limitar = static function (
    string $texto,
    int $maximo
): string {
    $texto = trim($texto);

    if (mb_strlen($texto, 'UTF-8') <= $maximo) {
        return $texto;
    }

    return trim(
        mb_substr(
            $texto,
            0,
            $maximo,
            'UTF-8'
        )
    );
};


$itemsValidos = [];
$itemsDescartados = 0;
$clavesVistas = [];


/*
 * Validar cada alerta propuesta por Grok.
 */
foreach ($parsed['items'] as $item) {
    if (!is_array($item)) {
        $itemsDescartados++;
        continue;
    }

    $tipo = trim(
        (string)($item['tipo'] ?? '')
    );

    $severidad = trim(
        (string)($item['severidad'] ?? '')
    );

    $zona = $limitar(
        (string)($item['zona'] ?? ''),
        160
    );

    $objetivo = $limitar(
        (string)($item['objetivo'] ?? ''),
        500
    );

    $dictadoItem = $limitar(
        (string)($item['dictado'] ?? ''),
        1500
    );

    $informeItem = $limitar(
        (string)($item['informe'] ?? ''),
        1500
    );

    $detalle = $limitar(
        (string)($item['detalle'] ?? ''),
        1500
    );


    /*
     * Tipo desconocido:
     * no permitir que pase al frontend.
     */
    if (!in_array($tipo, $tiposPermitidos, true)) {
        $itemsDescartados++;
        continue;
    }


    /*
     * Severidad inválida.
     */
    if (
        !in_array(
            $severidad,
            $severidadesPermitidas,
            true
        )
    ) {
        $itemsDescartados++;
        continue;
    }


    /*
     * Una alerta sin explicación no es útil.
     */
    if ($detalle === '') {
        $itemsDescartados++;
        continue;
    }


    /*
     * Para problemas que sí existen físicamente en el informe,
     * Grok debe indicar exactamente qué texto debemos subrayar.
     */
    if ($objetivo === '') {
        if (
            !in_array(
                $tipo,
                $tiposSinObjetivoPermitido,
                true
            )
        ) {
            $itemsDescartados++;
            continue;
        }
    } else {
        $objetivoBusqueda =
            $normalizarTexto($objetivo);

        /*
         * Si Grok inventó o parafraseó el objetivo y ese texto
         * no existe realmente en el informe, descartamos la alerta.
         */
        if (
            $objetivoBusqueda === ''
            || mb_stripos(
                $informeTexto,
                $objetivoBusqueda,
                0,
                'UTF-8'
            ) === false
        ) {
            $itemsDescartados++;
            continue;
        }
    }


    /*
     * Evitar alertas repetidas.
     */
    $clave = implode('|', [
        $tipo,
        mb_strtolower($zona, 'UTF-8'),
        mb_strtolower($objetivo, 'UTF-8'),
        mb_strtolower($detalle, 'UTF-8'),
    ]);

    if (isset($clavesVistas[$clave])) {
        $itemsDescartados++;
        continue;
    }

    $clavesVistas[$clave] = true;


    $itemsValidos[] = [
        'severidad' => $severidad,
        'tipo' => $tipo,
        'zona' => $zona,
        'objetivo' => $objetivo,
        'dictado' => $dictadoItem,
        'informe' => $informeItem,
        'detalle' => $detalle,
    ];
}


/*
 * Respuesta ya validada para frontend.
 */
echo json_encode([
    'status' => 'success',
    'items' => $itemsValidos,
    'debug_revision' => 'vetmind_grok_ok',
    'validacion' => [
        'recibidos' => count($parsed['items']),
        'aceptados' => count($itemsValidos),
        'descartados' => $itemsDescartados,
    ],
    'raw' => $content,
    'finish_reason' => $finish,
    'rid' => $rid ?? null,
    'usage' => $usageOut ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);