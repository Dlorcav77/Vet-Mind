<?php
declare(strict_types=1);

/**
 * Preprocesamiento clínico determinista.
 * No realiza llamadas a IA ni modifica las transcripciones originales.
 */
function interpretacion_procesar_php(string $textoRecibido, ?array $origen = null): array
{
    $textoRecibido = trim($textoRecibido);

    if ($textoRecibido === '') {
        throw new InvalidArgumentException('El dictado está vacío.');
    }

    $texto = trim((string)($origen['texto_a'] ?? $textoRecibido));
    $fuente = $origen !== null ? 'motor_a' : 'texto_directo';

    $hallazgos = [];
    $alertas = [];

    $catalogoOrganos = require __DIR__ . '/../config/organos.php';

    $patronOrgano = $catalogoOrganos['patron'];

    /*
    * Alias de órganos ya resueltos por el validador STT.
    *
    * Ejemplos reales:
    *   vaso       -> Bazo
    *   adrenalina -> Adrenal
    *   Duoden     -> Duodeno
    *   Yiyuno     -> Yeyuno
    *
    * No modificamos la transcripción original.
    * Solo permitimos que el intérprete reconozca el término descartado
    * como inicio de órgano y lo normalice al órgano elegido.
    */
    $aliasesOrgano = [];

    foreach (($origen['resueltas'] ?? []) as $correccion) {
        if (!is_array($correccion)) {
            continue;
        }

        if (($correccion['origen'] ?? '') !== 'organo') {
            continue;
        }

        $elegido = trim((string)($correccion['elegido'] ?? ''));
        $descartado = trim((string)($correccion['descartado'] ?? ''));

        if ($elegido === '' || $descartado === '') {
            continue;
        }

        $clave = mb_strtolower($descartado, 'UTF-8');

        if (!isset($aliasesOrgano[$clave])) {
            $aliasesOrgano[$clave] = $elegido;

            $patronOrgano .= '|'
                . preg_quote($descartado, '/');
        }
    }

    $normalizarOrganoDetectado = static function (?string $organo) use ($aliasesOrgano): ?string {
        if ($organo === null || $organo === '') {
            return null;
        }

        $clave = mb_strtolower(trim($organo), 'UTF-8');

        return $aliasesOrgano[$clave] ?? $organo;
    };

    // Identificar comienzos de órgano después de una coma.
    // No dividir las comas de los números decimales.
    $patronInicioOrgano =
        '(?:(?:el|la|los|las)\s+)?'
        . '(?:' . $patronOrgano . ')'
        . '(?:\s+(?:izquierd[oa]|derech[oa]))?'
        . '(?!\p{L})';

    $textoSegmentado = preg_replace(
        '/,\s*(?=' . $patronInicioOrgano . ')/iu',
        "\n",
        $texto
    ) ?? $texto;

    // Separar también por puntuación y saltos de línea.
    $fragmentos = preg_split(
        '/(?<=[.!?;])\s+|\R+/u',
        $textoSegmentado,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    foreach ($fragmentos as $fragmento) {
        $fragmento = trim($fragmento);
        if ($fragmento === '') {
            continue;
        }

        $organo = null;
        $lateralidad = null;
        $estado = 'fragmento_sin_asignar';

        // Identificación únicamente cuando el órgano inicia el fragmento.
        $patronInicio = '/^(?:paciente\b[^,]{0,100},\s*)?'
            . '(?:(?:el|la|los|las)\s+)?'
            . '(' . $patronOrgano . ')'
            . '(?:\s+(izquierd[oa]|derech[oa]))?'
            . '(?!\p{L})/iu';

        if (preg_match($patronInicio, $fragmento, $m)) {
            $organoOriginal = $m[1];

            $organo = $normalizarOrganoDetectado(
                $organoOriginal
            );

            $lateralidad = !empty($m[2])
                ? mb_strtolower($m[2], 'UTF-8')
                : null;

            $claveOriginal = mb_strtolower(
                trim($organoOriginal),
                'UTF-8'
            );

            $estado = isset($aliasesOrgano[$claveOriginal])
                ? 'organo_corregido_stt'
                : 'extraido_textualmente';
        }

        // Si aparecen varios órganos, no atribuir todo a uno solo.
        preg_match_all(
            '/(?<!\p{L})(?:' . $patronOrgano . ')(?!\p{L})/iu',
            $fragmento,
            $coincidencias
        );

        if (count($coincidencias[0]) > 1) {
            $organo = null;
            $lateralidad = null;
            $estado = 'requiere_contexto';

            $alertas[] = [
                'tipo' => 'segmento_ambiguo',
                'evidencia' => $fragmento
            ];
        }

        // Extraer solamente medidas con unidad explícita.
        $medidas = [];

        preg_match_all(
            '/(?<![\p{L}\d])'
            . '(\d+(?:[.,]\d+)?'
            . '(?:\s*(?:x|×|por)\s*\d+(?:[.,]\d+)?){0,2})'
            . '\s*(cm|mm|cent[ií]metros?|mil[ií]metros?)'
            . '(?!\p{L})/iu',
            $fragmento,
            $coincidenciasMedidas,
            PREG_SET_ORDER
        );

        foreach ($coincidenciasMedidas as $medida) {
            $valor = str_replace(',', '.', $medida[1]);
            $valor = preg_replace('/\s*(?:x|×|por)\s*/iu', 'x', $valor);

            $unidad = preg_match('/^(?:mm|mil)/iu', $medida[2])
                ? 'mm'
                : 'cm';

            $medidas[] = [
                'valor' => $valor,
                'unidad' => $unidad,
                'original' => $medida[0]
            ];
        }

        $hallazgos[] = [
            'organo' => $organo,
            'lateralidad' => $lateralidad,
            'atributo' => 'fragmento_dictado',
            'valor_texto' => $fragmento,
            'medidas' => $medidas,
            'fuentes' => [$fuente],
            'evidencia' => $fragmento,
            'estado' => $estado
        ];
    }

        /*
     * Detectar referencias explícitas entre órganos/lateralidades.
     *
     * Ejemplo:
     *   "Riñón derecho, mismas características que el izquierdo"
     *
     * No copiamos atributos aquí.
     * Solo dejamos estructurada la referencia para que el generador
     * sepa que el órgano descrito hereda las características del citado.
     */
    $referenciasEntreOrganos = [];

    foreach ($hallazgos as $hallazgo) {
        $organoActual = $hallazgo['organo'] ?? null;
        $lateralidadActual = $hallazgo['lateralidad'] ?? null;
        $textoHallazgo = (string)($hallazgo['valor_texto'] ?? '');

        if (
            $organoActual === null
            || $lateralidadActual === null
            || $textoHallazgo === ''
        ) {
            continue;
        }

        if (!preg_match(
            '/\bmismas?\s+caracter[ií]sticas\s+que\s+(?:el|la)\s+'
            . '(izquierd[oa]|derech[oa])\b/iu',
            $textoHallazgo,
            $m
        )) {
            continue;
        }

        $referenciasEntreOrganos[] = [
            'organo_destino' => $organoActual,
            'lateralidad_destino' => $lateralidadActual,
            'organo_referencia' => $organoActual,
            'lateralidad_referencia' => mb_strtolower(
                $m[1],
                'UTF-8'
            ),
            'evidencia' => $m[0],
            'estado' => 'referencia_explicita'
        ];
    }

        /*
     * Detectar autocorrecciones numéricas explícitas del veterinario.
     *
     * Casos soportados inicialmente:
     *
     *   "0.79... no, era 0.57"
     *   "0.45, no en 0.51"
     *
     * No modificamos el dictado ni los hallazgos originales.
     * Solo dejamos estructurada la corrección para el generador.
     */
    $autocorrecciones = [];

    $resolverContextoAutocorreccion = static function (
        string $textoFuente,
        int $offset
    ) use ($patronOrgano, $normalizarOrganoDetectado): array {
        $textoAnteriorBytes = substr($textoFuente, 0, $offset);

        $contextoAnterior = mb_substr(
            $textoAnteriorBytes,
            -350,
            null,
            'UTF-8'
        );

        preg_match_all(
            '/(?<!\p{L})(' . $patronOrgano . ')'
            . '(?:\s+(izquierd[oa]|derech[oa]))?'
            . '(?!\p{L})/iu',
            $contextoAnterior,
            $organos,
            PREG_SET_ORDER
        );

        $ultimoOrgano = !empty($organos)
            ? end($organos)
            : null;

        $organo = $ultimoOrgano
            ? $normalizarOrganoDetectado($ultimoOrgano[1])
            : null;

        $lateralidad = $ultimoOrgano && !empty($ultimoOrgano[2])
            ? mb_strtolower($ultimoOrgano[2], 'UTF-8')
            : null;

        $ventanaAtributo = mb_substr(
            $textoAnteriorBytes,
            -140,
            null,
            'UTF-8'
        );

        $atributo = 'medida';

        if (preg_match('/\bpolo\s+caudal\b/iu', $ventanaAtributo)) {
            $atributo = 'polo_caudal';
        } elseif (preg_match('/\bpolo\s+craneal\b/iu', $ventanaAtributo)) {
            $atributo = 'polo_craneal';
        } elseif (preg_match('/\bgrosor\b/iu', $ventanaAtributo)) {
            $atributo = 'grosor';
        } elseif (preg_match('/\bpared\b/iu', $ventanaAtributo)) {
            $atributo = 'pared';
        } elseif (preg_match('/\btamañ[oa]\b/iu', $ventanaAtributo)) {
            $atributo = 'tamaño';
        }

        return [
            'organo' => $organo,
            'lateralidad' => $lateralidad,
            'atributo' => $atributo
        ];
    };

    $normalizarNumeroAutocorreccion = static function (string $valor): string {
        return str_replace(',', '.', trim($valor));
    };

    /*
     * Forma 1:
     *   "0.79... no, era 0.57"
     *
     * El primer valor es descartado y el segundo es el corregido.
     */
    preg_match_all(
        '/(?P<anterior>\d+(?:[.,]\d+)?)'
        . '\s*(?:cm|mm|cent[ií]metros?|mil[ií]metros?)?'
        . '[\s,.;:…-]{0,15}'
        . '\bno\s*,?\s*(?:era|es)\s+'
        . '(?P<corregido>\d+(?:[.,]\d+)?)'
        . '\s*(?:cm|mm|cent[ií]metros?|mil[ií]metros?)?/iu',
        $texto,
        $coincidenciasAutocorreccion,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    foreach ($coincidenciasAutocorreccion as $m) {
        $contexto = $resolverContextoAutocorreccion(
            $texto,
            $m[0][1]
        );

        $autocorrecciones[] = [
            'organo' => $contexto['organo'],
            'lateralidad' => $contexto['lateralidad'],
            'atributo' => $contexto['atributo'],
            'valor_anterior' => $normalizarNumeroAutocorreccion(
                $m['anterior'][0]
            ),
            'valor_corregido' => $normalizarNumeroAutocorreccion(
                $m['corregido'][0]
            ),
            'evidencia' => trim($m[0][0])
        ];
    }

    /*
     * Forma 2:
     *   "estaba en 0.45, no en 0.51"
     *
     * Aquí el valor correcto aparece PRIMERO
     * y después el veterinario niega el valor anterior.
     */
    preg_match_all(
        '/(?P<corregido>\d+(?:[.,]\d+)?)'
        . '\s*(?:cm|mm|cent[ií]metros?|mil[ií]metros?)?'
        . '\s*,?\s*\bno\s+en\s+'
        . '(?P<anterior>\d+(?:[.,]\d+)?)'
        . '\s*(?:cm|mm|cent[ií]metros?|mil[ií]metros?)?/iu',
        $texto,
        $coincidenciasAutocorreccionInvertida,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    foreach ($coincidenciasAutocorreccionInvertida as $m) {
        $contexto = $resolverContextoAutocorreccion(
            $texto,
            $m[0][1]
        );

        $autocorrecciones[] = [
            'organo' => $contexto['organo'],
            'lateralidad' => $contexto['lateralidad'],
            'atributo' => $contexto['atributo'],
            'valor_anterior' => $normalizarNumeroAutocorreccion(
                $m['anterior'][0]
            ),
            'valor_corregido' => $normalizarNumeroAutocorreccion(
                $m['corregido'][0]
            ),
            'evidencia' => trim($m[0][0])
        ];
    }

    // Localizar cada diferencia dentro de su transcripción original.
    $buscarContexto = static function (
        string $textoFuente,
        string $termino
    ) use ($patronOrgano, $normalizarOrganoDetectado): array {
        $termino = trim($termino, " \t\n\r\0\x0B.,;:");

        if ($textoFuente === '' || $termino === '') {
            return [];
        }

        $patron = '/(?<![\p{L}\d,.])'
            . preg_quote($termino, '/')
            . '(?![\p{L}\d,.])/iu';

        if (!preg_match($patron, $textoFuente, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $pos = mb_strlen(substr($textoFuente, 0, $m[0][1]), 'UTF-8');
        $anterior = mb_substr(
            $textoFuente,
            max(0, $pos - 350),
            min(350, $pos),
            'UTF-8'
        );

        preg_match_all(
            '/(?<!\p{L})(' . $patronOrgano . ')'
            . '(?:\s+(izquierd[oa]|derech[oa]))?(?!\p{L})/iu',
            $anterior,
            $organos,
            PREG_SET_ORDER
        );

        $ultimo = !empty($organos) ? end($organos) : null;

        return [
            'organo' => $ultimo
                ? $normalizarOrganoDetectado($ultimo[1])
                : null,
            'lateralidad' => $ultimo && !empty($ultimo[2])
                ? mb_strtolower($ultimo[2], 'UTF-8')
                : null,
            'contexto' => trim(mb_substr(
                $textoFuente,
                max(0, $pos - 90),
                220,
                'UTF-8'
            )),
            'continuacion' => mb_substr(
                $textoFuente,
                $pos,
                60,
                'UTF-8'
            )
        ];
    };

    $discrepancias = [];
    $textoA = (string)($origen['texto_a'] ?? '');
    $textoB = (string)($origen['texto_b'] ?? '');

    foreach (($origen['discrepancias'] ?? []) as $d) {
        $a = trim((string)($d['a'] ?? ''));
        $b = trim((string)($d['b'] ?? ''));
        $tipo = (string)($d['tipo'] ?? 'cambio');

        $normalizar = static fn(string $v): string =>
            mb_strtolower(trim($v, " \t\n\r\0\x0B.,;:"), 'UTF-8');

        // Omitir diferencias aisladas sin contenido clínico.
        if ($a === '' || $b === '') {
            $unico = $normalizar($a !== '' ? $a : $b);

            if (in_array($unico, ['en', 'y', 'de', 'del', 'el', 'la'], true)) {
                continue;
            }
        }

        // "un" y "1" son representaciones equivalentes del mismo número.
        $numerosTextuales = ['un' => '1', 'una' => '1', 'uno' => '1'];

        $numeroA = $numerosTextuales[$normalizar($a)] ?? $normalizar($a);
        $numeroB = $numerosTextuales[$normalizar($b)] ?? $normalizar($b);

        if ($tipo === 'numero' && $numeroA === $numeroB) {
            continue;
        }

        $contextoA = $buscarContexto($textoA, $a);
        $contextoB = $buscarContexto($textoB, $b);

        // Preferir una referencia anatómica explícita encontrada en B.
        // Si no existe, utilizar A; nunca inventar un órgano.
        $contexto = !empty($contextoB['organo'])
            ? $contextoB
            : $contextoA;

        $organo = $contexto['organo'] ?? null;
        $lateralidad = $contexto['lateralidad'] ?? null;

        $prioridad = 'media';
        $atributo = null;

        if ($tipo === 'numero') {
            $continuacion = (string)(
                $contextoB['continuacion']
                ?? $contextoA['continuacion']
                ?? ''
            );

            $hayDecimal = (bool)preg_match(
                '/\d+[.,]\d+/u',
                $a . ' ' . $b
            );

            $hayUnidad = (bool)preg_match(
                '/^.{0,45}\b(?:cm|mm|cent[ií]metros?|mil[ií]metros?)\b/iu',
                $continuacion
            );

            $prioridad = ($hayDecimal || $hayUnidad)
                ? 'alta'
                : 'media';

            $atributo = $prioridad === 'alta'
                ? 'medida'
                : 'dato_numerico_por_confirmar';
        }

        // Un órgano mencionado solamente por B merece atención especial.
        if ($tipo === 'solo_B' && preg_match(
            '/^(?:' . $patronOrgano . ')$/iu',
            $b
        )) {
            $organo = $b;
            $prioridad = 'alta';
            $atributo = 'presencia_de_organo';
        }

        $discrepancia = [
            'organo' => $organo,
            'lateralidad' => $lateralidad,
            'atributo' => $atributo,
            'alternativas' => [
                ['fuente' => 'motor_a', 'valor' => $a],
                ['fuente' => 'motor_b', 'valor' => $b]
            ],
            'motivo' => $tipo,
            'prioridad' => $prioridad,
            'estado' => 'pendiente'
        ];

        if ($prioridad === 'alta') {
            $discrepancia['evidencia_contexto'] = [
                'motor_a' => $contextoA['contexto'] ?? '',
                'motor_b' => $contextoB['contexto'] ?? ''
            ];

            if ($atributo === 'medida') {
                $alertas[] = [
                    'tipo' => 'medida_discrepante',
                    'organo' => $organo,
                    'alternativas' => [$a, $b],
                    'detalle' => 'No confirmar una medida sin resolver la discrepancia.'
                ];
            }
        }

        $discrepancias[] = $discrepancia;
    }

    // Presentar primero las discrepancias de mayor importancia.
    usort($discrepancias, static function (array $a, array $b): int {
        $orden = ['alta' => 0, 'media' => 1, 'baja' => 2];

        return ($orden[$a['prioridad']] ?? 2)
            <=> ($orden[$b['prioridad']] ?? 2);
    });



    return [
        'version_esquema' => '1',
        'metodo' => 'php',
        'hallazgos' => $hallazgos,
        'correcciones_stt' => $origen['resueltas'] ?? [],
        'autocorrecciones' => $autocorrecciones,
        'referencias_entre_organos' => $referenciasEntreOrganos,
        'discrepancias' => $discrepancias,
        'alertas' => $alertas
    ];
}
