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

/**
 * Variantes STT conocidas del concepto "pélvica".
 *
 * IMPORTANTE:
 * pertenecer a esta familia NO basta para corregir una palabra.
 * La normalización solo puede realizarse cuando además existe
 * contexto renal seguro.
 */
function stt_es_variante_pelvica(string $valor): bool
{
    static $variantes = [
        'pelvica',

        // Variantes históricas ya observadas.
        'pellicano',
        'peluca',
        'publica',
        'periferica',
        'preliquea',
        'perita',

        // Variantes observadas en nuevos casos reales.
        'pelilica',
        'pyecta',
        'perihepatica',
        'pepetica',
        'pelica',
    ];

    return in_array(
        org_norm(org_limpia_borde($valor)),
        $variantes,
        true
    );
}

/**
 * Determina si una ocurrencia puede interpretarse con seguridad
 * como el concepto renal "pélvica".
 *
 * La palabra "imagen" es obligatoria.
 * Además debe existir terminología renal cercana.
 *
 * Esto evita sustituciones globales de términos que pueden ser
 * clínicamente válidos en otros contextos, como "perihepática".
 */
function stt_contexto_pelvico_seguro(
    string $texto,
    int $indice
): bool {
    $tokens = cmp_tokens(
        cmp_pre_limpiar($texto)
    );

    /*
     * Conservamos una ventana local deliberadamente acotada.
     * No queremos utilizar información de órganos alejados.
     */
    $inicio = max(0, $indice - 6);

    $contexto = implode(
        ' ',
        array_slice($tokens, $inicio, 13)
    );

    $tieneImagen = preg_match(
        '/\bimagen\b/iu',
        $contexto
    ) === 1;

    if (!$tieneImagen) {
        return false;
    }

    $tieneContextoRenal = preg_match(
        '/\b(?:riñ[oó]n|renal|pelvis|ur[eé]ter)\b/iu',
        $contexto
    ) === 1;

    return $tieneContextoRenal;
}

/**
 * Clasifica variantes STT conocidas de "Yeyuno".
 *
 * Hay tres niveles porque algunas deformaciones son mucho más
 * ambiguas que otras:
 *
 * - fonetica:     deformación claramente compatible con Yeyuno;
 * - ambigua:      secuencias que pueden tener otros significados;
 * - frase_valida: expresiones clínicamente válidas por sí mismas,
 *                 como "en ayuno" o "de ayuno".
 *
 * IMPORTANTE:
 * esta función solo clasifica. NO autoriza una corrección.
 */
function stt_tipo_variante_yeyuno(string $valor): ?string
{
    $normalizado = org_norm(
        org_limpia_borde($valor)
    );

    static $foneticas = [
        'yeyuno',
        'yejuno',
        'yiyuno',
        'geyuno',
        'yeiuno',   // yei-uno
        'digiuno',
    ];

    static $ambiguas = [
        'y1',       // Y 1
        'yei1',     // yei 1
        'jjun',     // JJ un
    ];

    static $frasesValidas = [
        'enayuno',
        'deayuno',
    ];

    if (in_array($normalizado, $foneticas, true)) {
        return 'fonetica';
    }

    if (in_array($normalizado, $ambiguas, true)) {
        return 'ambigua';
    }

    if (in_array($normalizado, $frasesValidas, true)) {
        return 'frase_valida';
    }

    return null;
}


/**
 * Indica si un término pertenece a alguna variante conocida
 * de la familia Yeyuno.
 *
 * Esto NO implica que deba corregirse automáticamente.
 */
function stt_es_variante_yeyuno(string $valor): bool
{
    return stt_tipo_variante_yeyuno($valor) !== null;
}


/**
 * Evalúa si alrededor de una ocurrencia existe contexto
 * gastrointestinal suficiente para interpretar una variante
 * como "Yeyuno".
 *
 * Las variantes ambiguas requieren más evidencia que una
 * deformación fonética.
 *
 * "en ayuno" y "de ayuno" son todavía más restrictivas porque
 * son expresiones clínicas válidas y no deben convertirse
 * accidentalmente en un órgano.
 */
