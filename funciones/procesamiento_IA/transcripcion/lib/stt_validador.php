<?php
// funciones/procesamiento_IA/transcripcion/lib/stt_validador.php
// Comparación de 2 transcripciones + validador (órganos/conceptos) + armado de bloques.
// Compartido por el banco y por transcribir_doble.php. Sin salida, solo define funciones/listas.
declare(strict_types=1);

// ---- Comparación por código (LCS) ----
function cmp_pre_limpiar(string $t): string {
    return preg_replace('/(\d)\s*([.,])\s*(\d)/u', '$1$2$3', $t);
}
function cmp_norm(string $w): string {
    $w = mb_strtolower($w, 'UTF-8');
    $w = strtr($w, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
    $w = str_replace('-', '', $w);
    $w = preg_replace('/[^0-9a-z.,]/u', '', $w);
    $w = trim($w, '.,');
    if (preg_match('/\d/', $w)) $w = str_replace(',', '.', $w);
    return $w;
}
function cmp_tokens(string $t): array {
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t === '' ? [] : explode(' ', $t);
}
function cmp_es_numero_norm(string $w): bool {
    return (bool)preg_match('/^\d+(\.\d+)?$/', $w);
}

function cmp_comparar(string $textoA, string $textoB): array {
    $textoA = cmp_pre_limpiar($textoA);
    $textoB = cmp_pre_limpiar($textoB);

    $A = cmp_tokens($textoA);
    $B = cmp_tokens($textoB);

    $na = count($A);
    $nb = count($B);

    $nA = array_map('cmp_norm', $A);
    $nB = array_map('cmp_norm', $B);

    $dp = array_fill(0, $na + 1, array_fill(0, $nb + 1, 0));

    for ($i = $na - 1; $i >= 0; $i--) {
        for ($j = $nb - 1; $j >= 0; $j--) {
            if ($nA[$i] !== '' && $nA[$i] === $nB[$j]) {
                $dp[$i][$j] = $dp[$i + 1][$j + 1] + 1;
            } else {
                $dp[$i][$j] = max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }
    }

    $i = 0;
    $j = 0;

    $disc = [];
    $bufA = [];
    $bufB = [];
    $bufNA = [];
    $bufNB = [];

    $inicioA = null;
    $inicioB = null;

    $flush = function() use (
        &$bufA,
        &$bufB,
        &$bufNA,
        &$bufNB,
        &$disc,
        &$inicioA,
        &$inicioB
    ) {
        if (empty($bufA) && empty($bufB)) {
            return;
        }

        $esNumero = static function (string $w): bool {
            return cmp_es_numero_norm($w)
                || in_array($w, ['un', 'una', 'uno'], true);
        };

        $hayNumA = false;
        $hayNumB = false;

        foreach ($bufNA as $w) {
            if ($esNumero($w)) {
                $hayNumA = true;
                break;
            }
        }

        foreach ($bufNB as $w) {
            if ($esNumero($w)) {
                $hayNumB = true;
                break;
            }
        }

        if (empty($bufB)) {
            $tipo = 'solo_A';
        } elseif (empty($bufA)) {
            $tipo = 'solo_B';
        } elseif ($hayNumA && $hayNumB) {
            $tipo = 'numero';
        } else {
            $tipo = 'cambio';
        }

        $disc[] = [
            'tipo' => $tipo,
            'a' => implode(' ', $bufA),
            'b' => implode(' ', $bufB),
            'indice_a' => $inicioA,
            'indice_b' => $inicioB,
        ];

        $bufA = [];
        $bufB = [];
        $bufNA = [];
        $bufNB = [];
        $inicioA = null;
        $inicioB = null;
    };

    while ($i < $na && $j < $nb) {
        if ($nA[$i] !== '' && $nA[$i] === $nB[$j]) {
            $flush();
            $i++;
            $j++;
        } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
            if ($inicioA === null) {
                $inicioA = $i;
            }

            $bufA[] = $A[$i];
            $bufNA[] = $nA[$i];
            $i++;
        } else {
            if ($inicioB === null) {
                $inicioB = $j;
            }

            $bufB[] = $B[$j];
            $bufNB[] = $nB[$j];
            $j++;
        }
    }

    while ($i < $na) {
        if ($inicioA === null) {
            $inicioA = $i;
        }

        $bufA[] = $A[$i];
        $bufNA[] = $nA[$i];
        $i++;
    }

    while ($j < $nb) {
        if ($inicioB === null) {
            $inicioB = $j;
        }

        $bufB[] = $B[$j];
        $bufNB[] = $nB[$j];
        $j++;
    }

    $flush();

    return $disc;
}

// ---- Diccionarios ----
$catalogoOrganos = require __DIR__ . '/../../config/organos.php';

$ORGANOS_LISTA = $catalogoOrganos['stt'];
$CONCEPTOS_LISTA = [
    'ecogenicidad','anecoico','anecoica','anecoicas',
    'hipoecoico','hipoecoica','hipoecoicas',
    'hiperecoico','hiperecoica','hiperecoicas',
    'isoecoico','isoecoica','isoecoicos','isoecoicas',
    'parenquima','estratificacion','esplenico','felino','aguzados','engrosado','engrosada','engrosadas',
    'mucoso','corticomedular','vasculatura','homogeneo','lobulo','reactivo','distendida',
    'redondeados','conservado','pelvica','grosor','doppler','peritoneal',
    'peripancreatico','peripancreatica',
    'peripancreaticos','peripancreaticas',
];
function org_norm(string $w): string {
    $w = mb_strtolower($w, 'UTF-8');
    $w = strtr($w, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
    return preg_replace('/[^a-z0-9]/', '', $w);
}
function org_es_organo(string $tok, array $lista): bool { return in_array(org_norm($tok), $lista, true); }
function org_limpia_borde(string $tok): string { return trim($tok, " .,;:"); }

function org_validar(
    string $a,
    string $b,
    array $lista,
    array $conceptos = []
): array {
    $a1 = org_limpia_borde($a);
    $b1 = org_limpia_borde($b);

    if (
        $a1 === ''
        || $b1 === ''
        || strpos($a1, ' ') !== false
        || strpos($b1, ' ') !== false
    ) {
        return ['accion' => 'pasa'];
    }

    $oa = org_es_organo($a1, $lista);
    $ob = org_es_organo($b1, $lista);

    if ($oa === $ob) {
        return ['accion' => 'pasa'];
    }

    $organo = $oa ? $a1 : $b1;
    $otro = $oa ? $b1 : $a1;

    /*
     * Un concepto clínico válido no debe perder
     * automáticamente contra un órgano.
     */
    if (concepto_clinico_protegido($otro, $conceptos)) {
        return ['accion' => 'pasa'];
    }

    $organoNorm = org_norm($organo);
    $otroNorm = org_norm($otro);

    if ($organoNorm === '' || $otroNorm === '') {
        return ['accion' => 'pasa'];
    }

    /*
     * Resolver solo errores STT ortográficamente cercanos.
     * Ej.: Vaso/Bazo, Yeyuno/Yeiuno.
     */
    $umbral = max(
        1,
        (int)floor(strlen($organoNorm) / 3)
    );

    if (levenshtein($organoNorm, $otroNorm) > $umbral) {
        return ['accion' => 'pasa'];
    }

    return [
        'accion' => 'resuelto',
        'elegido' => $organo,
        'descartado' => $otro
    ];
}

function org_validar_inicio_frase(
    string $a,
    string $b,
    array $lista,
    array $conceptos = []
): array {
    $extraer = static function (string $texto): ?array {
        if (!preg_match(
            '/^([^\s,.;:]+)[,.;:]?\s+(.+)$/u',
            trim($texto),
            $m
        )) return null;

        return [
            'inicio' => trim($m[1]),
            'resto' => trim($m[2])
        ];
    };

    $pa = $extraer($a);
    $pb = $extraer($b);

    if ($pa === null || $pb === null) {
        return ['accion' => 'pasa'];
    }

    $aOrgano = org_es_organo($pa['inicio'], $lista);
    $bOrgano = org_es_organo($pb['inicio'], $lista);

    if ($aOrgano === $bOrgano) {
        return ['accion' => 'pasa'];
    }

    $otro = $aOrgano ? $pb['inicio'] : $pa['inicio'];
    $organo = $aOrgano ? $pa['inicio'] : $pb['inicio'];

    if (concepto_clinico_protegido($otro, $conceptos)) {
        return ['accion' => 'pasa'];
    }

    /*
     * El término descartado debe parecer realmente una corrupción
     * del órgano, salvo equivalencias contextuales conocidas.
     */
    $organoNorm = org_norm($organo);
    $otroNorm = org_norm($otro);

    $par = [$organoNorm, $otroNorm];
    sort($par);

    $equivalenciaConocida = $par === ['bazo', 'vaso'];

    $umbralOrgano = max(
        1,
        (int)floor(strlen($organoNorm) / 3)
    );

    if (
        !$equivalenciaConocida
        && levenshtein($organoNorm, $otroNorm) > $umbralOrgano
    ) {
        return ['accion' => 'pasa'];
    }

    $normalizar = static function (string $texto): string {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o',
            'ú'=>'u','ñ'=>'n','ü'=>'u'
        ]);

        return preg_replace('/[^a-z0-9]/', '', $texto) ?? '';
    };

    $restoA = $normalizar($pa['resto']);
    $restoB = $normalizar($pb['resto']);

    if ($restoA === '' || $restoB === '') {
        return ['accion' => 'pasa'];
    }

    $umbralResto = max(
        1,
        (int)floor(max(strlen($restoA), strlen($restoB)) / 5)
    );

    if (levenshtein($restoA, $restoB) > $umbralResto) {
        return ['accion' => 'pasa'];
    }

    return [
        'accion' => 'resuelto',
        'elegido' => $organo,
        'descartado' => $otro
    ];
}

