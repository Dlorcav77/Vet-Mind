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

    /*
     * Fronteras anatómicas dudosas.
     *
     * Una discrepancia puede contener un órgano válido en Motor B,
     * pero una forma corrupta todavía no resuelta en Motor A.
     *
     * En ese caso NO elegimos el órgano desde PHP.
     * Solo impedimos que el fragmento quede absorbido por el órgano
     * activo anterior.
     */
    $fronterasOrganoDudosas = [];

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
         * Una corrección STT puede haberse resuelto por la familia
         * "organo" o por la familia "concepto".
         *
         * No confiamos únicamente en esa etiqueta interna:
         * si el término ELEGIDO corresponde exactamente a un órgano
         * reconocido por el catálogo anatómico, puede participar en
         * la segmentación anatómica.
         *
         * Esto permite, por ejemplo:
         *   "y el guion" -> "Yeyuno"
         * aunque el validador la haya marcado como origen "concepto".
         *
         * Una corrección conceptual no anatómica sigue quedando fuera.
         */
        $esCorreccionAnatomica = preg_match(
            '/^(?:' . $patronOrgano . ')$/iu',
            $elegido
        ) === 1;

        if (!$esCorreccionAnatomica) {
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

        /*
     * Detectar discrepancias pendientes donde Motor B contiene
     * inequívocamente un órgano reconocido, mientras Motor A no.
     *
     * Ejemplos:
     *   yeignum        / Yeyuno
     *   2 deno         / Duodeno
     *   Riña derecha   / Riñón derecho
     *   Adrenalina     / Adrenal
     *
     * Esto NO resuelve la discrepancia.
     * Solo crea una frontera estructural en Motor A.
     */
    $extraerOrganoAlternativa = static function (
        string $valor
    ) use ($patronOrgano): ?string {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        if (!preg_match(
            '/^(?:(?:el|la|los|las)\s+)?'
            . '(' . $patronOrgano . ')'
            . '(?:\s+(?:izquierd[oa]|derech[oa]))?'
            . '[\s,.;:]*$/iu',
            $valor,
            $m
        )) {
            return null;
        }

        return $m[1] ?? null;
    };

    foreach (($origen['discrepancias'] ?? []) as $discrepancia) {
        if (!is_array($discrepancia)) {
            continue;
        }

        $indiceA = (
            array_key_exists('indice_a', $discrepancia)
            && $discrepancia['indice_a'] !== null
        )
            ? (int)$discrepancia['indice_a']
            : null;

        if ($indiceA === null) {
            continue;
        }

        $alternativaA = trim(
            (string)($discrepancia['a'] ?? '')
        );

        $alternativaB = trim(
            (string)($discrepancia['b'] ?? '')
        );

        if ($alternativaA === '' || $alternativaB === '') {
            continue;
        }

        $organoA = $extraerOrganoAlternativa($alternativaA);
        $organoB = $extraerOrganoAlternativa($alternativaB);

        /*
         * Solo necesitamos esta frontera cuando Motor A NO reconoce
         * el órgano y Motor B sí.
         *
         * Si Motor A ya contiene un órgano válido, la segmentación
         * normal puede detectarlo.
         */
        if ($organoA !== null || $organoB === null) {
            continue;
        }

        $fronterasOrganoDudosas[] = [
            'indice_a' => $indiceA,
            'texto_a' => $alternativaA,
            'organo_alternativo' => $organoB,
        ];
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

            /*
             * La discrepancia resuelta puede ocupar uno o varios tokens.
             *
             * Ejemplos:
             *   "Vaso"       -> Bazo
             *   "y el guion" -> Yeyuno
             *
             * indice_a apunta al PRIMER token de la discrepancia.
             * Validamos el tramo completo antes de insertar la marca.
             */
            $descartadoLocal = trim(
                (string)($correccionLocal['descartado'] ?? '')
            );

            if ($descartadoLocal === '') {
                continue;
            }

            preg_match_all(
                '/\S+/u',
                $descartadoLocal,
                $tokensDescartadoLocal
            );

            $cantidadTokensDescartado = count(
                $tokensDescartadoLocal[0] ?? []
            );

            if ($cantidadTokensDescartado < 1) {
                continue;
            }

            $ultimoIndiceDescartado =
                $indice + $cantidadTokensDescartado - 1;

            if (!isset($tokensAnalisis[0][$ultimoIndiceDescartado])) {
                continue;
            }

            $tramoTokens = [];

            for (
                $i = $indice;
                $i <= $ultimoIndiceDescartado;
                $i++
            ) {
                $tramoTokens[] =
                    (string)$tokensAnalisis[0][$i][0];
            }

            $tramoTexto = implode(' ', $tramoTokens);

            /*
             * Seguridad: el tramo situado desde indice_a debe corresponder
             * exactamente al término descartado de esta corrección.
             */
            if (
                $normalizarClaveAlias($tramoTexto)
                !== $normalizarClaveAlias($descartadoLocal)
            ) {
                continue;
            }

            $offset = (int)$tokensAnalisis[0][$indice][1];

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

        /*
     * Aplicar fronteras anatómicas pendientes.
     *
     * Solo insertamos un salto de línea.
     * No añadimos __VMORG porque el órgano todavía no fue resuelto.
     */
    if (!empty($fronterasOrganoDudosas)) {
        preg_match_all(
            '/\S+/u',
            $textoAnalisis,
            $tokensFrontera,
            PREG_OFFSET_CAPTURE
        );

        $insercionesFrontera = [];

        foreach ($fronterasOrganoDudosas as $frontera) {
            $indice = $frontera['indice_a'];

            if (!isset($tokensFrontera[0][$indice])) {
                continue;
            }

            $textoAlternativa = trim(
                (string)$frontera['texto_a']
            );

            preg_match_all(
                '/\S+/u',
                $textoAlternativa,
                $tokensAlternativa
            );

            $cantidadTokens = count(
                $tokensAlternativa[0] ?? []
            );

            if ($cantidadTokens < 1) {
                continue;
            }

            $ultimoIndice =
                $indice + $cantidadTokens - 1;

            if (!isset($tokensFrontera[0][$ultimoIndice])) {
                continue;
            }

            $tramo = [];

            for ($i = $indice; $i <= $ultimoIndice; $i++) {
                $tramo[] =
                    (string)$tokensFrontera[0][$i][0];
            }

            if (
                $normalizarClaveAlias(implode(' ', $tramo))
                !== $normalizarClaveAlias($textoAlternativa)
            ) {
                continue;
            }

            /*
             * Un órgano mencionado como complemento o referencia
             * no representa necesariamente el inicio de un nuevo bloque.
             *
             * Ejemplos:
             *   "proceso ... del linfonódulo"
             *   "compatible con linfonódulo"
             *   "respecto al bazo"
             *
             * En esos casos conservamos la frase completa.
             */
            $tokenAnterior = '';

            if (
                $indice > 0
                && isset($tokensFrontera[0][$indice - 1])
            ) {
                $tokenAnterior = $normalizarClaveAlias(
                    (string)$tokensFrontera[0][$indice - 1][0]
                );
            }

            if (
                in_array(
                    $tokenAnterior,
                    [
                        'de',
                        'del',
                        'al',
                        'con',
                        'respecto',
                        'hacia',
                        'sobre',
                    ],
                    true
                )
            ) {
                continue;
            }

            $insercionesFrontera[] =
                (int)$tokensFrontera[0][$indice][1];
        }

        rsort($insercionesFrontera);

        foreach ($insercionesFrontera as $offset) {
            $textoAnalisis =
                substr($textoAnalisis, 0, $offset)
                . "\n"
                . substr($textoAnalisis, $offset);
        }
    }

    /*
     * "vaso conservado" puede corresponder a una estructura independiente
     * o a una deformación STT todavía no resuelta.
     *
     * No debe heredarse automáticamente por el órgano anterior.
     * Aquí solo creamos una frontera; NO lo convertimos en Bazo.
     */
    $textoAnalisis = preg_replace(
        '/,\s*(?=vasos?\s+conservad[oa]s?\b)/iu',
        ",\n",
        $textoAnalisis
    ) ?? $textoAnalisis;

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

        /*
        * Hallazgos generales abdominales/peritoneales no pertenecen
        * automáticamente al último órgano descrito.
        *
        * Incluimos tanto afirmaciones como negaciones:
        *
        *   "se observa derrame peritoneal"
        *   "no se observa derrame peritoneal"
        *   "no se visualiza líquido libre"
        *   "no se evidencia masa abdominal"
        *
        * Esta función solo corta continuidad anatómica.
        * No interpreta si el hallazgo está presente o ausente.
        */
        $prefijoObservacion =
            '(?:'
            . '(?:no\s+)?'
            . '(?:se\s+)?'
            . '(?:observan?|visualizan?|evidencian?|identifican?|aprecian?)'
            . '\s+'
            . ')?';

        return (bool)preg_match(
            '/^'
            . $prefijoObservacion
            . '(?:'

            // Hallazgos peritoneales generales.
            . 'neumoperitone\p{L}*'
            . '|derrame\s+peritone\p{L}*'
            . '|l[ií]quido\s+libre\b'
            . '|efusi[oó]n\s+peritone\p{L}*'

            // Masa / lesión abdominal expresada directamente.
            . '|(?:una?\s+)?'
            . '(?:masa|masas|lesi[oó]n|lesiones|estructura|estructuras|n[oó]dulo|n[oó]dulos)'
            . '\s+abdominal(?:es)?\b'

            // Masa / lesión localizada en cavidad abdominal.
            . '|(?:una?\s+)?'
            . '(?:masa|masas|lesi[oó]n|lesiones|estructura|estructuras|n[oó]dulo|n[oó]dulos)\b'
            . '[^.!?;]{0,80}'
            . '\ben\s+(?:la\s+)?cavidad\s+abdominal\b'

            // Mención general de cavidad abdominal.
            . '|(?:hallazgos?\s+(?:en\s+)?)?'
            . '(?:la\s+)?cavidad\s+abdominal\b'

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
            . '|patr[oó]n\s+(?:mucoso|gaseoso|l[ií]quido|alimenticio)\b'
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
            . '|polo\s+(?:craneal|caudal)\b'
            . '|lesi[oó]n(?:es)?\s+renal(?:es)?\b'
            . '|(?:la\s+)?capa\s+(?:muscular|submucosa|mucosa)\b'
            . '|con\s+predominio\s+de\s+la\s+capa\b'
            . '|(?:y\s+)?la\s+(?:segunda|tercera|cuarta)\s+estructura\b'
            . '|(?:ah|ay),\s*(?:no,\s*)?perd[oó]n\b'
            . ')/iu',
            $fragmento
        );
    };

    /*
     * Una frase puede parecer sintácticamente una continuación,
     * pero contener evidencia fuerte incompatible con el órgano activo.
     *
     * Esta función NO intenta descubrir el órgano correcto.
     * Solo evita herencias anatómicas evidentemente inseguras.
     *
     * Ante incompatibilidad se prefiere dejar el fragmento sin asignar.
     */
    $esContinuacionCompatibleConOrgano = static function (
        ?string $organo,
        string $fragmento
    ): bool {
        if ($organo === null || trim($organo) === '') {
            return false;
        }

        $fragmento = trim($fragmento);
        $organoNormalizado = mb_strtolower(
            trim($organo),
            'UTF-8'
        );

        /*
         * Familias mínimas.
         *
         * No se pretende construir aquí una ontología clínica.
         * Solo distinguir contextos con marcadores suficientemente fuertes.
         */
        $esGastrointestinal = preg_match(
            '/(?:'
            . 'est[oó]mago'
            . '|duodeno'
            . '|yeyuno'
            . '|[ií]leon'
            . '|ciego'
            . '|col[oó]n'
            . ')/iu',
            $organoNormalizado
        ) === 1;

        $esRenal = preg_match(
            '/ri(?:ñ|n)[oó]n/iu',
            $organoNormalizado
        ) === 1;

        $esLinfonodal = preg_match(
            '/linfon/iu',
            $organoNormalizado
        ) === 1;

        /*
         * Marcadores gastrointestinales fuertes.
         *
         * "pared", "grosor" y "contenido" NO se incluyen porque pueden
         * describir otras estructuras y no bastan para cortar contexto.
         */
        $hayContextoGastrointestinalFuerte = preg_match(
            '/(?:'
            . '\bmotilidad\b'
            . '|\bestratificaci[oó]n\b'
            . '|\bpatr[oó]n\s+(?:mucoso|gaseoso|l[ií]quido|alimenticio)\b'
            . '|\b(?:la\s+)?capa\s+(?:muscular|submucosa|mucosa)\b'
            . '|\bcon\s+predominio\s+de\s+la\s+capa\b'
            . ')/iu',
            $fragmento
        ) === 1;

        if (
            $hayContextoGastrointestinalFuerte
            && !$esGastrointestinal
        ) {
            return false;
        }

        /*
         * Marcadores renales suficientemente específicos.
         *
         * No usamos palabras genéricas como "medular" o "cortical"
         * aisladas.
         */
        $hayContextoRenalFuerte = preg_match(
            '/(?:'
            . '\bpelvis(?:\s+renal)?\b'
            . '|\b(?:l[ií]mite|relaci[oó]n)\s+'
            . 'c[oó]rtico[\s-]*medular\b'
            . '|\blesi[oó]n(?:es)?\s+renal(?:es)?\b'
            . ')/iu',
            $fragmento
        ) === 1;

        if (
            $hayContextoRenalFuerte
            && !$esRenal
        ) {
            return false;
        }

        /*
         * Una lesión descrita explícitamente como compatible con
         * linfonódulo no debe quedar pegada automáticamente al órgano
         * anterior.
         */
        $hayContextoLinfonodalFuerte = preg_match(
            '/\bcompatible\s+con\s+'
            . '(?:(?:un|una)\s+)?'
            . 'linfon[oó]dul[oa]|'
            . '\bcompatible\s+con\s+'
            . '(?:(?:un|una)\s+)?'
            . 'linfonod[oa]\b/iu',
            $fragmento
        ) === 1;

        if (
            $hayContextoLinfonodalFuerte
            && !$esLinfonodal
        ) {
            return false;
        }

        return true;
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

        /*
        * Para decidir si un fragmento mezcla órganos distintos,
        * ignoramos órganos usados únicamente como referencia comparativa.
        *
        * Ejemplo:
        *   "Hígado ... ecogenicidad disminuida respecto al Bazo"
        *
        * El sujeto clínico sigue siendo Hígado; Bazo es solo referencia.
        * El texto original del hallazgo NO se modifica.
        */
        $fragmentoParaContextosOrgano = preg_replace(
            '/\b(?:'
            . 'respecto\s+(?:a|al)'
            . '|en\s+comparaci[oó]n\s+con'
            . '|comparad[oa]s?\s+con'
            . ')\s+'
            . '(?:(?:el|la|los|las)\s+)?'
            . '(?:' . $patronOrgano . ')'
            . '(?:\s+(?:izquierd[oa]|derech[oa]))?'
            . '(?!\p{L})/iu',
            '__VMREFORG__',
            $fragmento
        ) ?? $fragmento;

        // Si aparecen varios órganos DISTINTOS, no atribuir todo a uno solo.
        preg_match_all(
            '/(?<!\p{L})(?<organo>' . $patronOrgano . ')'
            . '(?:\s+(?<lateralidad>izquierd[oa]|derech[oa]))?'
            . '(?!\p{L})/iu',
            $fragmentoParaContextosOrgano,
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
            && $esContinuacionCompatibleConOrgano(
                $organoActivo,
                $fragmento
            )
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
                '/\bmismas?\s+caracter[ií]sticas?\s+que\s+(?:el|la)\s+'
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
                '/\bmismas?\s+caracter[ií]sticas?\s+que\s+(?:el|la)\s+'
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
    ) use (
        $patronOrgano,
        $normalizarOrganoDetectado,
        $esHallazgoCavitarioIndependiente
    ): array {
        $textoAnteriorBytes = substr(
            $textoFuente,
            0,
            $offset
        );

        /*
        * Fragmento clínico inmediato donde aparece el valor corregido.
        */
        $fragmentosHastaValor = preg_split(
            '/(?<=[.!?;])\s+|\R+/u',
            $textoAnteriorBytes,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $fragmentoActual = !empty($fragmentosHastaValor)
            ? trim(
                (string)$fragmentosHastaValor[
                    array_key_last($fragmentosHastaValor)
                ]
            )
            : '';

        $organo = null;
        $lateralidad = null;

        /*
        * 1. Si el propio bloque actual comienza con un órgano,
        * ese contexto es más fuerte que cualquier término anatómico
        * mencionado después dentro de la misma descripción.
        *
        * Ejemplo:
        *   "Páncreas rama derecha ... peritoneo reactivo ...
        *    grosor 0.8, no era 1.4"
        *
        * El contexto sigue siendo Páncreas.
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
            $organo = $normalizarOrganoDetectado(
                $mActual[1]
            );

            $lateralidad = !empty($mActual[2])
                ? mb_strtolower(
                    $mActual[2],
                    'UTF-8'
                )
                : null;
        }

        /*
        * 2. Un hallazgo cavitario independiente nunca debe heredar
        * el órgano previo.
        */
        if (
            $fragmentoActual !== ''
            && $esHallazgoCavitarioIndependiente(
                $fragmentoActual
            )
        ) {
            $organo = null;
            $lateralidad = null;
        } elseif ($organo === null) {
            /*
            * 3. Fallback: último órgano cercano hacia atrás.
            *
            * Se mantiene para continuaciones legítimas como:
            *
            *   "Estómago aumentado. Grosor 0.23..., no era 0.33..."
            */
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
                ? $normalizarOrganoDetectado(
                    $ultimoOrgano[1]
                )
                : null;

            $lateralidad = (
                $ultimoOrgano
                && !empty($ultimoOrgano[2])
            )
                ? mb_strtolower(
                    $ultimoOrgano[2],
                    'UTF-8'
                )
                : null;
        }

        /*
        * Determinar el atributo usando solamente el fragmento clínico
        * donde aparece el valor corregido.
        *
        * No buscamos en frases anteriores porque podrían contener atributos
        * legítimos de otro dato del mismo órgano.
        *
        * Si hay varios candidatos dentro del fragmento, gana el mencionado
        * más cerca del valor que inicia la autocorrección.
        */
        $ventanaAtributo = $fragmentoActual !== ''
            ? mb_substr(
                $fragmentoActual,
                -140,
                null,
                'UTF-8'
            )
            : '';

        $atributo = 'medida';
        $mejorOffsetAtributo = -1;

        $candidatosAtributo = [
            'polo_caudal' => '/\bpolo\s+caudal\b/iu',
            'polo_craneal' => '/\bpolo\s+craneal\b/iu',
            'grosor' => '/\bgrosor\b/iu',
            'pared' => '/\bpared\b/iu',
            'tamaño' => '/\btamañ[oa]\b/iu',
        ];

        foreach ($candidatosAtributo as $nombreAtributo => $patronAtributo) {
            preg_match_all(
                $patronAtributo,
                $ventanaAtributo,
                $coincidenciasAtributo,
                PREG_OFFSET_CAPTURE
            );

            if (empty($coincidenciasAtributo[0])) {
                continue;
            }

            $ultimaCoincidencia = $coincidenciasAtributo[0][
                array_key_last($coincidenciasAtributo[0])
            ];

            $offsetAtributo = (int)$ultimaCoincidencia[1];

            if ($offsetAtributo > $mejorOffsetAtributo) {
                $mejorOffsetAtributo = $offsetAtributo;
                $atributo = $nombreAtributo;
            }
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
        $normalizarClaveAlias,
        $esHallazgoCavitarioIndependiente,
        $origen
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
         * Si el término posterior al marcador no es reconocido como órgano,
         * permitir que una discrepancia A/B LOCAL confirme su identidad.
         *
         * Ejemplo Rocko:
         *   Motor A: "Perdón, la adrenalina izquierda..."
         *   Motor B:              "adrenal izquierda..."
         *
         * No creamos alias global "adrenalina -> adrenal".
         * La equivalencia vale únicamente porque existe evidencia A/B
         * dentro de este mismo caso.
         */
        $patronTerminoLateralizadoDespuesCorreccion =
            '/^(?:'
            . 'perd[oó]n'
            . '|corrijo'
            . '|mejor\s+dicho'
            . '|quise\s+decir'
            . ')\b'
            . '[\s,;:\-]*'
            . '(?:(?:el|la|los|las)\s+)?'
            . '(\p{L}+)'
            . '\s+(izquierd[oa]|derech[oa])'
            . '(?!\p{L})/iu';

        if (
            preg_match(
                $patronTerminoLateralizadoDespuesCorreccion,
                $desdeMarcador,
                $mTerminoPosterior
            )
        ) {
            $terminoPosterior = trim(
                (string)$mTerminoPosterior[1]
            );

            $lateralidadPosterior = mb_strtolower(
                (string)$mTerminoPosterior[2],
                'UTF-8'
            );

            foreach (($origen['discrepancias'] ?? []) as $d) {
                if (!is_array($d)) {
                    continue;
                }

                $valorA = trim(
                    (string)($d['a'] ?? ''),
                    " \t\n\r\0\x0B.,;:"
                );

                $valorB = trim(
                    (string)($d['b'] ?? ''),
                    " \t\n\r\0\x0B.,;:"
                );

                $otraAlternativa = null;

                if (
                    $normalizarClaveAlias($valorA)
                    === $normalizarClaveAlias($terminoPosterior)
                ) {
                    $otraAlternativa = $valorB;
                } elseif (
                    $normalizarClaveAlias($valorB)
                    === $normalizarClaveAlias($terminoPosterior)
                ) {
                    $otraAlternativa = $valorA;
                }

                if (
                    $otraAlternativa === null
                    || $otraAlternativa === ''
                ) {
                    continue;
                }

                if (
                    !preg_match(
                        '/^(?:(?:el|la|los|las)\s+)?'
                        . '(' . $patronOrgano . ')'
                        . '(?!\p{L})$/iu',
                        $otraAlternativa,
                        $mOrganoAlternativo
                    )
                ) {
                    continue;
                }

                $contexto['organo'] =
                    $normalizarOrganoDetectado(
                        $mOrganoAlternativo[1]
                    );

                $contexto['lateralidad'] =
                    $lateralidadPosterior;

                return $contexto;
            }
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
    $patronAutocorreccionNumericaDirecta =
        '/(?P<anterior>\d+(?:[.,]\d+)?)'
        . '\s*(?:cm|mm|cent[ií]metros?|mil[ií]metros?)?'
        . '[\s,.;:…-]{0,15}'
        . '\bno\s*,?\s*(?:era|es)\s+'
        . '(?P<corregido>\d+(?:[.,]\d+)?)'
        . '\s*(?:cm|mm|cent[ií]metros?|mil[ií]metros?)?/iu';

    preg_match_all(
        $patronAutocorreccionNumericaDirecta,
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
    $patronAutocorreccionNumericaInvertida =
        '/(?P<corregido>\d+(?:[.,]\d+)?)'
        . '\s*(?:cm|mm|cent[ií]metros?|mil[ií]metros?)?'
        . '\s*,?\s*\bno\s+en\s+'
        . '(?P<anterior>\d+(?:[.,]\d+)?)'
        . '\s*(?:cm|mm|cent[ií]metros?|mil[ií]metros?)?/iu';

    preg_match_all(
        $patronAutocorreccionNumericaInvertida,
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

    /*
    * Ubicar sobre la MISMA copia semántica las autocorrecciones
    * numéricas que ya fueron resueltas determinísticamente.
    *
    * Así evitamos enviar además el mismo "no era/no es" como
    * autocorrección semántica candidata.
    */
    $rangosAutocorreccionNumerica = [];

    foreach ([
        $patronAutocorreccionNumericaDirecta,
        $patronAutocorreccionNumericaInvertida,
    ] as $patronNumerico) {
        preg_match_all(
            $patronNumerico,
            $textoAutocorreccionSemantica,
            $coincidenciasNumericasSemanticas,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($coincidenciasNumericasSemanticas as $coincidenciaNumerica) {
            $textoCoincidencia = (string)($coincidenciaNumerica[0][0] ?? '');
            $offsetCoincidencia = (int)($coincidenciaNumerica[0][1] ?? -1);

            if ($textoCoincidencia === '' || $offsetCoincidencia < 0) {
                continue;
            }

            $rangosAutocorreccionNumerica[] = [
                'inicio' => $offsetCoincidencia,
                'fin' => $offsetCoincidencia + strlen($textoCoincidencia),
            ];
        }
    }

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
        * Si este marcador está dentro de una autocorrección numérica
        * ya resuelta determinísticamente, no duplicarlo como candidata.
        */
        $yaResueltaNumericamente = false;

        foreach ($rangosAutocorreccionNumerica as $rangoNumerico) {
            if (
                $offsetBytes >= $rangoNumerico['inicio']
                && $offsetBytes < $rangoNumerico['fin']
            ) {
                $yaResueltaNumericamente = true;
                break;
            }
        }

        if ($yaResueltaNumericamente) {
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
        $normalizarClaveAlias,
        $esHallazgoCavitarioIndependiente,
        $esContinuacionContextual,
        $esContinuacionCompatibleConOrgano
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
                'tipo_contexto' => 'organo_explicito',
                'contexto' => trim(mb_substr(
                    $textoTrabajo,
                    max(0, $pos - 90),
                    220,
                    'UTF-8'
                )),
                'previo' => trim(
                    mb_substr(
                        $textoTrabajo,
                        max(0, $pos - 60),
                        min(60, $pos),
                        'UTF-8'
                    )
                ),
                'continuacion' => mb_substr(
                    $textoTrabajo,
                    $pos,
                    60,
                    'UTF-8'
                )
            ];
        }

                /*
         * Una discrepancia que comienza en un hallazgo cavitario
         * independiente no debe heredar el último órgano previo.
         *
         * Ejemplo:
         *   "Riñón derecho ... No se observa derrame peritoneal"
         *
         * Si la diferencia comienza en "derrame", el contexto
         * anatómico debe quedar sin órgano.
         */
        if ($esHallazgoCavitarioIndependiente($desdePosicion)) {
            return [
                'organo' => null,
                'lateralidad' => null,
                'distancia_organo' => null,
                'contexto' => trim(mb_substr(
                    $textoTrabajo,
                    max(0, $pos - 90),
                    220,
                    'UTF-8'
                )),
                'previo' => trim(
                    mb_substr(
                        $textoTrabajo,
                        max(0, $pos - 60),
                        min(60, $pos),
                        'UTF-8'
                    )
                ),
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

            $descartadoLocal = trim(
                (string)($correccionLocal['descartado'] ?? '')
            );

            if ($descartadoLocal === '') {
                continue;
            }

            preg_match_all(
                '/\S+/u',
                $descartadoLocal,
                $tokensDescartadoLocal
            );

            $cantidadTokensDescartadoLocal = count(
                $tokensDescartadoLocal[0] ?? []
            );

            if ($cantidadTokensDescartadoLocal < 1) {
                continue;
            }

            $ultimoIndiceLocal =
                $indiceLocal + $cantidadTokensDescartadoLocal - 1;

            if (!isset($tokensTrabajo[0][$ultimoIndiceLocal])) {
                continue;
            }

            $tramoLocal = [];

            for (
                $i = $indiceLocal;
                $i <= $ultimoIndiceLocal;
                $i++
            ) {
                $tramoLocal[] =
                    (string)$tokensTrabajo[0][$i][0];
            }

            if (
                $normalizarClaveAlias(implode(' ', $tramoLocal))
                !== $normalizarClaveAlias($descartadoLocal)
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
            $indiceDespuesCorreccion =
                $indiceLocal + $cantidadTokensDescartadoLocal;

            if (isset($tokensTrabajo[0][$indiceDespuesCorreccion])) {
                $tokenSiguiente = trim(
                    (string)$tokensTrabajo[0][$indiceDespuesCorreccion][0],
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

                /*
         * Incluso dentro del mismo segmento, un descriptor fuertemente
         * incompatible invalida la herencia anatómica.
         *
         * Ejemplo:
         *
         *   "Adrenal derecha ... 0.4 cm, ... 0.37 cm
         *    con patrón mucoso, estratificación..."
         *
         * Aunque no exista un punto entre ambos bloques, una discrepancia
         * situada en el segundo no debe heredarse como Adrenal.
         */
        $segmentoCompatibilidad = preg_split(
            '/[.!?;]/u',
            $desdePosicion,
            2
        )[0] ?? $desdePosicion;

        if (
            $organoFinal !== null
            && !$esContinuacionCompatibleConOrgano(
                $organoFinal,
                $segmentoCompatibilidad
            )
        ) {
            return [
                'organo' => null,
                'lateralidad' => null,
                'distancia_organo' => null,
                'tipo_contexto' => 'corte_estructural',
                'contexto' => trim(mb_substr(
                    $textoTrabajo,
                    max(0, $pos - 90),
                    220,
                    'UTF-8'
                )),
                'previo' => trim(mb_substr(
                    $textoTrabajo,
                    max(0, $pos - 60),
                    min(60, $pos),
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


        /*
        * No permitir que una discrepancia herede un órgano situado
        * en un segmento clínico anterior solo porque está cerca.
        *
        * Ejemplos que deben cortar continuidad:
        *
        *   "Adrenal izquierda ... . Y 1 ..."
        *   "Adrenal izquierda ... . hilio esplénico ..."
        *   "Riñón derecho ... . masa abdominal ..."
        *
        * Una ruptura de frase solo permite conservar el órgano previo
        * cuando el nuevo segmento comienza como una continuación
        * descriptiva reconocible:
        *
        *   "Riñón izquierdo ... . Ecogenicidad aumentada ..."
        *   "Riñón izquierdo ... . Pelvis conservada ..."
        */
        $tipoContexto = 'mismo_segmento';

        if (
            $posOrganoFinal !== null
            && $posOrganoFinal < $pos
        ) {
            $entreOrganoYDiscrepancia = mb_substr(
                $textoTrabajo,
                $posOrganoFinal,
                $pos - $posOrganoFinal,
                'UTF-8'
            );

            /*
            * Si existe puntuación estructural entre el órgano candidato
            * y la discrepancia, analizamos el segmento que comenzó después
            * del último corte.
            */
            if (
                preg_match(
                    '/[.!?;]\s*([^.!?;]*)$/u',
                    $entreOrganoYDiscrepancia,
                    $mCorte
                )
            ) {
                $segmentoActual = trim(
                    (string)($mCorte[1] ?? '')
                    . ' '
                    . $desdePosicion
                );

                /*
                * Los hallazgos cavitarios/generalizados nunca mantienen
                * el órgano previo.
                */
                if (
                    $esHallazgoCavitarioIndependiente(
                        $segmentoActual
                    )
                ) {
                    return [
                        'organo' => null,
                        'lateralidad' => null,
                        'distancia_organo' => null,
                        'tipo_contexto' => 'corte_estructural',
                        'contexto' => trim(mb_substr(
                            $textoTrabajo,
                            max(0, $pos - 90),
                            220,
                            'UTF-8'
                        )),
                        'previo' => trim(mb_substr(
                            $textoTrabajo,
                            max(0, $pos - 60),
                            min(60, $pos),
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

                /*
                * Si después del corte no comienza una continuación clínica
                * reconocible, el órgano anterior deja de ser válido.
                *
                * Ante duda preferimos dejar la discrepancia sin órgano antes
                * que atribuirla incorrectamente al órgano previo.
                */
                if (
                    !$esContinuacionContextual(
                        $segmentoActual
                    )
                ) {
                    return [
                        'organo' => null,
                        'lateralidad' => null,
                        'distancia_organo' => null,
                        'tipo_contexto' => 'corte_estructural',
                        'contexto' => trim(mb_substr(
                            $textoTrabajo,
                            max(0, $pos - 90),
                            220,
                            'UTF-8'
                        )),
                        'previo' => trim(mb_substr(
                            $textoTrabajo,
                            max(0, $pos - 60),
                            min(60, $pos),
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

                $tipoContexto = 'continuacion';
            }
        }

        $distanciaOrgano = $posOrganoFinal !== null
            ? max(0, $pos - $posOrganoFinal)
            : null;

        $distanciaOrgano = $posOrganoFinal !== null
            ? max(0, $pos - $posOrganoFinal)
            : null;

        return [
        'organo' => $organoFinal,
        'lateralidad' => $lateralidadFinal,
        'distancia_organo' => $distanciaOrgano,
        'tipo_contexto' => $tipoContexto,
            'contexto' => trim(mb_substr(
                $textoTrabajo,
                max(0, $pos - 90),
                220,
                'UTF-8'
            )),
            'previo' => trim(
                mb_substr(
                    $textoTrabajo,
                    max(0, $pos - 60),
                    min(60, $pos),
                    'UTF-8'
                )
            ),
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

        /*
        * Las equivalencias numéricas se resuelven previamente en
        * stt_validador.php, donde pueden validarse de forma segura:
        *
        * - igualdad exacta del valor;
        * - unidad compatible;
        * - órgano/contexto compatible;
        * - sin tolerancias numéricas.
        *
        * No resolver aquí "un/uno/una" -> "1" de forma aislada,
        * porque podría ocultar una discrepancia real.
        */

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
         * Elegir primero el contexto anatómico de mayor calidad.
         *
         * Prioridad:
         *
         * 1. órgano explícito en el punto de discrepancia;
         * 2. órgano encontrado dentro del mismo segmento clínico;
         * 3. órgano heredado mediante una continuación válida.
         *
         * La distancia solo decide cuando ambos contextos tienen
         * la misma calidad.
         *
         * Esto evita que un órgano anterior gane únicamente porque
         * está unos caracteres más cerca.
         */
        $tieneA = !empty($contextoA['organo']);
        $tieneB = !empty($contextoB['organo']);

        $prioridadContexto = static function (array $contexto): int {
            if (empty($contexto['organo'])) {
                return 0;
            }

            return match ($contexto['tipo_contexto'] ?? null) {
                'organo_explicito' => 3,
                'mismo_segmento' => 2,
                'continuacion' => 1,

                /*
                * Compatibilidad defensiva:
                * si apareciera un contexto antiguo sin clasificación,
                * no le damos ventaja sobre uno explícitamente clasificado.
                */
                default => 1,
            };
        };

        if ($tieneA && !$tieneB) {
            $contexto = $contextoA;

        } elseif ($tieneB && !$tieneA) {
            $contexto = $contextoB;

        } elseif ($tieneA && $tieneB) {
            $prioridadA = $prioridadContexto($contextoA);
            $prioridadB = $prioridadContexto($contextoB);

            if ($prioridadA > $prioridadB) {
                $contexto = $contextoA;

            } elseif ($prioridadB > $prioridadA) {
                $contexto = $contextoB;

            } else {
                /*
                * Solo si ambos contextos tienen la misma calidad
                * usamos la distancia anatómica como desempate.
                */
                $distanciaA =
                    $contextoA['distancia_organo']
                    ?? PHP_INT_MAX;

                $distanciaB =
                    $contextoB['distancia_organo']
                    ?? PHP_INT_MAX;

                if ($distanciaA < $distanciaB) {
                    $contexto = $contextoA;

                } elseif ($distanciaB < $distanciaA) {
                    $contexto = $contextoB;

                } else {
                    /*
                    * Misma calidad y misma distancia:
                    * solo adjudicamos si ambos motores coinciden
                    * realmente en órgano y lateralidad.
                    */
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

                    $contexto = (
                        $mismoOrgano
                        && $mismaLateralidad
                    )
                        ? $contextoA
                        : [];
                }
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

        /*
        * La palabra "relación" puede haber quedado fuera de la discrepancia
        * porque LCS conserva el término común y deja únicamente los valores:
        *
        *   aumentada / disminuida
        *
        * Por eso aceptamos dos evidencias:
        *   1. "relación" aparece dentro de las alternativas;
        *   2. "relación [córtico-medular]" termina inmediatamente antes
        *      del punto de discrepancia en cualquiera de los motores.
        */
        $relacionEnAlternativas = preg_match(
            '/(?<!\p{L})relaci[oó]n(?!\p{L})/iu',
            $textoAlternativas
        ) === 1;

        $patronRelacionPrevia =
            '/\brelaci[oó]n'
            . '(?:\s+c[oó]rtico[\s-]*medular)?'
            . '[\s,:;-]*$/iu';

        $relacionJustoAntes = (
            preg_match(
                $patronRelacionPrevia,
                (string)($contextoA['previo'] ?? '')
            ) === 1
            || preg_match(
                $patronRelacionPrevia,
                (string)($contextoB['previo'] ?? '')
            ) === 1
        );

        $hayValorRelacion = preg_match(
            '/(?<!\p{L})(?:'
            . 'aumentad[oa]s?'
            . '|disminuid[oa]s?'
            . '|conservad[oa]s?'
            . ')(?!\p{L})/iu',
            $textoAlternativas
        ) === 1;

        if (
            $esRinon
            && ($relacionEnAlternativas || $relacionJustoAntes)
            && $hayValorRelacion
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

        /*
        * Un órgano mencionado solamente por B merece atención especial.
        *
        * Admitimos únicamente una referencia anatómica aislada:
        *
        *   "Riñón"
        *   "Riñón derecho"
        *   "el Riñón izquierdo"
        *   "Adrenal derecha,"
        *
        * No interpretamos aquí frases clínicas completas.
        */
        if (
            $tipo === 'solo_B'
            && preg_match(
                '/^(?:(?:el|la|los|las)\s+)?'
                . '(' . $patronOrgano . ')'
                . '(?:\s+(izquierd[oa]|derech[oa]))?'
                . '[\s.,;:]*$/iu',
                trim($b),
                $mOrganoSoloB
            )
        ) {
            $organo = $normalizarOrganoDetectado(
                $mOrganoSoloB[1]
            );

            $lateralidad = !empty($mOrganoSoloB[2])
                ? mb_strtolower(
                    $mOrganoSoloB[2],
                    'UTF-8'
                )
                : null;

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
