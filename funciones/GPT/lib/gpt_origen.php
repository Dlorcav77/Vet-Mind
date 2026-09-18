<?php
// funciones/GPT/lib/gpt_origen.php

declare(strict_types=1);

function gpt_origen_limpiar_dictado(string $texto): string
{
    $cortes = [
        '=== CORRECCIONES YA RESUELTAS',
        '=== NOTA: DIFERENCIAS ENTRE 2 TRANSCRIPCIONES'
    ];

    $posCorte = null;

    foreach ($cortes as $marca) {
        $pos = mb_strpos($texto, $marca);

        if ($pos !== false && ($posCorte === null || $pos < $posCorte)) {
            $posCorte = $pos;
        }
    }

    if ($posCorte !== null) {
        $texto = mb_substr($texto, 0, $posCorte);
    }

    return trim($texto);
}

function gpt_origen_equivalencias_resueltas(string $texto): array
{
    $inicio = mb_strpos($texto, '=== CORRECCIONES YA RESUELTAS');

    if ($inicio === false) {
        return [];
    }

    $fin = mb_strpos($texto, '=== NOTA: DIFERENCIAS ENTRE 2 TRANSCRIPCIONES', $inicio);
    $bloque = $fin !== false
        ? mb_substr($texto, $inicio, $fin - $inicio)
        : mb_substr($texto, $inicio);

    if (!preg_match_all(
        '/-\s*Usa\s+"([^"]+)"\s*\([^)]*como\s+"([^"]+)"[^)]*\)/iu',
        $bloque,
        $matches,
        PREG_SET_ORDER
    )) {
        return [];
    }

    $salida = [];

    foreach ($matches as $match) {
        $correcto = trim($match[1] ?? '');
        $incorrecto = trim($match[2] ?? '');

        if ($correcto === '' || $incorrecto === '') {
            continue;
        }

        $salida[] = [
            'correcto' => $correcto,
            'incorrecto' => $incorrecto,
            'correcto_norm' => gpt_origen_normalizar($correcto),
            'incorrecto_norm' => gpt_origen_normalizar($incorrecto)
        ];
    }

    return $salida;
}

function gpt_origen_diferencias_motores(string $texto): array
{
    $inicio = mb_strpos($texto, '=== NOTA: DIFERENCIAS ENTRE 2 TRANSCRIPCIONES');

    if ($inicio === false) {
        return [];
    }

    $bloque = mb_substr($texto, $inicio);

    if (!preg_match_all(
        '/-\s*Motor A dice "([^"]+)"\s*\/\s*Motor B dice "([^"]+)"/iu',
        $bloque,
        $matches,
        PREG_SET_ORDER
    )) {
        return [];
    }

    $salida = [];

    foreach ($matches as $match) {
        $a = trim($match[1] ?? '');
        $b = trim($match[2] ?? '');

        if ($a === '' || $b === '') {
            continue;
        }

        $salida[] = [
            'a' => $a,
            'b' => $b,
            'a_norm' => gpt_origen_normalizar($a),
            'b_norm' => gpt_origen_normalizar($b)
        ];
    }

    return $salida;
}

function gpt_origen_normalizar(string $texto): string
{
    $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n'
    ]);

    $texto = str_replace('×', 'x', $texto);
    $texto = preg_replace('/(?<=\d),(?=\d)/u', '.', $texto);
    $texto = preg_replace('/\b(\d+(?:\.\d+)?)\s*(?:por|x)\s*(\d+(?:\.\d+)?)\b/u', '$1x$2', $texto);
    $texto = preg_replace('/\bcentimetros?\b/u', 'cm', $texto);
    $texto = str_replace('corticomedular', 'cortico medular', $texto);
    $texto = preg_replace('/\s+/u', ' ', $texto);

    return trim((string)$texto);
}