function concepto_es(string $tok, array $lista): bool { return in_array(org_norm($tok), $lista, true); }

function concepto_clinico_protegido(string $tok, array $conceptos): bool
{
    if (concepto_es($tok, $conceptos)) return true;

    static $protegidos = [
        'contenido','forma','pared','borde','bordes','limite','relacion',
        'pelvis','vasculatura','parenquima','capsula','motilidad','tamano',
        'patron','capa','imagen','senal','ecotextura',

        'homogeneo','homogenea','homogeneos','homogeneas',
        'heterogeneo','heterogenea','heterogeneos','heterogeneas',

        'anecoico','anecoica','anecoicos','anecoicas',
        'hipoecoico','hipoecoica','hipoecoicos','hipoecoicas',
        'hiperecoico','hiperecoica','hiperecoicos','hiperecoicas',

        'aumentado','aumentada','aumentados','aumentadas',
        'disminuido','disminuida','disminuidos','disminuidas',
        'conservado','conservada','conservados','conservadas',

        'engrosado','engrosada','engrosados','engrosadas',
        'reactivo','reactiva','reactivos','reactivas',
        'distendido','distendida','distendidos','distendidas',

        'redondeado','redondeada','redondeados','redondeadas',
        'aguzado','aguzada','aguzados','aguzadas',

        'esplenico','esplenica','esplenicos','esplenicas',

        'mucoso','mucosa','mucosos','mucosas',
        'felino','felina','felinos','felinas',
        'lobulo','lobulos',
        'grosor','grosores',
        'peritoneal','peritoneales','peritoneo','peritoneos',

        'ecogenicidad','ecogenicidades',
        'parenquima','parenquimas',
        'estratificacion','estratificaciones',
        'vasculatura','vasculaturas'
    ];

    return in_array(org_norm($tok), $protegidos, true);
}

