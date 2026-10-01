<?php
declare(strict_types=1);

/**
 * Catálogo anatómico compartido.
 *
 * stt: palabras utilizadas por el validador.
 * patron: expresiones utilizadas para reconocer órganos en el dictado.
 *
 * No incluir errores STT como nombres anatómicos válidos.
 */

$catalogo = [
    ['stt' => ['vejiga'],       'patron' => 'vejiga(?:\s+urinaria)?'],
    ['stt' => ['riñon', 'riñones'], 'patron' => 'riñ[oó]n(?:es)?'],
    ['stt' => ['bazo'],         'patron' => 'bazo'],
    ['stt' => ['higado'],       'patron' => 'h[ií]gado'],
    ['stt' => ['vesicula'],     'patron' => 'ves[ií]cula(?:\s+biliar)?'],
    ['stt' => ['estomago'],     'patron' => 'est[oó]mago'],
    ['stt' => ['pancreas'],     'patron' => 'p[aá]ncreas'],
    ['stt' => ['linfonodulos'], 'patron' => 'linfon[oó]dulos?|linfonodos?'],
    [
        'nombre' => 'Adrenal',

        'stt' => [
            'adrenal',
            'adrenales'
        ],

        'patron' =>
            '(?:gl[aá]ndulas?\s+)?'
            . '(?:adrenal(?:es)?|suprarrenal(?:es)?)',

        'sinonimos' => [
            'glándula adrenal',
            'glándula suprarrenal',
            'suprarrenal'
        ],

        'errores_stt' => [
            'adrenalina',
            'arenal'
        ],

        'lateralidad' => true,

        'atributos' => [
            'tamaño',
            'polo_craneal',
            'polo_caudal',
            'ecogenicidad',
            'forma'
        ]
    ],
    ['stt' => ['yeyuno'],       'patron' => 'yeyuno'],
    ['stt' => ['ileon', 'ileum'], 'patron' => '(?:[ií]leon|ileum)'],
    ['stt' => ['duodeno'],      'patron' => 'duodeno'],
    ['stt' => ['colon'],        'patron' => 'col[oó]n'],
    ['stt' => ['prostata'],     'patron' => 'pr[oó]stata'],
    ['stt' => ['ciego'],        'patron' => 'ciego'],
    ['stt' => ['peritoneo'],    'patron' => 'peritoneo'],
    ['stt' => ['ovario'],       'patron' => 'ovarios?'],
    ['stt' => ['utero'],        'patron' => '[uú]tero'],
    [
        'stt' => ['cuerno'],
        'patron' => 'cuernos?\s+uterinos?'
    ],
    [
        'stt' => ['cuerpo'],
        'patron' => 'cuerpo\s+uterino'
    ],
    ['stt' => ['testiculos'], 'patron' => 'test[ií]culos?']
];

$diccionario = [];
$patrones = [];

foreach ($catalogo as $item) {
    array_push($diccionario, ...$item['stt']);

    if (!empty($item['patron'])) {
        $patrones[] = $item['patron'];
    }
}

return [
    'stt' => array_values(array_unique($diccionario)),
    'patron' => implode('|', $patrones),
    'catalogo' => $catalogo
];