function gpt_origen_extraer_parrafos(string $html): array
{
    if (trim($html) === '') {
        return [];
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);

    $dom->loadHTML(
        '<?xml encoding="UTF-8"><div id="vm-origen-root">'.$html.'</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $xpath = new DOMXPath($dom);
    $nodos = $xpath->query('//*[@id="vm-origen-root"]//p');

    if (!$nodos) {
        return [];
    }

    $salida = [];

    foreach ($nodos as $p) {
        if (!$p instanceof DOMElement) {
            continue;
        }

        foreach ($xpath->query('.//sup[contains(concat(" ", normalize-space(@class), " "), " flag ")]', $p) ?: [] as $sup) {
            $sup->parentNode?->removeChild($sup);
        }

        $texto = trim((string)$p->textContent);
        if ($texto === '') {
            continue;
        }

        if (str_starts_with(gpt_origen_normalizar($texto), 'observaciones del asistente')) {
            continue;
        }

        $etiqueta = '';

        foreach (['strong', 'em'] as $tag) {
            $labels = $p->getElementsByTagName($tag);
            if ($labels->length > 0) {
                $posible = trim((string)$labels->item(0)?->textContent);

                if ($posible !== '' && str_starts_with(
                    gpt_origen_normalizar($texto),
                    gpt_origen_normalizar($posible)
                )) {
                    $etiqueta = rtrim($posible, ": \t\n\r\0\x0B");
                    break;
                }
            }
        }

        $salida[] = [
            'texto' => $texto,
            'etiqueta' => $etiqueta
        ];
    }

    return $salida;
}

function gpt_origen_detectar_organos_informe(string $html): array
{
    $parrafos = gpt_origen_extraer_parrafos($html);
    $organos = [];

    foreach ($parrafos as $p) {
        $texto = $p['texto'];
        $organo = trim((string)$p['etiqueta']);

        if ($organo === '') {
            if (preg_match('/^(AI|AD)\b/u', $texto, $m)) {
                $organo = $m[1];
            } elseif (preg_match('/^(Motilidad)\b/iu', $texto, $m)) {
                $organo = $m[1];
            } elseif (preg_match('/^(Estómago|Duodeno|Yeyuno|Colon)\b/iu', $texto, $m)) {
                $organo = $m[1];
            }
        }

        if ($organo === '') {
            continue;
        }

        $norm = gpt_origen_normalizar($organo);

        if (in_array($norm, [
            'gastro entero',
            'glandulas adrenales',
            'impresion diagnostica',
            'sugerencias'
        ], true)) {
            continue;
        }

        $organos[] = [
            'organo' => $organo,
            'texto' => $texto
        ];
    }

    return $organos;
}

function gpt_origen_buscar_plantilla_por_organo(string $plantilla, array $organosInforme): array
{
    $parrafos = gpt_origen_extraer_parrafos($plantilla);
    $salida = [];

    foreach ($organosInforme as $organo) {
        $nombre = $organo['organo'];
        $nombreNorm = gpt_origen_normalizar($nombre);

        foreach ($parrafos as $p) {
            $textoNorm = gpt_origen_normalizar($p['texto']);
            $etiquetaNorm = gpt_origen_normalizar((string)$p['etiqueta']);

            if (
                ($etiquetaNorm !== '' && $etiquetaNorm === $nombreNorm)
                || preg_match('/^'.preg_quote($nombreNorm, '/').'\b/u', $textoNorm)
            ) {
                $salida[$nombreNorm] = $p['texto'];
                break;
            }
        }
    }

    return $salida;
}

function gpt_origen_aliases_organo(string $organo): array
{
    $norm = gpt_origen_normalizar($organo);

    switch ($norm) {
        case 'vejiga urinaria':
            return ['vejiga urinaria', 'vejiga'];

        case 'ai':
            return [
                'adrenal izquierda',
                'glandula adrenal izquierda',
                'adrenalina izquierda',
                'arenal izquierda'
            ];

        case 'ad':
            return [
                'adrenal derecha',
                'glandula adrenal derecha',
                'adrenalina derecha',
                'arenal derecha'
            ];

        case 'motilidad':
            return ['motilidad', 'motilidad intestinal', 'motilidad conservada'];

        default:
            return [$norm];
    }
}

function gpt_origen_contextos_dictado(string $dictado, array $organosInforme): array
{
    $equivalencias = gpt_origen_equivalencias_resueltas($dictado);
    $diferenciasMotores = gpt_origen_diferencias_motores($dictado);
    $dictado = gpt_origen_normalizar(gpt_origen_limpiar_dictado($dictado));
    $menciones = [];

    foreach ($organosInforme as $indice => $organo) {
        $aliases = gpt_origen_aliases_organo($organo['organo']);
        $aliasesNorm = array_map('gpt_origen_normalizar', $aliases);
        $nombreNorm = gpt_origen_normalizar($organo['organo']);

        foreach ($equivalencias as $equivalencia) {
            $correcto = $equivalencia['correcto_norm'] ?? '';
            $incorrecto = $equivalencia['incorrecto_norm'] ?? '';

            if (
                $incorrecto !== ''
                && (
                    $correcto === $nombreNorm
                    || in_array($correcto, $aliasesNorm, true)
                )
            ) {
                $aliases[] = $incorrecto;
            }
        }

        $aliases = array_values(array_unique($aliases));

        foreach ($aliases as $alias) {
            $offset = 0;
            $largo = mb_strlen($alias);

            while (($pos = mb_strpos($dictado, $alias, $offset)) !== false) {
                $antes = $pos > 0 ? mb_substr($dictado, $pos - 1, 1) : '';
                $despues = mb_substr($dictado, $pos + $largo, 1);

                $limiteAntes = $antes === '' || preg_match('/[^\p{L}\p{N}]/u', $antes);
                $limiteDespues = $despues === '' || preg_match('/[^\p{L}\p{N}]/u', $despues);

                if ($limiteAntes && $limiteDespues) {
                    $menciones[] = [
                        'indice' => $indice,
                        'pos' => $pos,
                        'largo' => $largo
                    ];
                }

                $offset = $pos + max(1, $largo);
            }
        }
    }

    $indicesPorNombre = [];
    foreach ($organosInforme as $indice => $organo) {
        $indicesPorNombre[gpt_origen_normalizar($organo['organo'])] = $indice;
    }

    /*
    * Recuperación conservadora de órganos cuyo nombre fue destruido por STT.
    * Solo se activa cuando el órgano aún no tiene una mención normal.
    */
    foreach ($organosInforme as $indice => $organo) {
        $nombre = gpt_origen_normalizar($organo['organo']);

        $yaTieneMencion = false;
        foreach ($menciones as $mencion) {
            if ($mencion['indice'] === $indice) {
                $yaTieneMencion = true;
                break;
            }
        }

        if ($yaTieneMencion) {
            continue;
        }

        // Riñón izquierdo/derecho con nombre deformado:
        // "Vellón izquierdo...", "Peñón derecho...", etc.
        if (preg_match('/^rinon\s+(izquierdo|derecho)$/u', $nombre, $ladoMatch)) {
            $lado = $ladoMatch[1];

            $patronRenal =
                '/\b(\p{L}+)\s+'.preg_quote($lado, '/').'\b'
                .'(?=.{0,400}\b(?:imagen\s+pelvica|pelvis\s+conservada|anill[oa]\s+medular|'
                .'cortico\s+medular|ureter)\b)/u';

            if (preg_match($patronRenal, $dictado, $match, PREG_OFFSET_CAPTURE)) {
                $offsetBytes = (int)($match[0][1] ?? 0);
                $pos = mb_strlen(substr($dictado, 0, $offsetBytes), 'UTF-8');

                $menciones[] = [
                    'indice' => $indice,
                    'pos' => $pos,
                    'largo' => mb_strlen((string)$match[0][0])
                ];

                continue;
            }
        }

        /*
        * Bazo:
        * no convertimos "vaso" en alias global.
        * Solo lo aceptamos si el mismo bloque contiene una referencia
        * anatómica esplénica inequívoca.
        */
        if ($nombre === 'bazo') {
            $patronBazo =
                '/\bvaso\b'
                .'(?=.{0,500}\b(?:cuerpo|cola|cabeza)\s+esplenic[oa]\b)/u';

            if (preg_match($patronBazo, $dictado, $match, PREG_OFFSET_CAPTURE)) {
                $offsetBytes = (int)($match[0][1] ?? 0);
                $pos = mb_strlen(substr($dictado, 0, $offsetBytes), 'UTF-8');

                $menciones[] = [
                    'indice' => $indice,
                    'pos' => $pos,
                    'largo' => mb_strlen((string)$match[0][0])
                ];
            }
        }
    }

    /*
     * Recupera órganos pares cuyo nombre fue destruido por STT.
     * Ejemplo:
     * "Brillet derecho, vivas características del izquierdo..."
     */
    foreach ($organosInforme as $indice => $organo) {
        $nombre = gpt_origen_normalizar($organo['organo']);

        if (!preg_match('/^(.*)\s+(izquierdo|derecho)$/u', $nombre, $partes)) {
            continue;
        }

        $yaTieneMencion = false;
        foreach ($menciones as $mencion) {
            if ($mencion['indice'] === $indice) {
                $yaTieneMencion = true;
                break;
            }
        }

        if ($yaTieneMencion) {
            continue;
        }

        $base = trim($partes[1]);
        $lado = $partes[2];
        $otroLado = $lado === 'derecho' ? 'izquierdo' : 'derecho';
        $indiceReferencia = $indicesPorNombre[$base.' '.$otroLado] ?? null;

        if ($indiceReferencia === null) {
            continue;
        }

        $referenciaExiste = false;
        foreach ($menciones as $mencion) {
            if ($mencion['indice'] === $indiceReferencia) {
                $referenciaExiste = true;
                break;
            }
        }

        if (!$referenciaExiste) {
            continue;
        }

        $patron = '/\b'.preg_quote($lado, '/').'\b.{0,80}\bcaracteristicas?\b.{0,50}\b(?:del|que\s+el)\s+'.preg_quote($otroLado, '/').'\b/u';

        if (!preg_match($patron, $dictado, $match, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        $fragmento = (string)$match[0][0];
        $offsetBytes = (int)($match[0][1] ?? 0);
        $pos = mb_strlen(substr($dictado, 0, $offsetBytes), 'UTF-8');

        $posLado = mb_strpos($fragmento, $lado);
        if ($posLado !== false) {
            $pos += $posLado;
        }

        /*
         * Incluye también la palabra deformada inmediatamente anterior
         * para que no quede pegada al órgano previo ("brillet derecho").
         */
        $prefijo = mb_substr($dictado, 0, $pos);
        if (preg_match('/(\p{L}+)\s+$/u', $prefijo, $prev)) {
            $pos -= mb_strlen($prev[1]) + 1;
        }

        $menciones[] = [
            'indice' => $indice,
            'pos' => max(0, $pos),
            'largo' => mb_strlen($lado)
        ];
    }

    usort($menciones, static fn(array $a, array $b): int => $a['pos'] <=> $b['pos']);

    /*
     * Acumula todas las menciones del mismo órgano.
     * Esto permite frases posteriores como:
     * "me faltó que el páncreas también está heterogéneo".
     */
    $contextos = [];

    foreach ($organosInforme as $indice => $organo) {
        $propias = array_values(array_filter(
            $menciones,
            static fn(array $m): bool => $m['indice'] === $indice
        ));

        if (!$propias) {
            $contextos[$indice] = '';
            continue;
        }

        $fragmentos = [];

        foreach ($propias as $propia) {
            $inicio = $propia['pos'];
            $fin = mb_strlen($dictado);

            foreach ($menciones as $mencion) {
                if ($mencion['pos'] > $inicio && $mencion['indice'] !== $indice) {
                    $fin = $mencion['pos'];
                    break;
                }
            }

            $fragmento = trim(mb_substr($dictado, $inicio, $fin - $inicio));

            if ($fragmento !== '') {
                $fragmentos[] = $fragmento;
            }
        }

        $contextos[$indice] = trim(implode(' ', array_unique($fragmentos)));
    }

    /*
     * Herencia entre órganos pares.
     * "mismas características del izquierdo"
     * "mismas características que el izquierdo"
     *
     * No heredamos cifras del órgano contrario para evitar que una medida
     * izquierda pueda validar accidentalmente una medida derecha incorrecta.
     */
    foreach ($organosInforme as $indice => $organo) {
        $nombre = gpt_origen_normalizar($organo['organo']);
        $contexto = $contextos[$indice] ?? '';

        if (
            !preg_match('/^(.*)\s+(izquierdo|derecho)$/u', $nombre, $partes)
            || !preg_match(
                '/\bcaracteristicas?\b.{0,30}\b(?:del|que\s+el)\s+(izquierdo|derecho)\b/u',
                $contexto,
                $ref
            )
        ) {
            continue;
        }

        $nombreReferencia = trim($partes[1].' '.$ref[1]);
        $indiceReferencia = $indicesPorNombre[$nombreReferencia] ?? null;

        if (
            $indiceReferencia === null
            || $indiceReferencia === $indice
            || empty($contextos[$indiceReferencia])
        ) {
            continue;
        }

        $contextoReferencia = $contextos[$indiceReferencia];

        $contextoReferencia = preg_replace(
            '/\b\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?)?\b/u',
            ' ',
            $contextoReferencia
        ) ?? $contextoReferencia;

        $contextoReferencia = preg_replace('/\s+/u', ' ', $contextoReferencia) ?? $contextoReferencia;

        $contextos[$indice] = trim($contextoReferencia.' '.$contexto);
    }

    /*
    * Usa las diferencias A/B solo como evidencia de procedencia.
    *
    * Si una variante aparece dentro del contexto de ESTE órgano,
    * agregamos la alternativa del otro motor al mismo contexto.
    *
    * No decidimos cuál es clínicamente correcta: ambas siguen siendo
    * candidatas provenientes del audio. Las discrepancias clínicas
    * continúan siendo responsabilidad del revisor.
    */
    foreach ($contextos as $indice => $contexto) {
        if (trim($contexto) === '') {
            continue;
        }

        $evidencia = [];

        foreach ($diferenciasMotores as $diferencia) {
            $a = $diferencia['a_norm'] ?? '';
            $b = $diferencia['b_norm'] ?? '';

            $aUtil = $a !== ''
                && $a !== '(nada)'
                && (mb_strlen($a) >= 4 || preg_match('/\d/u', $a));

            $bUtil = $b !== ''
                && $b !== '(nada)'
                && (mb_strlen($b) >= 4 || preg_match('/\d/u', $b));

            $aEnContexto = $aUtil && str_contains($contexto, $a);
            $bEnContexto = $bUtil && str_contains($contexto, $b);

            if ($aEnContexto && $bUtil) {
                $evidencia[] = $b;
            }

            if ($bEnContexto && $aUtil) {
                $evidencia[] = $a;
            }
        }

        if ($evidencia) {
            $contextos[$indice] = trim(
                $contexto.' '.implode(' ', array_unique($evidencia))
            );
        }
    }

    return $contextos;
}

function gpt_origen_segmentar_atributos(string $texto, string $organo): array
{
    $resto = trim($texto);

    $patron = '/^\s*'.preg_quote($organo, '/').'\s*:?\s*/iu';
    $resto = preg_replace($patron, '', $resto, 1) ?? $resto;

    $partes = preg_split('/\s*,\s*|(?<=[.;])\s+/u', $resto) ?: [];
    $salida = [];

    foreach ($partes as $parte) {
        $parte = trim($parte, " \t\n\r\0\x0B,.;");

        if ($parte === '') {
            continue;
        }

        $salida[] = $parte;
    }

    return $salida;
}

function gpt_origen_tokens(string $texto): array
{
    $texto = gpt_origen_normalizar($texto);
    $tokens = preg_split('/[^\p{L}\p{N}.]+/u', $texto) ?: [];

    $stop = [
        'de','del','la','las','el','los','un','una','unos','unas','en','por',
        'con','sin','y','o','e','a','al','se','su','sus','que','cm'
    ];

    return array_values(array_filter($tokens, static function (string $token) use ($stop): bool {
        if ($token === '' || in_array($token, $stop, true)) {
            return false;
        }

        return preg_match('/^\d+(?:\.\d+)?$/', $token) === 1 || mb_strlen($token) >= 3;
    }));
}

function gpt_origen_score(string $frase, string $contexto): float
{
    if (trim($frase) === '' || trim($contexto) === '') {
        return 0.0;
    }

    $fraseNorm = gpt_origen_normalizar($frase);
    $contextoNorm = gpt_origen_normalizar($contexto);

    if ($fraseNorm !== '' && str_contains($contextoNorm, $fraseNorm)) {
        return 1.0;
    }

    $tokens = gpt_origen_tokens($frase);
    if (!$tokens) {
        return 0.0;
    }

    $pesoTotal = 0.0;
    $pesoCoincide = 0.0;

    foreach ($tokens as $token) {
        $esNumero = preg_match('/^\d+(?:\.\d+)?$/', $token) === 1;
        $peso = $esNumero ? 2.5 : 1.0;

        $pesoTotal += $peso;

        if (preg_match('/(^|[^\p{L}\p{N}.])'.preg_quote($token, '/').'([^\p{L}\p{N}.]|$)/u', $contextoNorm)) {
            $pesoCoincide += $peso;
        }
    }

    return $pesoTotal > 0 ? $pesoCoincide / $pesoTotal : 0.0;
}

function gpt_origen_extraer_numeros(string $texto): array
{
    preg_match_all('/\b\d+(?:\.\d+)?\b/u', gpt_origen_normalizar($texto), $m);
    return array_values(array_unique($m[0] ?? []));
}

function gpt_origen_sin_numeros(string $texto): string
{
    return trim((string)preg_replace('/\b\d+(?:\.\d+)?\b/u', ' ', $texto));
}

function gpt_origen_tiene_token_exclusivo(string $frase, string $favor, string $contra): bool
{
    $favorNorm = gpt_origen_normalizar($favor);
    $contraNorm = gpt_origen_normalizar($contra);

    foreach (gpt_origen_tokens($frase) as $token) {
        if (preg_match('/^\d+(?:\.\d+)?$/', $token) || mb_strlen($token) < 4) {
            continue;
        }

        $patron = '/(^|[^\p{L}\p{N}.])'.preg_quote($token, '/').'([^\p{L}\p{N}.]|$)/u';
        $enFavor = preg_match($patron, $favorNorm) === 1;
        $enContra = preg_match($patron, $contraNorm) === 1;

        if ($enFavor && !$enContra) {
            return true;
        }
    }

    return false;
}

function gpt_origen_clasificar_atributo(string $atributo, string $plantilla, string $dictado): string
{
    $atributoNorm = gpt_origen_normalizar($atributo);
    $plantillaNorm = gpt_origen_normalizar($plantilla);

    $scorePlantilla = gpt_origen_score($atributo, $plantilla);
    $scoreDictado = gpt_origen_score($atributo, $dictado);

    if ($plantilla === '') {
        return $scoreDictado >= 0.45 ? 'dictado' : 'desconocido';
    }

    if ($dictado === '') {
        return $scorePlantilla >= 0.45 ? 'plantilla' : 'desconocido';
    }

    // Si el atributo final está literalmente contenido en la plantilla,
    // no necesitamos destacarlo aunque también haya sido dictado.
    if ($atributoNorm !== '' && str_contains($plantillaNorm, $atributoNorm)) {
        return 'plantilla';
    }

    // Si el texto final no es igual a plantilla pero el dictado lo respalda
    // claramente, consideramos que hubo aporte/cambio del dictado.
    if ($scoreDictado >= 0.55) {
        return 'dictado';
    }

    // Si no encontramos respaldo suficiente en dictado pero sí en plantilla,
    // probablemente es una pequeña reformulación del texto base.
    if ($scorePlantilla >= 0.55) {
        return 'plantilla';
    }

    if ($scoreDictado >= 0.45) {
        return 'dictado';
    }

    return 'desconocido';
}

function gpt_origen_token_en_texto(string $token, string $texto): bool
{
    $token = gpt_origen_normalizar($token);
    $texto = gpt_origen_normalizar($texto);

    if ($token === '' || $texto === '') {
        return false;
    }

    if (preg_match('/^\d+(?:\.\d+)?$/', $token)) {
        return preg_match(
            '/(?<![\d.])'.preg_quote($token, '/').'(?![\d.])/',
            $texto
        ) === 1;
    }

    $variantes = [$token];

    if (preg_match('/^(.+)(os|as)$/u', $token, $m)) {
        $variantes[] = $m[1].($m[2] === 'os' ? 'o' : 'a');
    } elseif (preg_match('/^(.+)(o|a)$/u', $token, $m)) {
        $variantes[] = $m[1].($m[2] === 'o' ? 'os' : 'as');
    }

    foreach (array_unique($variantes) as $variante) {
        if (preg_match(
            '/(?<![\p{L}\p{N}])'.preg_quote($variante, '/').'(?![\p{L}\p{N}])/u',
            $texto
        )) {
            return true;
        }
    }

    return false;
}

function gpt_origen_segmentar_procedencia(string $atributo, string $plantilla, string $dictado): array
{
    $atributo = trim($atributo);

    if ($atributo === '') {
        return [];
    }

    if ($plantilla === '') {
        return [[
            'texto' => $atributo,
            'origen' => $dictado !== '' ? 'dictado' : 'desconocido'
        ]];
    }

    $atributoNorm = gpt_origen_normalizar($atributo);
    $plantillaNorm = gpt_origen_normalizar($plantilla);
    $dictadoNorm = gpt_origen_normalizar($dictado);

    preg_match_all(
        '/\p{L}+|\d+(?:[.,]\d+)?|[^\p{L}\p{N}]+/u',
        $atributo,
        $matches,
        PREG_OFFSET_CAPTURE
    );

    $piezas = $matches[0] ?? [];

    if (!$piezas) {
        return [[
            'texto' => $atributo,
            'origen' => gpt_origen_clasificar_atributo($atributo, $plantilla, $dictado)
        ]];
    }

    $stop = [
        'de','del','la','las','el','los','un','una','unos','unas','en','por',
        'con','sin','y','o','e','a','al','se','su','sus','que','cm'
    ];

    $modificadoresPrevios = [
        'semi','leve','levemente','ligeramente','moderadamente','marcadamente'
    ];

    $tokensAtributo = gpt_origen_tokens($atributo);
    $primerToken = $tokensAtributo[0] ?? '';
    $origenAtributo = gpt_origen_clasificar_atributo($atributo, $plantilla, $dictado);
    $tokens = [];

    foreach ($piezas as [$texto, $offset]) {
        if (!preg_match('/[\p{L}\p{N}]/u', $texto)) {
            continue;
        }

        $norm = gpt_origen_normalizar($texto);
        $esNumero = preg_match('/^\d+(?:\.\d+)?$/', $norm) === 1;

        if (!$esNumero && in_array($norm, $stop, true)) {
            $origen = null;
        } else {
            $enPlantilla = gpt_origen_token_en_texto($norm, $plantilla);
            $enDictado = gpt_origen_token_en_texto($norm, $dictado);

            if ($enDictado && !$enPlantilla) {
                $origen = 'dictado';
            } elseif ($enPlantilla && !$enDictado) {
                $origen = 'plantilla';
            } elseif ($enPlantilla && $enDictado) {
                $origen = 'plantilla';

                if ($norm === $primerToken) {
                    foreach ($modificadoresPrevios as $modificador) {
                        if (
                            str_contains($plantillaNorm, $modificador.' '.$norm)
                            && !str_contains($dictadoNorm, $modificador.' '.$norm)
                        ) {
                            $origen = 'dictado';
                            break;
                        }
                    }
                }
            } else {
                $origen = 'desconocido';
            }

            if ($origen === 'desconocido' && $origenAtributo === 'dictado') {
                $origen = 'dictado';
            }
        }

        $tokens[] = [
            'texto' => $texto,
            'offset' => $offset,
            'origen' => $origen
        ];
    }

    if (!$tokens) {
        return [[
            'texto' => $atributo,
            'origen' => $origenAtributo
        ]];
    }

    foreach ($tokens as $i => &$token) {
        if ($token['origen'] !== null) {
            continue;
        }

        $anterior = null;
        $siguiente = null;

        for ($j = $i - 1; $j >= 0; $j--) {
            if ($tokens[$j]['origen'] !== null) {
                $anterior = $tokens[$j]['origen'];
                break;
            }
        }

        for ($j = $i + 1, $n = count($tokens); $j < $n; $j++) {
            if ($tokens[$j]['origen'] !== null) {
                $siguiente = $tokens[$j]['origen'];
                break;
            }
        }

        if ($anterior !== null && $anterior === $siguiente) {
            $token['origen'] = $anterior;
        } elseif ($siguiente !== null) {
            $token['origen'] = $siguiente;
        } elseif ($anterior !== null) {
            $token['origen'] = $anterior;
        } else {
            $token['origen'] = $origenAtributo;
        }
    }
    unset($token);

    $modificadoresCambio = [
        'poco','muy','leve','levemente','ligeramente','moderadamente','marcadamente'
    ];

    for ($i = 0, $n = count($tokens) - 1; $i < $n; $i++) {
        $actual = gpt_origen_normalizar($tokens[$i]['texto']);
        $siguiente = gpt_origen_normalizar($tokens[$i + 1]['texto']);

        if (
            $tokens[$i]['origen'] !== 'dictado'
            || !in_array($actual, $modificadoresCambio, true)
            || $siguiente === ''
        ) {
            continue;
        }

        $frase = $actual.' '.$siguiente;

        if (
            str_contains($dictadoNorm, $frase)
            && !str_contains($plantillaNorm, $frase)
        ) {
            $tokens[$i + 1]['origen'] = 'dictado';
        }
    }

    // Si una medida fue confirmada como dictado, asociamos también el
    // fragmento desconocido inmediatamente anterior a esa medida.
    // Ej.: "aumentado de 0.65 cm".
    for ($i = 0, $n = count($tokens); $i < $n; $i++) {
        $norm = gpt_origen_normalizar($tokens[$i]['texto']);

        if (
            $tokens[$i]['origen'] !== 'dictado'
            || !preg_match('/^\d+(?:\.\d+)?$/', $norm)
        ) {
            continue;
        }

        for ($j = $i - 1; $j >= 0; $j--) {
            $prevNorm = gpt_origen_normalizar($tokens[$j]['texto']);

            if (in_array($prevNorm, [
                'de','del','la','las','el','los','en','por','con','y','e','a','al'
            ], true)) {
                continue;
            }

            if ($tokens[$j]['origen'] === 'desconocido') {
                $tokens[$j]['origen'] = 'dictado';
            }

            break;
        }
    }

    $segmentos = [];
    $inicio = 0;
    $origenActual = $tokens[0]['origen'];

    foreach ($tokens as $i => $token) {
        if ($i === 0 || $token['origen'] === $origenActual) {
            continue;
        }

        $fin = $token['offset'];
        $texto = trim(substr($atributo, $inicio, $fin - $inicio), " \t\n\r\0\x0B,.;");

        if ($texto !== '') {
            $segmentos[] = [
                'texto' => $texto,
                'origen' => $origenActual
            ];
        }

        $inicio = $token['offset'];
        $origenActual = $token['origen'];
    }

    $texto = trim(substr($atributo, $inicio), " \t\n\r\0\x0B,.;");

    if ($texto !== '') {
        $segmentos[] = [
            'texto' => $texto,
            'origen' => $origenActual
        ];
    }

    return $segmentos;
}

function gpt_clasificar_origen_informe(string $dictado, string $plantilla, string $informe): array
{
    $organosInforme = gpt_origen_detectar_organos_informe($informe);
    $plantillas = gpt_origen_buscar_plantilla_por_organo($plantilla, $organosInforme);
    $contextosDictado = gpt_origen_contextos_dictado($dictado, $organosInforme);
    $dictadoLimpio = gpt_origen_limpiar_dictado($dictado);

    $salida = [];

    foreach ($organosInforme as $indice => $organo) {
        $nombre = $organo['organo'];
        $nombreNorm = gpt_origen_normalizar($nombre);
        $textoPlantilla = $plantillas[$nombreNorm] ?? '';
        $textoDictadoDirecto = trim($contextosDictado[$indice] ?? '');
        $textoDictado = $textoDictadoDirecto;

        if ($textoPlantilla === '' && $textoDictado === '') {
            $textoDictado = $dictadoLimpio;
        }

        $organoNuevoDictado = $textoPlantilla === '' && $textoDictadoDirecto !== '';
        $atributos = [];

        foreach (gpt_origen_segmentar_atributos($organo['texto'], $nombre) as $atributo) {
            $segmentos = gpt_origen_segmentar_procedencia(
                $atributo,
                $textoPlantilla,
                $textoDictado
            );

            foreach ($segmentos as $segmento) {
                if ($organoNuevoDictado && $segmento['origen'] === 'desconocido') {
                    $segmento['origen'] = 'dictado';
                }

                $atributos[] = $segmento;
            }
        }

        // Conectores genéricos aislados heredan dictado cuando están junto
        // a contenido confirmado como dictado.
        $conectoresGenericos = [
            'se observa',
            'se observan',
            'ubicado',
            'ubicada',
            'ubicados',
            'ubicadas'
        ];

        foreach ($atributos as $i => &$atributoActual) {
            if ($atributoActual['origen'] !== 'desconocido') {
                continue;
            }

            $textoNorm = gpt_origen_normalizar($atributoActual['texto']);

            if (!in_array($textoNorm, $conectoresGenericos, true)) {
                continue;
            }

            $anterior = $atributos[$i - 1]['origen'] ?? null;
            $siguiente = $atributos[$i + 1]['origen'] ?? null;

            if ($anterior === 'dictado' || $siguiente === 'dictado') {
                $atributoActual['origen'] = 'dictado';
            }
        }
        unset($atributoActual);

        $salida[] = [
            'organo' => $nombre,
            'atributos' => $atributos
        ];
    }

    return ['organos' => $salida];
}