function concepto_similar(string $valido, string $otro): bool {
    $a = org_norm($valido); $b = org_norm($otro);
    if ($a === '' || $b === '') return false;
    $umbral = max(1, (int)floor(mb_strlen($a) / 3));
    return levenshtein($a, $b) <= $umbral;
}

function concepto_equivalente_contextual(
    string $a,
    string $b,
    ?int $indiceA,
    ?int $indiceB,
    string $textoA,
    string $textoB
): ?array {
    if ($indiceA === null || $indiceB === null) return null;

    $aNorm = org_norm(org_limpia_borde($a));
    $bNorm = org_norm(org_limpia_borde($b));

    $ventana = static function (string $texto, int $indice): string {
        $tokens = cmp_tokens(cmp_pre_limpiar($texto));
        $inicio = max(0, $indice - 6);

        return implode(
            ' ',
            array_slice($tokens, $inicio, 13)
        );
    };

    $contexto = $ventana($textoA, $indiceA)
        . ' '
        . $ventana($textoB, $indiceB);

    $par = [$aNorm, $bNorm];
    sort($par);

    if ($par === ['doble', 'doppler']) {
        if (!preg_match(
            '/\b(?:señal|senal|color|flujo|vascular|vascularizaci[oó]n)\b/iu',
            $contexto
        )) {
            return null;
        }

        return [
            'accion' => 'resuelto',
            'elegido' => $aNorm === 'doppler' ? $a : $b,
            'descartado' => $aNorm === 'doble' ? $a : $b
        ];
    }

    $variantesPelvica = [
        'pellicano',
        'peluca',
        'publica',
    ];

    $esFamiliaPelvica = (
        $aNorm === 'pelvica'
        && in_array($bNorm, $variantesPelvica, true)
    ) || (
        $bNorm === 'pelvica'
        && in_array($aNorm, $variantesPelvica, true)
    );

    if ($esFamiliaPelvica) {
        $tieneImagen = preg_match(
            '/\bimagen\b/iu',
            $contexto
        ) === 1;

        $tieneContextoRenal = preg_match(
            '/\b(?:riñ[oó]n|renal|pelvis|ur[eé]ter)\b/iu',
            $contexto
        ) === 1;

        if (!$tieneImagen || !$tieneContextoRenal) {
            return null;
        }

        return [
            'accion' => 'resuelto',
            'elegido' => $aNorm === 'pelvica' ? $a : $b,
            'descartado' => $aNorm === 'pelvica' ? $b : $a
        ];
    }

    return null;
}