function stt_contexto_yeyuno_seguro(
    string $texto,
    int $indice,
    string $tipoVariante
): bool {
    $tokens = cmp_tokens(
        cmp_pre_limpiar($texto)
    );

    /*
     * Ventana local acotada.
     * No utilizamos todo el dictado para evitar heredar señales
     * gastrointestinales de órganos alejados.
     */
    $inicio = max(0, $indice - 8);

    $contexto = implode(
        ' ',
        array_slice($tokens, $inicio, 17)
    );

    /*
     * Señales altamente características de un bloque intestinal.
     */
    $senalesFuertes = 0;

    if (preg_match(
        '/\bestratificaci[oó]n\b/iu',
        $contexto
    )) {
        $senalesFuertes++;
    }

    if (preg_match(
        '/\bpatr[oó]n\s+(?:mucoso|gaseoso)\b/iu',
        $contexto
    )) {
        $senalesFuertes++;
    }

    if (preg_match(
        '/\bmotilidad\b/iu',
        $contexto
    )) {
        $senalesFuertes++;
    }

    if (preg_match(
        '/\b(?:duodeno|[ií]leon|colon|col[oó]n)\b/iu',
        $contexto
    )) {
        $senalesFuertes++;
    }

    if (preg_match(
        '/\b(?:intestinal|intestino|asas?)\b/iu',
        $contexto
    )) {
        $senalesFuertes++;
    }

    /*
     * Señales compatibles con una descripción intestinal,
     * pero no suficientemente específicas por sí solas.
     */
    $senalesModeradas = 0;

    if (preg_match(
        '/\bpared\b/iu',
        $contexto
    )) {
        $senalesModeradas++;
    }

    if (preg_match(
        '/\bgrosor\b/iu',
        $contexto
    )) {
        $senalesModeradas++;
    }

    /*
     * Una medida cercana ayuda especialmente en variantes fonéticas:
     *
     *   "Geyuno aumentado en 0.62 cm"
     */
    $tieneMedida = preg_match(
        '/\b\d+(?:[.,]\d+)?\s*'
        . '(?:cm|mm|cent[ií]metros?|mil[ií]metros?)\b/iu',
        $contexto
    ) === 1;

    /*
     * Deformación fonética relativamente específica.
     *
     * Una señal GI fuerte es suficiente.
     * También aceptamos una medida asociada, porque el propio término
     * ya aporta una evidencia fonética importante.
     */
    if ($tipoVariante === 'fonetica') {
        return (
            $senalesFuertes >= 1
            || $tieneMedida
        );
    }

    /*
     * Formas como "Y 1", "yei 1" o "JJ un" necesitan
     * evidencia gastrointestinal inequívoca.
     */
    if ($tipoVariante === 'ambigua') {
        return (
            $senalesFuertes >= 2
            || (
                $senalesFuertes >= 1
                && $senalesModeradas >= 1
                && $tieneMedida
            )
        );
    }

    /*
     * "en ayuno" y "de ayuno" son expresiones válidas.
     *
     * Nunca bastan pared, grosor o una medida.
     * Exigimos varias señales GI específicas.
     *
     * Más adelante, si el otro motor dice explícitamente "Yeyuno",
     * podremos aprovechar además esa evidencia A/B.
     */
    if ($tipoVariante === 'frase_valida') {
        return $senalesFuertes >= 2;
    }

    return false;
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

    /*
    * Familia contextual Yeyuno.
    *
    * No usamos aliases globales porque algunas variantes,
    * especialmente "en ayuno" y "de ayuno", son expresiones
    * clínicas válidas fuera de un bloque intestinal.
    */
    $tipoYeyunoA = stt_tipo_variante_yeyuno($a);
    $tipoYeyunoB = stt_tipo_variante_yeyuno($b);

    $esFamiliaYeyuno =
        $tipoYeyunoA !== null
        && $tipoYeyunoB !== null
        && $aNorm !== $bNorm;

    if ($esFamiliaYeyuno) {
        $aEsCanonico = $aNorm === 'yeyuno';
        $bEsCanonico = $bNorm === 'yeyuno';

        /*
        * Caso de máxima confianza:
        * uno de los motores ya entregó explícitamente "Yeyuno"
        * y el otro produjo una deformación fonética.
        *
        * Ejemplos:
        *   Yeyuno / Yejuno
        *   Yeyuno / Geyuno
        *   Yeyuno / digiuno
        *
        * El órgano canónico aportado por un motor es evidencia
        * suficiente siempre que la otra alternativa sea una
        * deformación fonética conocida.
        */
        if (
            $aEsCanonico
            && $tipoYeyunoB === 'fonetica'
        ) {
            return [
                'accion' => 'resuelto',
                'elegido' => $a,
                'descartado' => $b
            ];
        }

        if (
            $bEsCanonico
            && $tipoYeyunoA === 'fonetica'
        ) {
            return [
                'accion' => 'resuelto',
                'elegido' => $b,
                'descartado' => $a
            ];
        }

        /*
        * Para variantes ambiguas o cuando ambos motores están
        * corruptos exigimos contexto GI suficiente en AMBAS
        * transcripciones.
        */
        $contextoYeyunoA = stt_contexto_yeyuno_seguro(
            $textoA,
            $indiceA,
            $tipoYeyunoA
        );

        $contextoYeyunoB = stt_contexto_yeyuno_seguro(
            $textoB,
            $indiceB,
            $tipoYeyunoB
        );

        if (!$contextoYeyunoA || !$contextoYeyunoB) {
            return null;
        }

        /*
        * Si uno de los motores ya entregó la forma canónica,
        * conservar esa forma original.
        */
        if ($aEsCanonico) {
            return [
                'accion' => 'resuelto',
                'elegido' => $a,
                'descartado' => $b
            ];
        }

        if ($bEsCanonico) {
            return [
                'accion' => 'resuelto',
                'elegido' => $b,
                'descartado' => $a
            ];
        }

        /*
        * Ambos motores entregaron variantes no canónicas,
        * pero las dos están respaldadas por contexto GI fuerte.
        */
        return [
            'accion' => 'resuelto',
            'elegido' => 'Yeyuno',
            'descartado' => $a . ' / ' . $b
        ];
    }

    $esFamiliaPelvica =
        stt_es_variante_pelvica($a)
        && stt_es_variante_pelvica($b)
        && $aNorm !== $bNorm;

    if ($esFamiliaPelvica) {
        /*
        * Exigimos contexto renal seguro en AMBAS transcripciones.
        *
        * Esto es deliberadamente conservador:
        * si una de las dos ocurrencias quedó demasiado dañada como
        * para demostrar el contexto renal, la discrepancia continúa
        * pendiente y puede ser revisada posteriormente.
        */
        $contextoPelvicoA = stt_contexto_pelvico_seguro(
            $textoA,
            $indiceA
        );

        $contextoPelvicoB = stt_contexto_pelvico_seguro(
            $textoB,
            $indiceB
        );

        if (!$contextoPelvicoA || !$contextoPelvicoB) {
            return null;
        }

        /*
        * Si uno de los motores ya entregó "pélvica",
        * conservamos esa forma original.
        *
        * Si ambos motores deformaron el concepto,
        * el contexto permite reconstruir de forma
        * inequívoca el concepto clínico "pélvica".
        */
        if ($aNorm === 'pelvica') {
            return [
                'accion' => 'resuelto',
                'elegido' => $a,
                'descartado' => $b
            ];
        }

        if ($bNorm === 'pelvica') {
            return [
                'accion' => 'resuelto',
                'elegido' => $b,
                'descartado' => $a
            ];
        }

        return [
            'accion' => 'resuelto',
            'elegido' => 'pélvica',
            'descartado' => $a . ' / ' . $b
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
function numero_decimal_equivalente(
    string $a,
    string $b,
    ?int $indiceA,
    ?int $indiceB,
    string $textoA,
    string $textoB,
    array $organos = []
): ?array {
    if ($indiceA === null || $indiceB === null) {
        return null;
    }

    /*
     * Convierte únicamente representaciones numéricas
     * inequívocas a una forma canónica.
     *
     * NO usa tolerancia matemática.
     *
     * Ejemplos:
     *   0 coma un  -> 0.1
     *   0 coma uno -> 0.1
     *   0 a 37     -> 0.37
     *   un          -> 1
     *   1           -> 1
     */
    $normalizarValor = static function (string $valor): ?string {
        $valor = mb_strtolower(
            trim($valor, " \t\n\r\0\x0B.,;:"),
            'UTF-8'
        );

        $valor = strtr($valor, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ]);

        $valor = preg_replace(
            '/\s+/u',
            ' ',
            $valor
        ) ?? $valor;

        /*
         * Forma numérica literal.
         *
         * La coma decimal y el punto decimal son equivalentes.
         */
        if (preg_match(
            '/^\d+(?:[.,]\d+)?$/u',
            $valor
        )) {
            return str_replace(',', '.', $valor);
        }

        /*
         * Cantidad verbal simple.
         *
         * Esta conversión solo podrá aceptarse más abajo
         * si existe una unidad de medida compatible.
         */
        if (in_array(
            $valor,
            ['un', 'una', 'uno'],
            true
        )) {
            return '1';
        }

        $digitos = [
            'cero' => '0',
            'un' => '1',
            'una' => '1',
            'uno' => '1',
            'dos' => '2',
            'tres' => '3',
            'cuatro' => '4',
            'cinco' => '5',
            'seis' => '6',
            'siete' => '7',
            'ocho' => '8',
            'nueve' => '9',
        ];

        /*
         * Decimal hablado simple:
         *
         *   0 coma un
         *   0 coma uno
         *   cero coma uno
         *   0 coma 1
         *
         * Solo admitimos UNA cifra decimal hablada.
         */
        if (preg_match(
            '/^(?:0|cero)\s+coma\s+'
            . '(cero|un|una|uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|\d)$/u',
            $valor,
            $m
        )) {
            $decimal = $digitos[$m[1]] ?? $m[1];

            return '0.' . $decimal;
        }

        /*
         * Error STT histórico ya soportado:
         *
         *   0 a 37 -> 0.37
         */
        if (preg_match(
            '/^(?:0|cero)\s+a\s+(\d{1,3})$/u',
            $valor,
            $m
        )) {
            return '0.' . $m[1];
        }

        return null;
    };

    /*
     * Normalizar unidad únicamente cuando está asociada
     * inmediatamente a ESTA discrepancia.
     *
     * La unidad puede haber quedado:
     * - dentro de la propia discrepancia; o
     * - justo después de ella porque ambos motores coincidieron
     *   en "centímetros", "cm", etc.
     */
    $extraerUnidad = static function (
        string $texto,
        int $indice,
        string $discrepancia
    ): ?string {
        $tokens = cmp_tokens(
            cmp_pre_limpiar($texto)
        );

        if (!isset($tokens[$indice])) {
            return null;
        }

        $cantidadDiscrepancia = count(
            cmp_tokens(
                cmp_pre_limpiar($discrepancia)
            )
        );

        $fin = min(
            count($tokens) - 1,
            $indice + max(1, $cantidadDiscrepancia) + 1
        );

        for ($i = $indice; $i <= $fin; $i++) {
            $token = mb_strtolower(
                trim(
                    (string)$tokens[$i],
                    " \t\n\r\0\x0B.,;:"
                ),
                'UTF-8'
            );

            $token = strtr($token, [
                'á' => 'a',
                'é' => 'e',
                'í' => 'i',
                'ó' => 'o',
                'ú' => 'u',
                'ü' => 'u',
            ]);

            if (preg_match(
                '/^(?:cm|centimetro|centimetros)$/u',
                $token
            )) {
                return 'cm';
            }

            if (preg_match(
                '/^(?:mm|milimetro|milimetros)$/u',
                $token
            )) {
                return 'mm';
            }
        }

        return null;
    };

    /*
     * Recuperar el último órgano explícito anterior a la medida.
     *
     * Esto evita fusionar números pertenecientes claramente
     * a órganos distintos aunque casualmente tengan el mismo valor.
     */
    $extraerOrganoPrevio = static function (
        string $texto,
        int $indice
    ) use ($organos): ?string {
        if (empty($organos)) {
            return null;
        }

        $tokens = cmp_tokens(
            cmp_pre_limpiar($texto)
        );

        $inicio = max(0, $indice - 35);

        for ($i = $indice - 1; $i >= $inicio; $i--) {
            $token = org_norm(
                org_limpia_borde(
                    (string)($tokens[$i] ?? '')
                )
            );

            if (
                $token !== ''
                && in_array($token, $organos, true)
            ) {
                return $token;
            }
        }

        return null;
    };

    /*
     * Ancla descriptiva inmediatamente anterior.
     *
     * No pretende interpretar el hallazgo: solamente evita declarar
     * equivalencia cuando las medidas están claramente insertas en
     * contextos diferentes.
     */
    $extraerAnclaPrevia = static function (
        string $texto,
        int $indice
    ): ?string {
        $tokens = cmp_tokens(
            cmp_pre_limpiar($texto)
        );

        $ignorar = [
            'de', 'del', 'la', 'el', 'los', 'las',
            'en', 'con', 'a', 'al', 'y',
            'un', 'una', 'uno',
        ];

        $inicio = max(0, $indice - 6);

        for ($i = $indice - 1; $i >= $inicio; $i--) {
            $token = cmp_norm(
                (string)($tokens[$i] ?? '')
            );

            if (
                $token === ''
                || cmp_es_numero_norm($token)
                || in_array($token, $ignorar, true)
            ) {
                continue;
            }

            return $token;
        }

        return null;
    };

    $valorA = $normalizarValor($a);
    $valorB = $normalizarValor($b);

    /*
     * Si alguna alternativa no puede interpretarse de forma
     * determinista, no intentamos corregirla.
     */
    if ($valorA === null || $valorB === null) {
        return null;
    }

    /*
     * Igualdad EXACTA.
     *
     * 0.16 !== 0.17
     * 51   !== 0.51
     */
    if ($valorA !== $valorB) {
        return null;
    }

    $unidadA = $extraerUnidad(
        $textoA,
        $indiceA,
        $a
    );

    $unidadB = $extraerUnidad(
        $textoB,
        $indiceB,
        $b
    );

    /*
     * No normalizamos "un/uno" ni decimales hablados sin una
     * medida explícita compatible en ambos motores.
     */
    if (
        $unidadA === null
        || $unidadB === null
        || $unidadA !== $unidadB
    ) {
        return null;
    }

    $organoA = $extraerOrganoPrevio(
        $textoA,
        $indiceA
    );

    $organoB = $extraerOrganoPrevio(
        $textoB,
        $indiceB
    );

    /*
     * Si ambos motores permiten identificar órgano y son distintos,
     * las medidas NO son equivalentes contextual mente.
     */
    if (
        $organoA !== null
        && $organoB !== null
        && $organoA !== $organoB
    ) {
        return null;
    }

    /*
     * Si solo uno de los motores permite identificar órgano,
     * preferimos no fusionar automáticamente.
     */
    if (
        ($organoA === null) !== ($organoB === null)
    ) {
        return null;
    }

    $anclaA = $extraerAnclaPrevia(
        $textoA,
        $indiceA
    );

    $anclaB = $extraerAnclaPrevia(
        $textoB,
        $indiceB
    );

    /*
     * Cuando ambos tienen una referencia descriptiva inmediata,
     * también debe ser compatible.
     */
    if (
        $anclaA !== null
        && $anclaB !== null
        && $anclaA !== $anclaB
    ) {
        return null;
    }

    /*
     * Preferimos como descartada la forma que no coincide con
     * la representación canónica. Si ambas difieren visualmente,
     * conservamos ambas como evidencia.
     */
    $aLiteral = str_replace(
        ',',
        '.',
        trim($a, " \t\n\r\0\x0B.,;:")
    );

    $bLiteral = str_replace(
        ',',
        '.',
        trim($b, " \t\n\r\0\x0B.,;:")
    );

    if ($aLiteral === $valorA && $bLiteral !== $valorB) {
        $descartado = $b;
    } elseif ($bLiteral === $valorB && $aLiteral !== $valorA) {
        $descartado = $a;
    } else {
        $descartado = $a . ' / ' . $b;
    }

    return [
        'accion' => 'resuelto',
        'elegido' => $valorA,
        'descartado' => $descartado
    ];
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

    /*
    * Familia "imagen pélvica":
    * ambos motores pueden cometer exactamente el mismo error,
    * por lo que la ocurrencia no aparece como discrepancia A/B.
    *
    * Ejemplos:
    *   pyecta / pyecta
    *   pelica / pelica
    *   periférica / periférica
    *
    * Solo se resuelve si:
    * - ambos motores presentan la misma variante;
    * - la variante pertenece a la familia pélvica conocida;
    * - ambas ocurrencias tienen contexto renal seguro.
    *
    * Nunca es una sustitución global.
    */
    $extraerPelvicasContextuales = static function (
        string $texto
    ): array {
        $tokens = cmp_tokens(
            cmp_pre_limpiar($texto)
        );

        $resultado = [];

        foreach ($tokens as $indice => $token) {
            if (!stt_es_variante_pelvica($token)) {
                continue;
            }

            /*
            * "pélvica" ya correcta no necesita autocorrección.
            */
            if (
                org_norm(org_limpia_borde($token))
                === 'pelvica'
            ) {
                continue;
            }

            if (!stt_contexto_pelvico_seguro(
                $texto,
                (int)$indice
            )) {
                continue;
            }

            $resultado[] = [
                'valor' => $token,
                'indice' => (int)$indice,
            ];
        }

        return $resultado;
    };

    $pelvicasA = $extraerPelvicasContextuales($textoA);
    $pelvicasB = $extraerPelvicasContextuales($textoB);

    /*
    * Solo emparejamos automáticamente cuando ambos motores
    * detectaron la misma cantidad de ocurrencias contextuales.
    *
    * Si las cantidades no coinciden, no intentamos adivinar
    * qué ocurrencia corresponde con cuál.
    */
    if (
        count($pelvicasA) > 0
        && count($pelvicasA) === count($pelvicasB)
    ) {
        foreach ($pelvicasA as $i => $pelvicaA) {
            $pelvicaB = $pelvicasB[$i];

            $valorA = (string)$pelvicaA['valor'];
            $valorB = (string)$pelvicaB['valor'];

            /*
            * Este bloque es únicamente para errores iguales
            * que no llegaron a generar discrepancia.
            *
            * Si A y B son variantes diferentes, esa situación
            * corresponde a concepto_equivalente_contextual().
            */
            if (
                org_norm(org_limpia_borde($valorA))
                !== org_norm(org_limpia_borde($valorB))
            ) {
                continue;
            }

            $resueltas[] = [
                'elegido' => 'pélvica',
                'descartado' => $valorA,
                'origen' => 'concepto',

                'motor_a' => $valorA,
                'motor_b' => $valorB,

                'indice_a' => (int)$pelvicaA['indice'],
                'indice_b' => (int)$pelvicaB['indice'],

                'alcance' => 'coincidencia_comun',
            ];
        }
    }

    /**
     * Familia Yeyuno:
     * ambos motores pueden cometer exactamente el mismo error,
     * por lo que no aparecerá una discrepancia A/B.
     *
     * Ejemplos:
     *   Geyuno / Geyuno
     *   Y 1 / Y 1
     *   yei 1 / yei 1
     *   JJ un / JJ un
     *
     * También reconocemos "en ayuno" / "de ayuno", pero solo
     * podrán corregirse si superan el contexto GI fuerte definido
     * en stt_contexto_yeyuno_seguro().
     *
     * Nunca se realiza una sustitución global.
     */
    $extraerYeyunosContextuales = static function (
        string $texto
    ) use ($indiceTokenDesdeOffset): array {
        $resultado = [];

        /*
        * Primero capturamos las variantes completas.
        *
        * Las formas de varias palabras deben analizarse como una unidad;
        * no sirve recorrer token por token para "Y 1", "JJ un", etc.
        */
        $patronYeyuno = '/(?<![\p{L}\d])(?:'
            . 'yejuno'
            . '|yiyuno'
            . '|geyuno'
            . '|digiuno'
            . '|yei[-\s]+uno'
            . '|y\s+1'
            . '|yei\s+1'
            . '|jj\s+un'
            . '|en\s+ayuno'
            . '|de\s+ayuno'
            . ')(?![\p{L}\d])/iu';

        preg_match_all(
            $patronYeyuno,
            $texto,
            $coincidencias,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($coincidencias as $match) {
            $valor = (string)$match[0][0];
            $offset = (int)$match[0][1];

            $tipo = stt_tipo_variante_yeyuno($valor);

            if ($tipo === null) {
                continue;
            }

            $indice = $indiceTokenDesdeOffset(
                $texto,
                $offset
            );

            /*
            * La corrección solo existe si ESTA ocurrencia concreta
            * dispone de contexto gastrointestinal suficiente.
            */
            if (!stt_contexto_yeyuno_seguro(
                $texto,
                $indice,
                $tipo
            )) {
                continue;
            }

            $resultado[] = [
                'valor' => $valor,
                'tipo' => $tipo,
                'indice' => $indice,
            ];
        }

        return $resultado;
    };

    $yeyunosA = $extraerYeyunosContextuales($textoA);
    $yeyunosB = $extraerYeyunosContextuales($textoB);

    /*
    * Para coincidencias comunes exigimos que ambos motores tengan
    * la misma cantidad de ocurrencias contextuales.
    *
    * Si no, no intentamos emparejar por aproximación.
    */
    if (
        count($yeyunosA) > 0
        && count($yeyunosA) === count($yeyunosB)
    ) {
        foreach ($yeyunosA as $i => $yeyunoA) {
            $yeyunoB = $yeyunosB[$i];

            $valorA = (string)$yeyunoA['valor'];
            $valorB = (string)$yeyunoB['valor'];

            /*
            * Este bloque es únicamente para el MISMO error en ambos STT.
            *
            * Variantes distintas:
            *   Yiyuno / Yejuno
            *
            * ya corresponden a concepto_equivalente_contextual().
            */
            if (org_norm($valorA) !== org_norm($valorB)) {
                continue;
            }

            $resueltas[] = [
                'elegido' => 'Yeyuno',
                'descartado' => $valorA,
                'origen' => 'organo',

                'motor_a' => $valorA,
                'motor_b' => $valorB,

                'indice_a' => (int)$yeyunoA['indice'],
                'indice_b' => (int)$yeyunoB['indice'],

                'alcance' => 'coincidencia_comun',
            ];
        }
    }

    /**
     * Vaso / Vasos -> Bazo
     *
     * Esta corrección aplica SOLO cuando ambos motores cometieron
     * el mismo error y existe contexto esplénico suficientemente fuerte.
     *
     * "vaso" es un término clínico válido, por lo que nunca se trata
     * como alias global de Bazo.
     */
    $extraerVasoBazoContextual = static function (
        string $texto
    ): array {
        $resultado = [];

        /*
        * Solo consideramos "vaso/vasos" cuando inicia un nuevo
        * segmento clínico. El fragmento termina al cambiar de frase.
        */
        $patron = '/'
            . '(?:^|[.!?;]\s+)'
            . '(?<organo>vasos?)\b'
            . '(?<fragmento>[^.!?;\r\n]{0,500})'
            . '/iu';

        preg_match_all(
            $patron,
            $texto,
            $coincidencias,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($coincidencias as $match) {
            $organo = (string)$match['organo'][0];
            $fragmento = (string)$match['fragmento'][0];

            /*
            * Protección vascular:
            * expresiones compatibles con un vaso real NO se corrigen.
            */
            if (preg_match(
                '/\b(?:'
                . 'sangu[ií]ne[oa]s?'
                . '|vascular(?:es)?'
                . '|arteria(?:s)?'
                . '|vena(?:s)?'
                . '|flujo'
                . '|doppler'
                . ')\b/iu',
                $fragmento
            )) {
                continue;
            }

            /*
            * Anclajes esplénicos explícitos.
            * Uno de estos es suficiente porque identifica directamente
            * la anatomía esplénica.
            */
            $tieneAnclajeEsplenico = preg_match(
                '/\b(?:'
                . 'hilio\s+espl[eé]nic[oa]'
                . '|c[aá]psula\s+espl[eé]nic[oa]'
                . '|espl[eé]nic[oa]s?'
                . '|hilio\s+escl[eé]nic[oa]'
                . '|c[aá]psula\s+escl[eé]nic[oa]'
                . '|escl[eé]nic[oa]s?'
                . ')\b/iu',
                $fragmento
            ) === 1;

            /*
            * Si no existe una palabra esplénica explícita, aceptamos
            * únicamente una combinación fuerte de atributos típicos
            * de una descripción orgánica esplénica.
            */
            $senales = 0;

            if (preg_match(
                '/\b(?:tamañ[oa]|tamano|aumentad[oa]|conservad[oa])\b/iu',
                $fragmento
            )) {
                $senales++;
            }

            if (preg_match(
                '/\bbordes?\s+(?:aguzad[oa]s?|redondead[oa]s?|regular(?:es)?)\b/iu',
                $fragmento
            )) {
                $senales++;
            }

            if (preg_match(
                '/\bpar[eé]nquima\b/iu',
                $fragmento
            )) {
                $senales++;
            }

            if (preg_match(
                '/\b(?:'
                . 'ecogenicidad'
                . '|ecotextura'
                . '|hipoecoic[oa]'
                . '|hiperecoic[oa]'
                . '|homog[eé]ne[oa]'
                . '|heterog[eé]ne[oa]'
                . ')\b/iu',
                $fragmento
            )) {
                $senales++;
            }

            if (preg_match(
                '/\bc[aá]psula\b/iu',
                $fragmento
            )) {
                $senales++;
            }

            if (
                !$tieneAnclajeEsplenico
                && $senales < 3
            ) {
                continue;
            }

            $resultado[] = [
                'valor' => $organo,
                'offset' => (int)$match['organo'][1],
            ];
        }

        return $resultado;
    };

    $vasoBazoA = $extraerVasoBazoContextual($textoA);
    $vasoBazoB = $extraerVasoBazoContextual($textoB);

    /*
    * Solo emparejamos automáticamente cuando ambos motores
    * presentan la misma cantidad de bloques compatibles.
    */
    if (
        count($vasoBazoA) > 0
        && count($vasoBazoA) === count($vasoBazoB)
    ) {
        foreach ($vasoBazoA as $i => $matchA) {
            $matchB = $vasoBazoB[$i];

            $valorA = (string)$matchA['valor'];
            $valorB = (string)$matchB['valor'];

            /*
            * Este bloque cubre coincidencias del mismo error.
            * Las discrepancias vaso/bazo siguen resolviéndose
            * por org_validar()/org_validar_inicio_frase().
            */
            if (
                org_norm($valorA)
                !== org_norm($valorB)
            ) {
                continue;
            }

            $resueltas[] = [
                'elegido' => 'Bazo',
                'descartado' => $valorA,
                'origen' => 'organo',

                'motor_a' => $valorA,
                'motor_b' => $valorB,

                'indice_a' => $indiceTokenDesdeOffset(
                    $textoA,
                    (int)$matchA['offset']
                ),
                'indice_b' => $indiceTokenDesdeOffset(
                    $textoB,
                    (int)$matchB['offset']
                ),

                'alcance' => 'coincidencia_comun',
            ];
        }
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
        $n = numero_decimal_equivalente(
            $a,
            $b,
            $contextoResolucion['indice_a'],
            $contextoResolucion['indice_b'],
            $textoA,
            $textoB,
            $organos
        );

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