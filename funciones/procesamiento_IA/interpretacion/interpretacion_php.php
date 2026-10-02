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
     * Correcciones de órganos resueltas por el validador STT.
     *
     * Las correcciones nuevas incluyen indice_a y se aplican SOLO
     * a esa ocurrencia concreta del Motor A.
     *
     * Los registros históricos que no poseen índice conservan
     * temporalmente el comportamiento antiguo mediante $aliasesOrgano.
     */
    $aliasesOrgano = [];
    $correccionesOrganoLocales = [];

    /*
    * Correcciones localizadas disponibles también para resolver
    * el contexto anatómico de discrepancias posteriores.
    */
    $correccionesOrganoContextoA = [];
    $correccionesOrganoContextoB = [];

    $normalizarClaveAlias = static function (string $valor): string {
        $valor = mb_strtolower(trim($valor), 'UTF-8');

        $valor = strtr($valor, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
            'ü' => 'u',
        ]);

        return preg_replace(
            '/[^a-z0-9]/',
            '',
            $valor
        ) ?? '';
    };

    foreach (($origen['resueltas'] ?? []) as $correccion) {
        if (!is_array($correccion)) {
            continue;
        }

        if (($correccion['origen'] ?? '') !== 'organo') {
            continue;
        }

        $elegido = trim(
            (string)($correccion['elegido'] ?? '')
        );

        $descartado = trim(
            (string)($correccion['descartado'] ?? '')
        );

        if ($elegido === '' || $descartado === '') {
            continue;
        }

        /*
         * Corrección nueva localizada.
         *
         * interpretacion_php trabaja sobre Motor A, por lo que
         * usamos indice_a.
         */
        $indiceCorreccionA = (
            array_key_exists('indice_a', $correccion)
            && $correccion['indice_a'] !== null
        )
            ? (int)$correccion['indice_a']
            : null;

        $indiceCorreccionB = (
            array_key_exists('indice_b', $correccion)
            && $correccion['indice_b'] !== null
        )
            ? (int)$correccion['indice_b']
            : null;

        if (
            $indiceCorreccionA !== null
            || $indiceCorreccionB !== null
        ) {
            if ($indiceCorreccionA !== null) {
                /*
                * Esta lista se mantiene para la segmentación principal,
                * que trabaja sobre Motor A.
                */
                $correccionesOrganoLocales[] = [
                    'elegido' => $elegido,
                    'descartado' => $descartado,
                    'indice_a' => $indiceCorreccionA,
                ];

                $correccionesOrganoContextoA[] = [
                    'elegido' => $elegido,
                    'descartado' => $descartado,
                    'indice_token' => $indiceCorreccionA,
                ];
            }

            if ($indiceCorreccionB !== null) {
                $correccionesOrganoContextoB[] = [
                    'elegido' => $elegido,
                    'descartado' => $descartado,
                    'indice_token' => $indiceCorreccionB,
                ];
            }

            /*
            * Una corrección que ya posee índices nunca debe convertirse
            * después en alias global.
            */
            continue;
        }

        /*
         * Compatibilidad con registros históricos anteriores a los índices.
         * Solo esos registros mantienen el alias global antiguo.
         */
        $clave = mb_strtolower(
            $descartado,
            'UTF-8'
        );

        if (!isset($aliasesOrgano[$clave])) {
            $aliasesOrgano[$clave] = $elegido;

            $patronOrgano .= '|'
                . preg_quote($descartado, '/');
        }
    }

    $normalizarOrganoDetectado = static function (
        ?string $organo
    ) use ($aliasesOrgano): ?string {
        if ($organo === null || $organo === '') {
            return null;
        }

        $clave = mb_strtolower(
            trim($organo),
            'UTF-8'
        );

        return $aliasesOrgano[$clave] ?? $organo;
    };

    /*
     * Los índices vienen del comparador, que previamente normaliza
     * espacios alrededor del separador decimal.
     *
     * Trabajamos sobre una copia para mantener la misma numeración
     * de tokens sin modificar el dictado original almacenado.
     */
    $textoAnalisis = preg_replace(
        '/(\d)\s*([.,])\s*(\d)/u',
        '$1$2$3',
        $texto
    ) ?? $texto;

    /*
     * Insertamos una marca interna ÚNICAMENTE delante de la ocurrencia
     * corregida. La marca nunca llega a hallazgos ni al informe.
     *
     * Esto evita añadir "Vaso", "Duden", etc. al patrón global.
     */
    $marcasOrganoLocales = [];

    if (!empty($correccionesOrganoLocales)) {
        preg_match_all(
            '/\S+/u',
            $textoAnalisis,
            $tokensAnalisis,
            PREG_OFFSET_CAPTURE
        );

        $inserciones = [];

        foreach ($correccionesOrganoLocales as $correccionLocal) {
            $indice = $correccionLocal['indice_a'];

            if (!isset($tokensAnalisis[0][$indice])) {
                continue;
            }

            $token = (string)$tokensAnalisis[0][$indice][0];
            $offset = (int)$tokensAnalisis[0][$indice][1];

            /*
             * Validación de seguridad:
             * el token situado en indice_a debe ser realmente
             * el término descartado.
             */
            if (
                $normalizarClaveAlias($token)
                !== $normalizarClaveAlias(
                    $correccionLocal['descartado']
                )
            ) {
                continue;
            }

            $idMarca = count($marcasOrganoLocales);

            $marcasOrganoLocales[$idMarca] = $correccionLocal;

            $inserciones[] = [
                'offset' => $offset,
                'marca' => '__VMORG' . $idMarca . '__',
            ];
        }

        /*
         * Insertar desde el final para no alterar los offsets
         * de las posiciones anteriores.
         */
        usort(
            $inserciones,
            static function (array $a, array $b): int {
                return $b['offset'] <=> $a['offset'];
            }
        );

        foreach ($inserciones as $insercion) {
            $offset = $insercion['offset'];

            $textoAnalisis =
                substr($textoAnalisis, 0, $offset)
                . "\n"
                . $insercion['marca']
                . substr($textoAnalisis, $offset);
        }
    }

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
        $textoAnalisis
    ) ?? $textoAnalisis;

    // Separar también por puntuación y saltos de línea.
    $fragmentos = preg_split(
        '/(?<=[.!?;])\s+|\R+/u',
        $textoSegmentado,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    $organoActivo = null;
    $lateralidadActiva = null;

    $esHallazgoCavitarioIndependiente = static function (
        string $fragmento
    ): bool {
        $fragmento = trim($fragmento);

        return (bool)preg_match(
            '/^(?:se\s+observan?\s+)?(?:'
            . 'neumoperitone\p{L}*'
            . '|derrame\s+peritone\p{L}*'
            . '|l[ií]quido\s+libre\b'
            . '|efusi[oó]n\s+peritone\p{L}*'
            . ')/iu',
            $fragmento
        );
    };

    $esContinuacionContextual = static function (string $fragmento): bool {
        $fragmento = trim($fragmento);

        return (bool)preg_match(
            '/^(?:'
            . 'imagen\s+p[eé]lvica\b'
            . '|ur[eé]ter\b'
            . '|se\s+observan?\b'
            . '|estratificaci[oó]n\b'
            . '|ecogenicidad\b'
            . '|ecotextura\b'
            . '|pared\b'
            . '|grosor\b'
            . '|contenido\b'
            . '|forma\b'
            . '|bordes?\b'
            . '|l[ií]mite\b'
            . '|relaci[oó]n\b'
            . '|pelvis\b'
            . '|vasculatura\b'
            . '|par[eé]nquima\b'
            . '|c[aá]psula\b'
            . '|motilidad\b'
            . '|(?:la\s+)?capa\s+(?:muscular|submucosa|mucosa)\b'
            . '|con\s+predominio\s+de\s+la\s+capa\b'
            . '|(?:y\s+)?la\s+(?:segunda|tercera|cuarta)\s+estructura\b'
            . '|(?:ah|ay),\s*(?:no,\s*)?perd[oó]n\b'
            . ')/iu',
            $fragmento
        );
    };

    foreach ($fragmentos as $fragmento) {
        $fragmento = trim($fragmento);
        if ($fragmento === '') {
            continue;
        }

        $organo = null;
        $lateralidad = null;
        $estado = 'fragmento_sin_asignar';

        $correccionOrganoLocal = null;

        /*
         * Una marca __VMORGn__ significa que ESTA ocurrencia concreta
         * fue resuelta por el validador.
         */
        if (
            preg_match(
                '/^__VMORG(\d+)__/u',
                $fragmento,
                $marcaLocal
            )
        ) {
            $idMarca = (int)$marcaLocal[1];

            $fragmento = preg_replace(
                '/^__VMORG\d+__/u',
                '',
                $fragmento,
                1
            ) ?? $fragmento;

            $fragmento = ltrim($fragmento);

            $correccionOrganoLocal =
                $marcasOrganoLocales[$idMarca] ?? null;

            if ($correccionOrganoLocal !== null) {
                $organo = $correccionOrganoLocal['elegido'];

                $descartadoLocal =
                    $correccionOrganoLocal['descartado'];

                $patronLateralidadLocal =
                    '/^'
                    . preg_quote($descartadoLocal, '/')
                    . '(?:\s+(izquierd[oa]|derech[oa]))?'
                    . '(?!\p{L})/iu';

                if (
                    preg_match(
                        $patronLateralidadLocal,
                        $fragmento,
                        $mLocal
                    )
                ) {
                    $lateralidad = !empty($mLocal[1])
                        ? mb_strtolower(
                            $mLocal[1],
                            'UTF-8'
                        )
                        : null;
                }

                $estado = 'organo_corregido_stt';

                $organoActivo = $organo;
                $lateralidadActiva = $lateralidad;
            }
        }

        // Identificación únicamente cuando el órgano inicia el fragmento.
        $patronInicio = '/^(?:paciente\b[^,]{0,100},\s*)?'
            . '(?:(?:el|la|los|las)\s+)?'
            . '(' . $patronOrgano . ')'
            . '(?:\s+(izquierd[oa]|derech[oa]))?'
            . '(?!\p{L})/iu';

        if (
            $organo === null
            && preg_match($patronInicio, $fragmento, $m)
        ) {
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

            $organoActivo = $organo;
            $lateralidadActiva = $lateralidad;
        }

        // Si aparecen varios órganos DISTINTOS, no atribuir todo a uno solo.
        preg_match_all(
            '/(?<!\p{L})(?<organo>' . $patronOrgano . ')'
            . '(?:\s+(?<lateralidad>izquierd[oa]|derech[oa]))?'
            . '(?!\p{L})/iu',
            $fragmento,
            $coincidencias,
            PREG_SET_ORDER
        );

        $contextosOrgano = [];

        foreach ($coincidencias as $coincidenciaOrgano) {
            $organoCoincidencia = $normalizarOrganoDetectado(
                $coincidenciaOrgano['organo'] ?? null
            );

            if ($organoCoincidencia === null) {
                continue;
            }

            $lateralidadCoincidencia = !empty($coincidenciaOrgano['lateralidad'])
                ? mb_strtolower($coincidenciaOrgano['lateralidad'], 'UTF-8')
                : null;

            $claveContexto = mb_strtolower(
                $organoCoincidencia,
                'UTF-8'
            ) . '|' . ($lateralidadCoincidencia ?? '');

            $contextosOrgano[$claveContexto] = true;
        }

        /*
         * El órgano corregido localmente también cuenta al detectar
         * si el fragmento mezcla órganos distintos.
         */
        if (
            $correccionOrganoLocal !== null
            && $organo !== null
        ) {
            $claveContextoLocal = mb_strtolower(
                $organo,
                'UTF-8'
            ) . '|' . ($lateralidad ?? '');

            $contextosOrgano[$claveContextoLocal] = true;
        }

        $cantidadContextosOrgano = count($contextosOrgano);

        $esCorreccionConOrganoExplicito =
            $organo === null
            && $cantidadContextosOrgano === 1
            && preg_match(
                '/^(?:ah|ay),\s*(?:no,\s*)?perd[oó]n\b/iu',
                $fragmento
            );

        if ($esCorreccionConOrganoExplicito) {
            $coincidenciaUnica = $coincidencias[0] ?? null;

            if ($coincidenciaUnica) {
                $organo = $normalizarOrganoDetectado(
                    $coincidenciaUnica['organo'] ?? null
                );

                $lateralidad = !empty($coincidenciaUnica['lateralidad'])
                    ? mb_strtolower(
                        $coincidenciaUnica['lateralidad'],
                        'UTF-8'
                    )
                    : null;

                $estado = 'continuacion_contextual';

                $organoActivo = $organo;
                $lateralidadActiva = $lateralidad;
            }
        }

        if ($cantidadContextosOrgano > 1) {
            $organo = null;
            $lateralidad = null;
            $estado = 'requiere_contexto';

            $alertas[] = [
                'tipo' => 'segmento_ambiguo',
                'evidencia' => $fragmento
            ];
        } elseif (
            $organo === null
            && $organoActivo !== null
            && $cantidadContextosOrgano === 0
            && !$esHallazgoCavitarioIndependiente($fragmento)
            && $esContinuacionContextual($fragmento)
        ) {
            $organo = $organoActivo;
            $lateralidad = $lateralidadActiva;
            $estado = 'continuacion_contextual';
        } elseif (
            $organo === null
            && $estado === 'fragmento_sin_asignar'
        ) {
            $organoActivo = null;
            $lateralidadActiva = null;
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
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($coincidenciasMedidas as $medida) {
            $textoMedida = (string)$medida[0][0];
            $offsetMedida = (int)$medida[0][1];

            /*
            * Evitar rescatar como medida válida el segundo número
            * de una secuencia numérica corrupta.
            *
            * Ejemplo real:
            *   "0.2 59 centímetros"
            *
            * El antiguo extractor tomaba "59 centímetros".
            * Si inmediatamente antes de la coincidencia existe otro
            * número separado solo por espacios, la medida queda sin
            * extraer para que la discrepancia STT la resuelva después.
            */
            $textoAnterior = substr(
                $fragmento,
                0,
                $offsetMedida
            );

            if (
                preg_match(
                    '/\d+(?:[.,]\d+)?\s+$/u',
                    $textoAnterior
                )
            ) {
                continue;
            }

            $valor = str_replace(
                ',',
                '.',
                (string)$medida[1][0]
            );

            $valor = preg_replace(
                '/\s*(?:x|×|por)\s*/iu',
                'x',
                $valor
            );

            $unidadTexto = (string)$medida[2][0];

            $unidad = preg_match(
                '/^(?:mm|mil)/iu',
                $unidadTexto
            )
                ? 'mm'
                : 'cm';

            $medidas[] = [
                'valor' => $valor,
                'unidad' => $unidad,
                'original' => $textoMedida
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
     * La referencia puede existir en cualquiera de los dos STT.
     * No damos prioridad automática a Motor A ni Motor B.
     *
     * No copiamos atributos aquí.
     * Solo dejamos estructurada la referencia para que el generador
     * sepa que el órgano descrito hereda características del citado.
     */
    $referenciasEntreOrganos = [];
    $clavesReferenciasEntreOrganos = [];

    $agregarReferenciaEntreOrganos = static function (
        ?string $organoDestino,
        ?string $lateralidadDestino,
        ?string $lateralidadReferencia,
        string $evidencia
    ) use (
        &$referenciasEntreOrganos,
        &$clavesReferenciasEntreOrganos,
        $normalizarClaveAlias
    ): void {
        if (
            $organoDestino === null
            || $organoDestino === ''
            || $lateralidadDestino === null
            || $lateralidadDestino === ''
            || $lateralidadReferencia === null
            || $lateralidadReferencia === ''
        ) {
            return;
        }

        $lateralidadDestino = mb_strtolower(
            $lateralidadDestino,
            'UTF-8'
        );

        $lateralidadReferencia = mb_strtolower(
            $lateralidadReferencia,
            'UTF-8'
        );

        /*
         * Deduplicar si ambos motores dicen la misma referencia
         * o si ya fue detectada desde los hallazgos segmentados.
         */
        $clave =
            $normalizarClaveAlias($organoDestino)
            . '|'
            . $lateralidadDestino
            . '|'
            . $lateralidadReferencia;

        if (isset($clavesReferenciasEntreOrganos[$clave])) {
            return;
        }

        $clavesReferenciasEntreOrganos[$clave] = true;

        $referenciasEntreOrganos[] = [
            'organo_destino' => $organoDestino,
            'lateralidad_destino' => $lateralidadDestino,
            'organo_referencia' => $organoDestino,
            'lateralidad_referencia' => $lateralidadReferencia,
            'evidencia' => $evidencia,
            'estado' => 'referencia_explicita'
        ];
    };

    /*
     * Primera vía:
     * conservar la detección histórica sobre hallazgos ya segmentados.
     *
     * Esto mantiene compatibilidad con correcciones locales de órgano
     * que PHP ya pudo haber aplicado durante la segmentación.
     */
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

        if (
            !preg_match(
                '/\bmismas?\s+caracter[ií]sticas\s+que\s+(?:el|la)\s+'
                . '(izquierd[oa]|derech[oa])\b/iu',
                $textoHallazgo,
                $m
            )
        ) {
            continue;
        }

        $agregarReferenciaEntreOrganos(
            $organoActual,
            $lateralidadActual,
            $m[1],
            $m[0]
        );
    }

    /*
     * Segunda vía:
     * revisar directamente las transcripciones completas.
     *
     * Esto permite detectar referencias que aparecen solamente
     * en Motor B o que la segmentación principal de Motor A
     * no pudo estructurar.
     */
    $textosParaReferencias = [];

    if ($texto !== '') {
        $textosParaReferencias[] = $texto;
    }

    if ($origen !== null) {
        $textoReferenciaB = trim(
            (string)($origen['texto_b'] ?? '')
        );

        if ($textoReferenciaB !== '') {
            $textosParaReferencias[] = $textoReferenciaB;
        }
    }

    /*
     * Evitar procesar dos veces textos idénticos.
     */
    $textosParaReferencias = array_values(
        array_unique($textosParaReferencias)
    );

    $detectarReferenciasEnTexto = static function (
        string $textoFuente
    ) use (
        $patronOrgano,
        $normalizarOrganoDetectado,
        $agregarReferenciaEntreOrganos
    ): void {
        $fragmentosReferencia = preg_split(
            '/(?<=[.!?;])\s+|\R+/u',
            $textoFuente,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        foreach ($fragmentosReferencia as $fragmentoReferencia) {
            $fragmentoReferencia = trim(
                (string)$fragmentoReferencia
            );

            if ($fragmentoReferencia === '') {
                continue;
            }

            preg_match_all(
                '/\bmismas?\s+caracter[ií]sticas\s+que\s+(?:el|la)\s+'
                . '(izquierd[oa]|derech[oa])\b/iu',
                $fragmentoReferencia,
                $referenciasEncontradas,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE
            );

            foreach ($referenciasEncontradas as $referenciaEncontrada) {
                $evidencia = trim(
                    (string)($referenciaEncontrada[0][0] ?? '')
                );

                $offsetReferencia = (int)(
                    $referenciaEncontrada[0][1] ?? -1
                );

                $lateralidadReferencia = (string)(
                    $referenciaEncontrada[1][0] ?? ''
                );

                if (
                    $evidencia === ''
                    || $offsetReferencia < 0
                    || $lateralidadReferencia === ''
                ) {
                    continue;
                }

                /*
                 * Buscar el órgano lateralizado más cercano
                 * ANTES de "mismas características".
                 *
                 * Si hubiera más de uno en la misma frase,
                 * el más cercano es el destino de la referencia.
                 */
                $textoPrevioReferencia = substr(
                    $fragmentoReferencia,
                    0,
                    $offsetReferencia
                );

                preg_match_all(
                    '/(?<!\p{L})'
                    . '(' . $patronOrgano . ')'
                    . '\s+(izquierd[oa]|derech[oa])'
                    . '(?!\p{L})/iu',
                    $textoPrevioReferencia,
                    $organosPrevios,
                    PREG_SET_ORDER
                );

                if (empty($organosPrevios)) {
                    continue;
                }

                $organoPrevio =
                    $organosPrevios[
                        array_key_last($organosPrevios)
                    ];

                $organoDestino = $normalizarOrganoDetectado(
                    $organoPrevio[1] ?? null
                );

                $lateralidadDestino =
                    !empty($organoPrevio[2])
                        ? mb_strtolower(
                            $organoPrevio[2],
                            'UTF-8'
                        )
                        : null;

                $agregarReferenciaEntreOrganos(
                    $organoDestino,
                    $lateralidadDestino,
                    $lateralidadReferencia,
                    $evidencia
                );
            }
        }
    };

    foreach ($textosParaReferencias as $textoParaReferencia) {
        $detectarReferenciasEnTexto(
            $textoParaReferencia
        );
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

        /*
     * Para autocorrecciones semánticas damos prioridad al órgano
     * que inicia el bloque clínico previo.
     *
     * Ejemplo:
     *   "Páncreas rama derecha..., peritoneo reactivo.
     *    Ay, no, perdón..."
     *
     * El contexto debe seguir siendo Páncreas, no "peritoneo".
     */
    $resolverContextoAutocorreccionSemantica = static function (
        string $textoFuente,
        int $offset
    ) use (
        $resolverContextoAutocorreccion,
        $patronOrgano,
        $normalizarOrganoDetectado,
        $esHallazgoCavitarioIndependiente
    ): array {
        /*
        * Mantener como fallback el resolver existente.
        */
        $contexto = $resolverContextoAutocorreccion(
            $textoFuente,
            $offset
        );

        $textoAnteriorBytes = substr(
            $textoFuente,
            0,
            $offset
        );

        /*
        * 1. Si después del marcador de corrección aparece
        * explícitamente un órgano, ese órgano es el contexto
        * más fuerte.
        *
        * Ejemplo:
        *   "Estómago aumentado. Perdón, el colon está aumentado."
        */
        $desdeMarcador = substr(
            $textoFuente,
            $offset,
            220
        );

        $patronOrganoDespuesCorreccion =
            '/^(?:'
            . 'perd[oó]n'
            . '|corrijo'
            . '|mejor\s+dicho'
            . '|quise\s+decir'
            . ')\b'
            . '[\s,;:\-]*'
            . '(?:(?:el|la|los|las)\s+)?'
            . '(' . $patronOrgano . ')'
            . '(?:\s+(izquierd[oa]|derech[oa]))?'
            . '(?!\p{L})/iu';

        if (
            preg_match(
                $patronOrganoDespuesCorreccion,
                $desdeMarcador,
                $mPosterior
            )
        ) {
            $contexto['organo'] = $normalizarOrganoDetectado(
                $mPosterior[1]
            );

            $contexto['lateralidad'] = !empty($mPosterior[2])
                ? mb_strtolower(
                    $mPosterior[2],
                    'UTF-8'
                )
                : null;

            return $contexto;
        }

        /*
        * Obtener solamente el fragmento clínico inmediato
        * donde apareció el marcador.
        */
        $fragmentosHastaMarca = preg_split(
            '/(?<=[.!?;])\s+|\R+/u',
            $textoAnteriorBytes,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $fragmentoActual = !empty($fragmentosHastaMarca)
            ? trim(
                (string)$fragmentosHastaMarca[
                    array_key_last($fragmentosHastaMarca)
                ]
            )
            : '';

        /*
        * 2. Una autocorrección dentro de un hallazgo cavitario
        * independiente no debe heredar el órgano anterior.
        *
        * Ejemplo real:
        *   "Adrenal izquierda...
        *    Se observa neumoperitoneo..., perdón..."
        *
        * El "perdón" pertenece al neumoperitoneo,
        * no a la adrenal.
        */
        if (
            $fragmentoActual !== ''
            && $esHallazgoCavitarioIndependiente(
                $fragmentoActual
            )
        ) {
            $contexto['organo'] = null;
            $contexto['lateralidad'] = null;

            return $contexto;
        }

        /*
        * 3. Si el propio fragmento actual comienza explícitamente
        * con un órgano, usar ese contexto antes de buscar hacia atrás.
        */
        $patronInicioFragmento =
            '/^(?:paciente\b[^,]{0,100},\s*)?'
            . '(?:en\s+)?'
            . '(?:(?:el|la|los|las)\s+)?'
            . '(' . $patronOrgano . ')'
            . '(?:\s+(izquierd[oa]|derech[oa]))?'
            . '(?!\p{L})/iu';

        if (
            $fragmentoActual !== ''
            && preg_match(
                $patronInicioFragmento,
                $fragmentoActual,
                $mActual
            )
        ) {
            $contexto['organo'] = $normalizarOrganoDetectado(
                $mActual[1]
            );

            $contexto['lateralidad'] = !empty($mActual[2])
                ? mb_strtolower(
                    $mActual[2],
                    'UTF-8'
                )
                : null;

            return $contexto;
        }

        /*
        * 4. Fallback histórico:
        * buscar hacia atrás el último bloque que comience
        * explícitamente con un órgano.
        */
        $ventanaAnterior = mb_substr(
            $textoAnteriorBytes,
            -500,
            null,
            'UTF-8'
        );

        $fragmentosPrevios = preg_split(
            '/(?<=[.!?;])\s+|\R+/u',
            $ventanaAnterior,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $patronInicioContexto =
            '/^(?:paciente\b[^,]{0,100},\s*)?'
            . '(?:en\s+)?'
            . '(?:(?:el|la|los|las)\s+)?'
            . '(' . $patronOrgano . ')'
            . '(?:\s+(izquierd[oa]|derech[oa]))?'
            . '(?!\p{L})/iu';

        for (
            $i = count($fragmentosPrevios) - 1;
            $i >= 0;
            $i--
        ) {
            $fragmentoPrevio = trim(
                (string)$fragmentosPrevios[$i]
            );

            if ($fragmentoPrevio === '') {
                continue;
            }

            if (
                preg_match(
                    '/^(?:(?:ah|ay)\s*,?\s*)?(?:no\s*,?\s*)?$/iu',
                    $fragmentoPrevio
                )
            ) {
                continue;
            }

            if (
                !preg_match(
                    $patronInicioContexto,
                    $fragmentoPrevio,
                    $m
                )
            ) {
                continue;
            }

            $contexto['organo'] = $normalizarOrganoDetectado(
                $m[1]
            );

            $contexto['lateralidad'] = !empty($m[2])
                ? mb_strtolower(
                    $m[2],
                    'UTF-8'
                )
                : null;

            break;
        }

        return $contexto;
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

        /*
     * Detectar posibles autocorrecciones SEMÁNTICAS.
     *
     * IMPORTANTE:
     * PHP NO decide qué valor es correcto.
     * Solo detecta una señal explícita del hablante y entrega al generador
     * una ventana de contexto para que la IA que YA genera el informe
     * interprete la corrección.
     *
     * Esto NO realiza ninguna llamada adicional a IA.
     */
    $autocorreccionesCandidatas = [];

    /*
     * Para analizar autocorrecciones semánticas usamos una COPIA del texto
     * donde las correcciones de órgano ya resueltas por STT se reflejan
     * únicamente en esta representación auxiliar.
     *
     * El dictado original almacenado NO se modifica.
     *
     * Ejemplo Daisy:
     *   "Vaso ... no, perdón..."
     * pasa aquí a:
     *   "Bazo ... no, perdón..."
     *
     * Así el contexto semántico no queda erróneamente asociado al Colon.
     */
    $textoAutocorreccionSemantica = $textoAnalisis;

    foreach ($marcasOrganoLocales as $idMarca => $correccionLocal) {
        $marca = '__VMORG' . $idMarca . '__';

        $elegido = trim(
            (string)($correccionLocal['elegido'] ?? '')
        );

        $descartado = trim(
            (string)($correccionLocal['descartado'] ?? '')
        );

        if ($elegido === '' || $descartado === '') {
            continue;
        }

        $patronCorreccionLocal =
            '/'
            . preg_quote($marca, '/')
            . '\s*'
            . preg_quote($descartado, '/')
            . '(?!\p{L})/iu';

        $textoAutocorreccionSemantica = preg_replace(
            $patronCorreccionLocal,
            $elegido,
            $textoAutocorreccionSemantica,
            1
        ) ?? $textoAutocorreccionSemantica;
    }

    /*
     * Seguridad para cualquier marca que no haya podido reemplazarse.
     */
    $textoAutocorreccionSemantica = preg_replace(
        '/__VMORG\d+__/u',
        '',
        $textoAutocorreccionSemantica
    ) ?? $textoAutocorreccionSemantica;

    $patronAutocorreccionSemantica = '/(?:'
        . '\bperd[oó]n\b'
        . '|\bcorrijo\b'
        . '|\bmejor\s+dicho\b'
        . '|\bquise\s+decir\b'
        . '|\bno\s*,?\s*(?:era|es)\b'
        . '|\bno\s+'
        . '(?!se\b|hay\b|observa\b|visualiza\b|evidencia\b|presenta\b)'
        . '[^.!?]{1,80}?'
        . '\s*,?\s*(?:es|era)\b'
        . ')/iu';

    preg_match_all(
        $patronAutocorreccionSemantica,
        $textoAutocorreccionSemantica,
        $coincidenciasAutocorreccionSemantica,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    foreach ($coincidenciasAutocorreccionSemantica as $m) {
        $evidenciaMarca = trim((string)($m[0][0] ?? ''));
        $offsetBytes = (int)($m[0][1] ?? -1);

        if ($evidenciaMarca === '' || $offsetBytes < 0) {
            continue;
        }

        /*
         * Recuperar órgano/lateralidad anterior mediante la misma lógica
         * que ya usamos para las autocorrecciones numéricas.
         */
        $contextoAnatomico =
            $resolverContextoAutocorreccionSemantica(
                $textoAutocorreccionSemantica,
                $offsetBytes
            );

        /*
         * Convertir el offset en bytes de preg_match a posición UTF-8.
         */
        $posCaracter = mb_strlen(
            substr(
                $textoAutocorreccionSemantica,
                0,
                $offsetBytes
            ),
            'UTF-8'
        );

        /*
         * Mandar suficiente texto antes y después para que GPT pueda
         * distinguir, por ejemplo:
         *
         *   "ecogenicidad aumentada... no aumentada, es mixta"
         *
         * de:
         *
         *   "Estómago aumentado. Perdón, el colon está aumentado."
         *
         * Sin enviar otra vez el informe completo.
         */
        $inicioContexto = max(0, $posCaracter - 350);

        $contextoSemantico = trim(
            mb_substr(
                $textoAutocorreccionSemantica,
                $inicioContexto,
                700,
                'UTF-8'
            )
        );

        $autocorreccionesCandidatas[] = [
            'organo_contexto' => $contextoAnatomico['organo'] ?? null,
            'lateralidad_contexto' => $contextoAnatomico['lateralidad'] ?? null,
            'marcador' => $evidenciaMarca,
            'contexto' => $contextoSemantico,
            'estado' => 'requiere_interpretacion_generador'
        ];
    }

    // Localizar cada diferencia dentro de su transcripción original.
    $buscarContexto = static function (
        string $textoFuente,
        string $termino,
        int $ocurrencia = 0,
        ?int $indiceToken = null,
        array $correccionesOrganoLocalesFuente = []
    ) use (
        $patronOrgano,
        $normalizarOrganoDetectado,
        $normalizarClaveAlias
    ): array {
        $termino = trim($termino, " \t\n\r\0\x0B.,;:");

        if ($textoFuente === '') {
            return [];
        }

        // Reproducir la misma normalización básica usada por el comparador.
        $textoTrabajo = preg_replace(
            '/(\d)\s*([.,])\s*(\d)/u',
            '$1$2$3',
            $textoFuente
        ) ?? $textoFuente;

        $textoTrabajo = preg_replace(
            '/\s+/u',
            ' ',
            trim($textoTrabajo)
        ) ?? trim($textoTrabajo);

        $pos = null;

        /**
         * Camino principal: usar la posición exacta entregada por cmp_comparar().
         */
        $pos = null;

        /*
        * Misma tokenización usada por cmp_comparar().
        * Además la reutilizamos para ubicar correcciones locales de órgano.
        */
        preg_match_all(
            '/\S+/u',
            $textoTrabajo,
            $tokensTrabajo,
            PREG_OFFSET_CAPTURE
        );

        /**
         * Camino principal: usar la posición exacta entregada por cmp_comparar().
         */
        if (
            $indiceToken !== null
            && $indiceToken >= 0
            && isset($tokensTrabajo[0][$indiceToken])
        ) {
            $byteOffset = (int)$tokensTrabajo[0][$indiceToken][1];

            $pos = mb_strlen(
                substr($textoTrabajo, 0, $byteOffset),
                'UTF-8'
            );
        }

        /**
         * Compatibilidad con registros antiguos sin indice_a / indice_b.
         */
        if ($pos === null) {
            if ($termino === '') {
                return [];
            }

            $esNumeroEntero = preg_match('/^\d+$/u', $termino) === 1;

            if ($esNumeroEntero) {
                $patron = '/(?<![\p{L}\d.,])'
                    . preg_quote($termino, '/')
                    . '(?![\p{L}\d])'
                    . '(?![.,]\d)/iu';
            } else {
                $patron = '/(?<![\p{L}\d])'
                    . preg_quote($termino, '/')
                    . '(?![\p{L}\d])/iu';
            }

            if (!preg_match_all(
                $patron,
                $textoTrabajo,
                $coincidenciasTermino,
                PREG_OFFSET_CAPTURE
            )) {
                return [];
            }

            if (!isset($coincidenciasTermino[0][$ocurrencia])) {
                return [];
            }

            $coincidencia = $coincidenciasTermino[0][$ocurrencia];
            $offsetCoincidencia = (int)$coincidencia[1];

            $pos = mb_strlen(
                substr($textoTrabajo, 0, $offsetCoincidencia),
                'UTF-8'
            );
        }

        /**
         * Si el propio punto de discrepancia comienza por un órgano,
         * esa referencia es más fuerte que cualquier órgano anterior.
         *
         * Ejemplo:
         * "Colon con contenido fecal" vs "Coloncocontinúo..."
         */
        $desdePosicion = mb_substr(
            $textoTrabajo,
            $pos,
            140,
            'UTF-8'
        );

        $patronOrganoActual =
            '/^(?:(?:el|la|los|las)\s+)?'
            . '(' . $patronOrgano . ')'
            . '(?:\s+(izquierd[oa]|derech[oa]))?'
            . '(?!\p{L})/iu';

        if (preg_match($patronOrganoActual, $desdePosicion, $actual)) {
            return [
                'organo' => $normalizarOrganoDetectado($actual[1]),
                'lateralidad' => !empty($actual[2])
                    ? mb_strtolower($actual[2], 'UTF-8')
                    : null,
                'distancia_organo' => 0,
                'contexto' => trim(mb_substr(
                    $textoTrabajo,
                    max(0, $pos - 90),
                    220,
                    'UTF-8'
                )),
                'continuacion' => mb_substr(
                    $textoTrabajo,
                    $pos,
                    60,
                    'UTF-8'
                )
            ];
        }

        $inicioAnterior = max(0, $pos - 350);

        $anterior = mb_substr(
            $textoTrabajo,
            $inicioAnterior,
            min(350, $pos),
            'UTF-8'
        );

        preg_match_all(
            '/(?<!\p{L})(' . $patronOrgano . ')'
            . '(?:\s+(izquierd[oa]|derech[oa]))?(?!\p{L})/iu',
            $anterior,
            $organos,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );







        
        $ultimo = !empty($organos)
            ? $organos[array_key_last($organos)]
            : null;

        /*
        * Candidato escrito literalmente en la transcripción.
        */
        $organoRaw = null;
        $lateralidadRaw = null;
        $posOrganoRaw = null;

        if ($ultimo !== null) {
            $offsetOrgano = (int)$ultimo[0][1];

            $posOrganoLocal = mb_strlen(
                substr($anterior, 0, $offsetOrgano),
                'UTF-8'
            );

            $posOrganoRaw = $inicioAnterior + $posOrganoLocal;

            $organoRaw = $normalizarOrganoDetectado(
                $ultimo[1][0]
            );

            $lateralidadRaw = (
                isset($ultimo[2][0])
                && $ultimo[2][0] !== ''
            )
                ? mb_strtolower($ultimo[2][0], 'UTF-8')
                : null;
        }

        /*
        * Candidato procedente de una corrección STT LOCALIZADA.
        *
        * Ejemplo:
        *   Motor A: "Vaso ..."
        *   corrección en indice_a: Vaso -> Bazo
        *
        * Si una discrepancia posterior aparece dentro de ese bloque,
        * Bazo debe competir como contexto anatómico con los órganos
        * escritos literalmente.
        */
        $organoLocal = null;
        $lateralidadLocal = null;
        $posOrganoCorregido = null;

        foreach ($correccionesOrganoLocalesFuente as $correccionLocal) {
            $indiceLocal = (int)(
                $correccionLocal['indice_token'] ?? -1
            );

            if (
                $indiceLocal < 0
                || !isset($tokensTrabajo[0][$indiceLocal])
            ) {
                continue;
            }

            $tokenLocal = (string)$tokensTrabajo[0][$indiceLocal][0];

            /*
            * Seguridad: la corrección solo vale si en esa posición
            * realmente está el término descartado.
            */
            if (
                $normalizarClaveAlias($tokenLocal)
                !== $normalizarClaveAlias(
                    (string)($correccionLocal['descartado'] ?? '')
                )
            ) {
                continue;
            }

            $byteOffsetLocal = (int)$tokensTrabajo[0][$indiceLocal][1];

            $posLocal = mb_strlen(
                substr($textoTrabajo, 0, $byteOffsetLocal),
                'UTF-8'
            );

            /*
            * Solo sirven órganos anteriores o coincidentes con
            * el punto donde comienza la discrepancia.
            */
            if ($posLocal > $pos) {
                continue;
            }

            /*
            * Mantener el mismo alcance máximo aproximado que
            * la búsqueda histórica hacia atrás.
            */
            if (($pos - $posLocal) > 350) {
                continue;
            }

            if (
                $posOrganoCorregido !== null
                && $posLocal <= $posOrganoCorregido
            ) {
                continue;
            }

            $organoLocal = trim(
                (string)($correccionLocal['elegido'] ?? '')
            );

            if ($organoLocal === '') {
                continue;
            }

            $posOrganoCorregido = $posLocal;
            $lateralidadLocal = null;

            /*
            * Si la lateralidad viene inmediatamente después
            * del término corregido, conservarla.
            */
            if (isset($tokensTrabajo[0][$indiceLocal + 1])) {
                $tokenSiguiente = trim(
                    (string)$tokensTrabajo[0][$indiceLocal + 1][0],
                    " .,;:"
                );

                if (
                    preg_match(
                        '/^(izquierd[oa]|derech[oa])$/iu',
                        $tokenSiguiente,
                        $mLateralidad
                    )
                ) {
                    $lateralidadLocal = mb_strtolower(
                        $mLateralidad[1],
                        'UTF-8'
                    );
                }
            }
        }

        /*
        * Elegir el candidato anatómico realmente más cercano.
        *
        * Si ambos ocupan exactamente la misma posición, la corrección
        * STT localizada gana sobre el término crudo.
        */
        $usarCorreccionLocal =
            $posOrganoCorregido !== null
            && (
                $posOrganoRaw === null
                || $posOrganoCorregido >= $posOrganoRaw
            );

        if ($usarCorreccionLocal) {
            $organoFinal = $organoLocal;
            $lateralidadFinal = $lateralidadLocal;
            $posOrganoFinal = $posOrganoCorregido;
        } else {
            $organoFinal = $organoRaw;
            $lateralidadFinal = $lateralidadRaw;
            $posOrganoFinal = $posOrganoRaw;
        }

        $distanciaOrgano = $posOrganoFinal !== null
            ? max(0, $pos - $posOrganoFinal)
            : null;

        return [
            'organo' => $organoFinal,
            'lateralidad' => $lateralidadFinal,
            'distancia_organo' => $distanciaOrgano,
            'contexto' => trim(mb_substr(
                $textoTrabajo,
                max(0, $pos - 90),
                220,
                'UTF-8'
            )),
            'continuacion' => mb_substr(
                $textoTrabajo,
                $pos,
                60,
                'UTF-8'
            )
        ];
    };

    $discrepancias = [];
    $textoA = (string)($origen['texto_a'] ?? '');
    $textoB = (string)($origen['texto_b'] ?? '');

    $usosTerminoA = [];
    $usosTerminoB = [];

    foreach (($origen['discrepancias'] ?? []) as $d) {
        $a = trim((string)($d['a'] ?? ''));
        $b = trim((string)($d['b'] ?? ''));
        $tipo = (string)($d['tipo'] ?? 'cambio');

        $indiceA = array_key_exists('indice_a', $d)
            && $d['indice_a'] !== null
                ? (int)$d['indice_a']
                : null;

        $indiceB = array_key_exists('indice_b', $d)
            && $d['indice_b'] !== null
                ? (int)$d['indice_b']
                : null;

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

        $claveA = $normalizar($a);
        $claveB = $normalizar($b);

        $ocurrenciaA = 0;
        $ocurrenciaB = 0;

        if ($claveA !== '') {
            $ocurrenciaA = $usosTerminoA[$claveA] ?? 0;
            $usosTerminoA[$claveA] = $ocurrenciaA + 1;
        }

        if ($claveB !== '') {
            $ocurrenciaB = $usosTerminoB[$claveB] ?? 0;
            $usosTerminoB[$claveB] = $ocurrenciaB + 1;
        }

        $contextoA = $buscarContexto(
            $textoA,
            $a,
            $ocurrenciaA,
            $indiceA,
            $correccionesOrganoContextoA
        );

        $contextoB = $buscarContexto(
            $textoB,
            $b,
            $ocurrenciaB,
            $indiceB,
            $correccionesOrganoContextoB
        );

        /**
         * Elegir la referencia anatómica más cercana a la discrepancia.
         * Si ambas son igual de cercanas pero apuntan a órganos distintos,
         * no adjudicar silenciosamente.
         */
        $tieneA = !empty($contextoA['organo']);
        $tieneB = !empty($contextoB['organo']);

        if ($tieneA && !$tieneB) {
            $contexto = $contextoA;
        } elseif ($tieneB && !$tieneA) {
            $contexto = $contextoB;
        } elseif ($tieneA && $tieneB) {
            $distanciaA = $contextoA['distancia_organo'] ?? PHP_INT_MAX;
            $distanciaB = $contextoB['distancia_organo'] ?? PHP_INT_MAX;

            if ($distanciaA < $distanciaB) {
                $contexto = $contextoA;
            } elseif ($distanciaB < $distanciaA) {
                $contexto = $contextoB;
            } else {
                $mismoOrgano = mb_strtolower(
                    (string)$contextoA['organo'],
                    'UTF-8'
                ) === mb_strtolower(
                    (string)$contextoB['organo'],
                    'UTF-8'
                );

                $mismaLateralidad =
                    ($contextoA['lateralidad'] ?? null)
                    === ($contextoB['lateralidad'] ?? null);

                $contexto = ($mismoOrgano && $mismaLateralidad)
                    ? $contextoA
                    : [];
            }
        } else {
            $contexto = [];
        }

        $organo = $contexto['organo'] ?? null;
        $lateralidad = $contexto['lateralidad'] ?? null;

        $prioridad = 'media';
        $atributo = null;

        $textoAlternativas = $a . ' ' . $b;
        $esRinon = $organo !== null
            && preg_match(
                '/(?<!\p{L})ri(?:ñ|n)[oó]n(?!\p{L})/iu',
                (string)$organo
            );

        if (
            $esRinon
            && preg_match(
                '/(?<!\p{L})relaci[oó]n(?!\p{L})/iu',
                $textoAlternativas
            )
            && preg_match(
                '/(?<!\p{L})(?:aumentad[oa]|disminuid[oa]|conservad[oa])(?!\p{L})/iu',
                $textoAlternativas
            )
        ) {
            $prioridad = 'alta';
            $atributo = 'relacion_cortico_medular';
        }

        if ($tipo === 'numero') {
            $continuacion = (string)($contexto['continuacion'] ?? '');

            $hayDecimal = (bool)preg_match(
                '/\d+[.,]\d+/u',
                $a . ' ' . $b
            );

            $hayUnidad = (bool)preg_match(
                '/^\s*\d+(?:[.,]\d+)?[\s,;:]*'
                . '(?:cm|mm|cent[ií]metros?|mil[ií]metros?)\b/iu',
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
            } elseif ($atributo === 'relacion_cortico_medular') {
                $alertas[] = [
                    'tipo' => 'atributo_clinico_discrepante',
                    'organo' => $organo,
                    'lateralidad' => $lateralidad,
                    'atributo' => $atributo,
                    'alternativas' => [$a, $b],
                    'detalle' => 'No usar el valor de la plantilla hasta resolver esta discrepancia clínica.'
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
        'autocorrecciones_candidatas' => $autocorreccionesCandidatas,
        'referencias_entre_organos' => $referenciasEntreOrganos,
        'discrepancias' => $discrepancias,
        'alertas' => $alertas
    ];
}