function concepto_frase_equivalente_conocida(string $a, string $b): ?array
{
    $aNorm = org_norm($a);
    $bNorm = org_norm($b);

    /**
     * Equivalencias STT de alta confianza.
     *
     * "canonico" es la forma que se enviará al generador.
     * "variantes" son errores recurrentes conocidos.
     *
     * Importante: permite resolver incluso cuando AMBOS motores
     * entregaron variantes erróneas del mismo concepto.
     */
    $equivalencias = [
        'ureterno' => [
            'canonico'  => 'ureter no',
            'variantes' => ['ureterna'],
        ],
        'patronmucoso' => [
            'canonico' => 'patrón mucoso',
            'variantes' => [
                '4mucosas','4mucosa','4mocos',
                'patromoncose','patomucosa','patromucosa',
            ],
            'variantes_entre_si' => true,
        ],
        'anecoico' => [
            'canonico' => 'anecoico',
            'variantes' => [
                'nicoico',
                'onicoico',
                'anicoico',
            ],
            'variantes_entre_si' => true,
        ],
        'hipoecoicohomogeneo' => [
            'canonico'  => 'hipoecoico homogéneo',
            'variantes' => [
                'hipocoicohomogenea',
            ],
        ],
        'hiperecoica' => [
            'canonico'  => 'hiperecoica',
            'variantes' => [
                'ypericoica',
                'pericoica',
            ],
        ],
        'ecogenicidadlevemente' => [
            'canonico'  => 'ecogenicidad levemente',
            'variantes' => [
                'ecogenesisdeaislamiento',
            ],
        ],
        'patronmucosoestratificacion' => [
            'canonico'  => 'patrón mucoso, estratificación',
            'variantes' => [
                '4mucosasratificacion',
                'patronmucosostratificacion',
            ],
        ],
        'hiperecoico' => [
            'canonico'  => 'hiperecoico',
            'variantes' => [
                'hiperacoico',
                'ypericolico',
            ],
        ],
        'anecoicohomogeneo' => [
            'canonico'  => 'anecoico homogéneo',
            'variantes' => [
                'anecoecogeneo',
                'micoecomogeneo',
            ],
        ],
        'isoecoicoheterogeneo' => [
            'canonico'  => 'isoecoico heterogéneo',
            'variantes' => [
                'isocoicoterigeneo',
            ],
        ],
    ];

    foreach ($equivalencias as $formaNorm => $config) {
        $grupo = array_merge(
            [$formaNorm],
            $config['variantes']
        );

        if (
            !in_array($aNorm, $grupo, true)
            || !in_array($bNorm, $grupo, true)
            || $aNorm === $bNorm
        ) {
            continue;
        }

        $ambosSonVariantes = (
            $aNorm !== $formaNorm
            && $bNorm !== $formaNorm
        );

        if (
            $ambosSonVariantes
            && empty($config['variantes_entre_si'])
        ) {
            continue;
        }

        $descartadas = [];

        if ($aNorm !== $formaNorm) {
            $descartadas[] = $a;
        }

        if ($bNorm !== $formaNorm) {
            $descartadas[] = $b;
        }

        return [
            'accion'     => 'resuelto',
            'elegido'    => $config['canonico'],
            'descartado' => implode(' / ', $descartadas),
        ];
    }

    return null;
}

