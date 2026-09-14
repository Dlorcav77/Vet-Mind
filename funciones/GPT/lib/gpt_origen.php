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

function gpt_origen_normalizar(string $texto): string
{
    $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'
    ]);
    $texto = str_replace(['×', ' x '], 'x', $texto);
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
    $dictado = gpt_origen_normalizar(gpt_origen_limpiar_dictado($dictado));
    $menciones = [];

    foreach ($organosInforme as $indice => $organo) {
        foreach (gpt_origen_aliases_organo($organo['organo']) as $alias) {
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

    usort($menciones, static fn(array $a, array $b): int => $a['pos'] <=> $b['pos']);

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

        $inicio = $propias[0]['pos'];
        $fin = mb_strlen($dictado);

        foreach ($menciones as $m) {
            if ($m['pos'] > $inicio && $m['indice'] !== $indice) {
                $fin = $m['pos'];
                break;
            }
        }

        $contextos[$indice] = trim(mb_substr($dictado, $inicio, $fin - $inicio));
    }

    $indicesPorNombre = [];
    foreach ($organosInforme as $indice => $organo) {
        $indicesPorNombre[gpt_origen_normalizar($organo['organo'])] = $indice;
    }

    foreach ($organosInforme as $indice => $organo) {
        $nombre = gpt_origen_normalizar($organo['organo']);
        $contexto = $contextos[$indice] ?? '';

        if (
            preg_match('/^(.*)\s+(izquierdo|derecho)$/u', $nombre, $partes)
            && preg_match('/mismas caracteristicas que el (izquierdo|derecho)/u', $contexto, $ref)
        ) {
            $nombreReferencia = trim($partes[1].' '.$ref[1]);
            $indiceReferencia = $indicesPorNombre[$nombreReferencia] ?? null;

            if ($indiceReferencia !== null && !empty($contextos[$indiceReferencia])) {
                $contextos[$indice] = trim($contextos[$indiceReferencia].' '.$contexto);
            }
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

function gpt_origen_clasificar_atributo(string $atributo, string $plantilla, string $dictado): string
{
    $scorePlantilla = gpt_origen_score($atributo, $plantilla);
    $scoreDictado = gpt_origen_score($atributo, $dictado);

    $numeros = gpt_origen_extraer_numeros($atributo);
    $dictadoNorm = gpt_origen_normalizar($dictado);

    $numeroEnDictado = false;
    foreach ($numeros as $numero) {
        if (preg_match('/(^|[^\d.])'.preg_quote($numero, '/').'([^\d.]|$)/', $dictadoNorm)) {
            $numeroEnDictado = true;
            break;
        }
    }

    $sinNumeros = gpt_origen_sin_numeros($atributo);
    $lexPlantilla = gpt_origen_score($sinNumeros, $plantilla);
    $lexDictado = gpt_origen_score($sinNumeros, $dictado);

    if ($plantilla === '') {
        return ($scoreDictado >= 0.45 || $numeroEnDictado) ? 'dictado' : 'desconocido';
    }

    if ($dictado === '') {
        return $scorePlantilla >= 0.45 ? 'plantilla' : 'desconocido';
    }

    if ($numeroEnDictado) {
        if ($lexPlantilla >= 0.78 && $lexDictado >= 0.45) {
            return 'mixto';
        }

        return 'dictado';
    }

    if ($scorePlantilla >= 0.72 && $scoreDictado >= 0.72) {
        return 'mixto';
    }

    if ($scoreDictado >= 0.62 && $scorePlantilla < 0.72) {
        return 'dictado';
    }

    if ($scorePlantilla >= 0.62 && $scoreDictado < 0.62) {
        return 'plantilla';
    }

    if ($scorePlantilla >= 0.50 && $scoreDictado >= 0.50) {
        return 'mixto';
    }

    if ($scoreDictado >= 0.45 && ($scoreDictado - $scorePlantilla) >= 0.15) {
        return 'dictado';
    }

    if ($scorePlantilla >= 0.45 && ($scorePlantilla - $scoreDictado) >= 0.15) {
        return 'plantilla';
    }

    return 'desconocido';
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
        $textoDictado = $contextosDictado[$indice] ?? '';

        if ($textoPlantilla === '' && $textoDictado === '') {
            $textoDictado = $dictadoLimpio;
        }

        $atributos = [];

        foreach (gpt_origen_segmentar_atributos($organo['texto'], $nombre) as $atributo) {
            $atributos[] = [
                'texto' => $atributo,
                'origen' => gpt_origen_clasificar_atributo(
                    $atributo,
                    $textoPlantilla,
                    $textoDictado
                )
            ];
        }

        $salida[] = [
            'organo' => $nombre,
            'atributos' => $atributos
        ];
    }

    return ['organos' => $salida];
}