function concepto_validar(
    string $a,
    string $b,
    array $conceptos,
    array $organos = []
): array {
    $a1 = org_limpia_borde($a);
    $b1 = org_limpia_borde($b);

    if ($a1 === '' || $b1 === '') {
        return ['accion' => 'pasa'];
    }

    /*
     * No resolver automáticamente entre descriptores ecográficos
     * de polaridad opuesta.
     *
     * Un STT puede deformar, por ejemplo:
     *   hiperecoica -> hipercoica / hipericoica
     *   hipoecoica  -> hipocoica
     *
     * Aunque sean ortográficamente similares, hiper/hipo cambian
     * el significado clínico y deben quedar como discrepancia.
     */
    $detectarPolaridadEco = static function (string $valor): ?string {
        $valor = org_norm($valor);

        if (preg_match('/^hiper[ei]?(?:coic|cogen)/', $valor)) {
            return 'hiper';
        }

        if (preg_match('/^hipo[ei]?(?:coic|cogen)/', $valor)) {
            return 'hipo';
        }

        return null;
    };

    $polaridadA = $detectarPolaridadEco($a1);
    $polaridadB = $detectarPolaridadEco($b1);

    if (
        $polaridadA !== null
        && $polaridadB !== null
        && $polaridadA !== $polaridadB
    ) {
        return ['accion' => 'pasa'];
    }

    $frase = concepto_frase_equivalente_conocida($a1, $b1);

    if ($frase !== null) {
        return $frase;
    }

    if (
        strpos($a1, ' ') !== false
        || strpos($b1, ' ') !== false
    ) {
        return ['accion' => 'pasa'];
    }

    /*
     * Un órgano válido nunca debe perder contra un concepto
     * solamente por similitud ortográfica.
     */
    if (
        org_es_organo($a1, $organos)
        || org_es_organo($b1, $organos)
    ) {
        return ['accion' => 'pasa'];
    }

    /*
     * Si ambas formas son términos clínicos válidos,
     * no decidir cuál es "correcta".
     *
     * Ejemplos:
     *   conservado / conservada
     *   homogéneo / homogénea
     */
    if (
        concepto_clinico_protegido($a1, $conceptos)
        && concepto_clinico_protegido($b1, $conceptos)
    ) {
        return ['accion' => 'pasa'];
    }

    /*
    * La familia pélvica requiere contexto renal.
    * No resolver por similitud léxica aislada.
    */
    if (
        org_norm($a1) === 'pelvica'
        || org_norm($b1) === 'pelvica'
    ) {
        return ['accion' => 'pasa'];
    }

    $ca = concepto_es($a1, $conceptos);
    $cb = concepto_es($b1, $conceptos);

    if (
        $ca
        && !$cb
        && concepto_similar($a1, $b1)
    ) {
        return [
            'accion' => 'resuelto',
            'elegido' => $a1,
            'descartado' => $b1
        ];
    }

    if (
        $cb
        && !$ca
        && concepto_similar($b1, $a1)
    ) {
        return [
            'accion' => 'resuelto',
            'elegido' => $b1,
            'descartado' => $a1
        ];
    }

    return ['accion' => 'pasa'];
}

/**
 * Resuelve un error STT recurrente donde un decimal se separa como:
 *
 *   "0 a 37"  <->  "0,37"
 *
 * Solo resuelve si ambas alternativas representan exactamente
 * el mismo decimal. No decide entre números realmente distintos.
 */
function numero_decimal_equivalente(string $a, string $b): ?array
{
    $extraerRoto = static function (string $texto): ?array {
        if (!preg_match(
            '/(?<!\d)0\s+a\s+(\d{1,3})(?!\d)/u',
            $texto,
            $m,
            PREG_OFFSET_CAPTURE
        )) {
            return null;
        }

        return [
            'decimal' => '0.' . $m[1][0],
            'original' => $m[0][0],
            'offset' => (int)$m[0][1]
        ];
    };

    $normalizarFrase = static function (string $texto): string {
        $tokens = cmp_tokens(cmp_pre_limpiar($texto));
        $tokens = array_map('cmp_norm', $tokens);
        $tokens = array_values(array_filter(
            $tokens,
            static fn(string $v): bool => $v !== ''
        ));

        return implode(' ', $tokens);
    };

    $resolver = static function (
        string $textoRoto,
        string $textoOtro,
        array $rotura
    ) use ($normalizarFrase): ?array {
        $textoCorregido = substr_replace(
            $textoRoto,
            $rotura['decimal'],
            $rotura['offset'],
            strlen($rotura['original'])
        );

        /*
         * El decimal solo se considera resuelto si, después de
         * repararlo, TODA la discrepancia resulta equivalente.
         *
         * Así no ocultamos diferencias como:
         * "grosor 0 a 37 aumentado"
         * vs
         * "pared 0,37 disminuida".
         */
        if (
            $normalizarFrase($textoCorregido)
            !== $normalizarFrase($textoOtro)
        ) {
            return null;
        }

        return [
            'accion' => 'resuelto',
            'elegido' => $rotura['decimal'],
            'descartado' => $textoRoto
        ];
    };

    $roturaA = $extraerRoto($a);

    if ($roturaA !== null) {
        $resultado = $resolver($a, $b, $roturaA);

        if ($resultado !== null) {
            return $resultado;
        }
    }

    $roturaB = $extraerRoto($b);

    if ($roturaB !== null) {
        $resultado = $resolver($b, $a, $roturaB);

        if ($resultado !== null) {
            return $resultado;
        }
    }

    return null;
}

/**
 * Detecta correcciones STT de alta confianza que NO aparecen
 * en las discrepancias porque ambos motores cometieron el mismo error.
 *
 * No modifica las transcripciones originales.
 * Solo devuelve correcciones estructuradas para que las capas posteriores
 * puedan reconocer el término descartado como su forma canónica.
 */
function stt_resolver_coincidencias_comunes(
    string $textoA,
    string $textoB
): array {
    $resueltas = [];

    /*
     * Convierte un offset de bytes en el índice de token utilizado
     * por cmp_comparar().
     */
    $indiceTokenDesdeOffset = static function (
        string $texto,
        int $byteOffset
    ): int {
        $prefijo = substr($texto, 0, $byteOffset);
        $prefijo = cmp_pre_limpiar($prefijo);

        return count(cmp_tokens($prefijo));
    };

    /**
     * Vaso -> Bazo
     *
     * Solo aceptamos ocurrencias donde "Vaso" inicia una descripción
     * y dentro del mismo contexto aparece terminología esplénica.
     *
     * IMPORTANTE:
     * devolvemos la posición concreta. No es un alias global.
     */
    $patronVasoBazo = '/'
        . '(?:^|[.!?;]\s+)'
        . '(?<organo>vaso)\b'
        . '[^.!?;\r\n]{0,500}?'
        . '\b(?:'
        . 'espl[eé]nic[oa]s?'
        . '|escl[eé]nic[oa]s?'
        . '|cola\s+espl[eé]nica'
        . '|cabeza\s+espl[eé]nica'
        . '|cola\s+escl[eé]nica'
        . '|cabeza\s+escl[eé]nica'
        . ')\b'
        . '/iu';

    preg_match_all(
        $patronVasoBazo,
        $textoA,
        $coincidenciasA,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    preg_match_all(
        $patronVasoBazo,
        $textoB,
        $coincidenciasB,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    $cantidadA = count($coincidenciasA);
    $cantidadB = count($coincidenciasB);

    if ($cantidadA === 0 || $cantidadA !== $cantidadB) {
        $cantidad = 0;
    } else {
        $cantidad = $cantidadA;
    }

    for ($i = 0; $i < $cantidad; $i++) {
        $offsetA = (int)$coincidenciasA[$i]['organo'][1];
        $offsetB = (int)$coincidenciasB[$i]['organo'][1];

        $resueltas[] = [
            'elegido' => 'Bazo',
            'descartado' => 'Vaso',
            'origen' => 'organo',

            'motor_a' => (string)$coincidenciasA[$i]['organo'][0],
            'motor_b' => (string)$coincidenciasB[$i]['organo'][0],

            'indice_a' => $indiceTokenDesdeOffset(
                $textoA,
                $offsetA
            ),
            'indice_b' => $indiceTokenDesdeOffset(
                $textoB,
                $offsetB
            ),

            'alcance' => 'coincidencia_comun',
        ];
    }

    /*
    * Patrón mucoso: errores repetidos iguales en ambos STT.
    * Solo actuamos cuando ambos motores tienen la misma cantidad
    * de ocurrencias y cada par representa la misma variante.
    */
    $patronMucosoErroneo = '/(?<![\p{L}\d])(?:'
        . '4\s+mucosas?'
        . '|4\s+mocos'
        . '|patromoncose'
        . '|patomucosa'
        . '|patromucosa'
        . ')(?![\p{L}\d])/iu';

    preg_match_all(
        $patronMucosoErroneo,
        $textoA,
        $mucosoA,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    preg_match_all(
        $patronMucosoErroneo,
        $textoB,
        $mucosoB,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    if (
        count($mucosoA) > 0
        && count($mucosoA) === count($mucosoB)
    ) {
        foreach ($mucosoA as $i => $matchA) {
            $valorA = (string)$matchA[0][0];
            $valorB = (string)$mucosoB[$i][0][0];

            if (org_norm($valorA) !== org_norm($valorB)) {
                continue;
            }

            $resueltas[] = [
                'elegido' => 'patrón mucoso',
                'descartado' => $valorA,
                'origen' => 'concepto',
                'motor_a' => $valorA,
                'motor_b' => $valorB,
                'indice_a' => $indiceTokenDesdeOffset(
                    $textoA,
                    (int)$matchA[0][1]
                ),
                'indice_b' => $indiceTokenDesdeOffset(
                    $textoB,
                    (int)$mucosoB[$i][0][1]
                ),
                'alcance' => 'coincidencia_comun'
            ];
        }
    }

    /*
    * Ecogenicidad: errores léxicos repetidos iguales en ambos STT.
    * Solo corregimos variantes inequívocas y cuando ambos motores
    * presentan la misma variante en posiciones correspondientes.
    */
    $patronEcogenicidadErronea = '/(?<![\p{L}\d])(?:'
        . 'cogenicidad'
        . '|coginicidad'
        . '|ecogenisidad'
        . '|ecoginicidad'
        . ')(?![\p{L}\d])/iu';

    preg_match_all(
        $patronEcogenicidadErronea,
        $textoA,
        $ecogenicidadA,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    preg_match_all(
        $patronEcogenicidadErronea,
        $textoB,
        $ecogenicidadB,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    if (
        count($ecogenicidadA) > 0
        && count($ecogenicidadA) === count($ecogenicidadB)
    ) {
        foreach ($ecogenicidadA as $i => $matchA) {
            $valorA = (string)$matchA[0][0];
            $valorB = (string)$ecogenicidadB[$i][0][0];

            if (org_norm($valorA) !== org_norm($valorB)) {
                continue;
            }

            $resueltas[] = [
                'elegido' => 'ecogenicidad',
                'descartado' => $valorA,
                'origen' => 'concepto',
                'motor_a' => $valorA,
                'motor_b' => $valorB,
                'indice_a' => $indiceTokenDesdeOffset(
                    $textoA,
                    (int)$matchA[0][1]
                ),
                'indice_b' => $indiceTokenDesdeOffset(
                    $textoB,
                    (int)$ecogenicidadB[$i][0][1]
                ),
                'alcance' => 'coincidencia_comun'
            ];
        }
    }

    /*
    * Bordes aguzados: corrupciones recurrentes iguales en ambos STT.
    * Nunca corregimos "abusado" globalmente; debe estar unido
    * explícitamente a borde/bordes.
    */
    $patronBordeAguzado = '/(?<![\p{L}\d])(?<frase>'
        . '(?<borde>bordes?)\s+'
        . '(?<descriptor>'
        . 'abusados?'
        . '|desabusados?'
        . '|desgustados?'
        . ')'
        . ')(?![\p{L}\d])/iu';

    preg_match_all(
        $patronBordeAguzado,
        $textoA,
        $bordesA,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    preg_match_all(
        $patronBordeAguzado,
        $textoB,
        $bordesB,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    if (
        count($bordesA) > 0
        && count($bordesA) === count($bordesB)
    ) {
        foreach ($bordesA as $i => $matchA) {
            $fraseA = (string)$matchA['frase'][0];
            $fraseB = (string)$bordesB[$i]['frase'][0];

            if (org_norm($fraseA) !== org_norm($fraseB)) {
                continue;
            }

            $esPlural = org_norm(
                (string)$matchA['borde'][0]
            ) === 'bordes';

            $canonico = $esPlural
                ? 'bordes aguzados'
                : 'borde aguzado';

            $resueltas[] = [
                'elegido' => $canonico,
                'descartado' => $fraseA,
                'origen' => 'concepto',
                'motor_a' => $fraseA,
                'motor_b' => $fraseB,
                'indice_a' => $indiceTokenDesdeOffset(
                    $textoA,
                    (int)$matchA['frase'][1]
                ),
                'indice_b' => $indiceTokenDesdeOffset(
                    $textoB,
                    (int)$bordesB[$i]['frase'][1]
                ),
                'alcance' => 'coincidencia_comun'
            ];
        }
    }

    return $resueltas;
}

function org_procesar(
    array $disc,
    array $organos,
    array $conceptos = [],
    string $textoA = '',
    string $textoB = ''
): array
{
    $resueltas = [];
    $aIA = [];

    foreach ($disc as $d) {
        $a = (string)($d['a'] ?? '');
        $b = (string)($d['b'] ?? '');

        /*
         * Conservamos SIEMPRE la ubicación y las alternativas originales.
         *
         * Una corrección resuelta pertenece a ESTA discrepancia concreta.
         * No debe convertirse después en una sustitución global de palabras
         * iguales que puedan aparecer en otra parte del informe.
         */
        $contextoResolucion = [
            'motor_a' => $a,
            'motor_b' => $b,
            'indice_a' => array_key_exists('indice_a', $d)
                && $d['indice_a'] !== null
                    ? (int)$d['indice_a']
                    : null,
            'indice_b' => array_key_exists('indice_b', $d)
                && $d['indice_b'] !== null
                    ? (int)$d['indice_b']
                    : null,
            'alcance' => 'discrepancia',
        ];

        // Resolver primero equivalencias numéricas inequívocas.
        $n = numero_decimal_equivalente($a, $b);

        if ($n !== null && $n['accion'] === 'resuelto') {
            $resueltas[] = array_merge([
                'elegido' => $n['elegido'],
                'descartado' => $n['descartado'],
                'origen' => 'numero',
            ], $contextoResolucion);

            continue;
        }

        $r = org_validar(
            $a,
            $b,
            $organos,
            $conceptos
        );

        if (($r['accion'] ?? '') === 'resuelto') {
            $resueltas[] = array_merge([
                'elegido' => $r['elegido'],
                'descartado' => $r['descartado'],
                'origen' => 'organo',
            ], $contextoResolucion);

            continue;
        }

        /*
         * Segunda oportunidad para discrepancias que incluyen
         * el órgano junto con parte de su descriptor:
         *
         *   "Vaso aumentado" / "Bazo aumentada"
         *
         * Solo se resuelve cuando el resto de ambas frases
         * es prácticamente equivalente.
         */
        $rFrase = org_validar_inicio_frase(
            $a,
            $b,
            $organos,
            $conceptos
        );

        if (($rFrase['accion'] ?? '') === 'resuelto') {
            $resueltas[] = array_merge([
                'elegido' => $rFrase['elegido'],
                'descartado' => $rFrase['descartado'],
                'origen' => 'organo',
            ], $contextoResolucion);

            continue;
        }

        $contextual = concepto_equivalente_contextual(
            $a,
            $b,
            $contextoResolucion['indice_a'],
            $contextoResolucion['indice_b'],
            $textoA,
            $textoB
        );

        if (($contextual['accion'] ?? '') === 'resuelto') {
            $resueltas[] = array_merge([
                'elegido' => $contextual['elegido'],
                'descartado' => $contextual['descartado'],
                'origen' => 'concepto',
            ], $contextoResolucion);

            continue;
        }

        $c = concepto_validar(
            $a,
            $b,
            $conceptos,
            $organos
        );

        if (($c['accion'] ?? '') === 'resuelto') {
            $resueltas[] = array_merge([
                'elegido' => $c['elegido'],
                'descartado' => $c['descartado'],
                'origen' => 'concepto',
            ], $contextoResolucion);

            continue;
        }

        $aIA[] = $d;
    }

    return [
        'resueltas' => $resueltas,
        'a_ia' => $aIA,
    ];
}

// ---- Armado de bloques para anexar al dictado ----
function construir_bloque_resueltas(array $resueltas): string
{
    if (empty($resueltas)) {
        return '';
    }

    $lineas = [];

    foreach ($resueltas as $r) {
        $elegido = trim((string)($r['elegido'] ?? ''));
        $descartado = trim((string)($r['descartado'] ?? ''));

        if ($elegido === '' || $descartado === '') {
            continue;
        }

        $motorA = trim((string)($r['motor_a'] ?? ''));
        $motorB = trim((string)($r['motor_b'] ?? ''));

        /*
         * Correcciones procedentes de una discrepancia:
         * explicamos exactamente dónde se resolvieron y prohibimos
         * aplicar esa sustitución a otras apariciones.
         */
        if (
            ($r['alcance'] ?? '') === 'discrepancia'
            && ($motorA !== '' || $motorB !== '')
        ) {
            $a = $motorA !== '' ? $motorA : '(nada)';
            $b = $motorB !== '' ? $motorB : '(nada)';

            $lineas[] =
                '- En esta discrepancia concreta: Motor A dice "'
                . $a
                . '" / Motor B dice "'
                . $b
                . '". Resolución: usa "'
                . $elegido
                . '" y descarta "'
                . $descartado
                . '" SOLO en esta aparición.';
            continue;
        }

        if (
            ($r['alcance'] ?? '') === 'coincidencia_comun'
            && ($motorA !== '' || $motorB !== '')
        ) {
            $lineas[] =
                '- En la ocurrencia contextual detectada, ambos motores '
                . 'transcribieron "'
                . $descartado
                . '". Resolución de alta confianza: interpreta SOLO esa '
                . 'aparición como "'
                . $elegido
                . '". No cambies otras apariciones de "'
                . $descartado
                . '" en el dictado.';
            continue;
        }

        /*
         * Compatibilidad con correcciones antiguas o con la segunda pasada.
         * Incluso aquí evitamos decir "usa siempre".
         */
        $lineas[] =
            '- Corrección contextual: usa "'
            . $elegido
            . '" y descarta "'
            . $descartado
            . '" únicamente en la ocurrencia detectada; '
            . 'no reemplaces automáticamente otras apariciones iguales.';
    }

    if (empty($lineas)) {
        return '';
    }

    return "\n\n"
        . "=== CORRECCIONES STT YA RESUELTAS ===\n"
        . "Estas correcciones fueron resueltas mediante reglas de alta confianza "
        . "a partir de las transcripciones del mismo audio.\n"
        . "IMPORTANTE: cada corrección aplica SOLO a la discrepancia u ocurrencia "
        . "donde fue detectada. NO la conviertas en una sustitución global de la "
        . "misma palabra en otras zonas del dictado. No incluyas esta nota en el informe.\n"
        . implode("\n", $lineas);
}

function construir_bloque_discrepancias(array $disc): string {
    if (empty($disc)) return '';
    $lineas = [];
    foreach ($disc as $d) {
        $a = $d['a'] !== '' ? $d['a'] : '(nada)';
        $b = $d['b'] !== '' ? $d['b'] : '(nada)';
        $lineas[] = '- Motor A dice "' . $a . '" / Motor B dice "' . $b . '"';
    }
    return "\n\n=== NOTA: DIFERENCIAS ENTRE 2 TRANSCRIPCIONES DEL MISMO AUDIO (usa el contexto solo si una alternativa es claramente un error de transcripción. Si ambas alternativas son términos clínicamente válidos y cambian el significado del hallazgo, NO elijas silenciosamente: conserva la duda y marca el dato con flag termino_confuso para revisión. No incluyas esta nota en el informe) ===\n"
         . implode("\n", $lineas);
}