<?php
declare(strict_types=1);

/**
 * Interpretación clínica con OpenAI.
 * Recibe el prompt preparado y devuelve el JSON clínico y sus métricas.
 * No genera HTML ni escribe directamente en la base de datos.
 */
function interpretacion_openai(
    array $promptData,
    array $configMotor,
    string $apiKey,
    array $resueltas = []
): array {
    $modelo = (string)$configMotor['model'];
    $system = (string)$promptData['system'];
    $prompt = (string)$promptData['prompt'];

    if (trim($apiKey) === '') {
        throw new RuntimeException('API Key de OpenAI no configurada.');
    }

    if (strlen($prompt) > (int)$configMotor['max_prompt_bytes_hard']) {
        throw new RuntimeException('El prompt de interpretación supera el límite permitido.');
    }

    $payload = [
        'model' => $modelo,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt],
        ],
        'max_completion_tokens' => (int)$configMotor['max_output_tokens'],
        'reasoning_effort' => (string)$configMotor['reasoning_effort'],
        'response_format' => ['type' => 'json_object'],
    ];

    $jsonPayload = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $maxIntentos = max(1, (int)$configMotor['max_attempts']);
    $demoras = $configMotor['retry_delays'];
    $timeouts = $configMotor['timeouts'];

    $inicio = hrtime(true);
    $respuesta = '';
    $httpCode = 0;

    for ($intento = 0; $intento < $maxIntentos; $intento++) {
        if ($intento > 0) {
            sleep((int)($demoras[$intento] ?? 0));
        }

        $ch = curl_init('https://api.openai.com/v1/chat/completions');

        if ($ch === false) {
            throw new RuntimeException('No se pudo iniciar cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_CONNECTTIMEOUT => (int)$timeouts['connect'],
            CURLOPT_TIMEOUT => (int)$timeouts['total'],
        ]);

        $respuesta = (string)curl_exec($ch);
        $curlError = curl_errno($ch) ? curl_error($ch) : '';
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $reintentable = $curlError !== ''
            || in_array($httpCode, [429, 500, 502, 503, 504], true);

        if ($reintentable && $intento + 1 < $maxIntentos) {
            continue;
        }

        if ($curlError !== '') {
            throw new RuntimeException('Error de conexión OpenAI: ' . $curlError);
        }

        if ($httpCode !== 200) {
            $errorApi = json_decode($respuesta, true);
            $detalle = $errorApi['error']['message'] ?? 'HTTP ' . $httpCode;

            throw new RuntimeException('Error API OpenAI: ' . $detalle);
        }

        break;
    }

    $respuestaApi = json_decode($respuesta, true, 512, JSON_THROW_ON_ERROR);

    $eleccion = $respuestaApi['choices'][0] ?? [];
    $contenido = trim((string)($eleccion['message']['content'] ?? ''));
    $finalizacion = (string)($eleccion['finish_reason'] ?? '');

    if (!empty($eleccion['message']['refusal'])) {
        throw new RuntimeException('El modelo rechazó la interpretación.');
    }

    if ($finalizacion !== 'stop' || $contenido === '') {
        throw new RuntimeException(
            'Interpretación incompleta: ' . ($finalizacion ?: 'respuesta vacía')
        );
    }

    try {
        $resultado = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('El modelo devolvió un JSON no válido.', 0, $e);
    }

    if (!is_array($resultado) || array_is_list($resultado)) {
        throw new RuntimeException('La interpretación no es un objeto JSON.');
    }

    foreach ([
        'hallazgos',
        'autocorrecciones',
        'referencias_entre_organos',
        'discrepancias',
        'alertas'
    ] as $campo) {
        if (!isset($resultado[$campo])
            || !is_array($resultado[$campo])
            || !array_is_list($resultado[$campo])) {
            throw new RuntimeException(
                'Campo JSON ausente o inválido: ' . $campo
            );
        }
    }

    // Las correcciones STT proceden del validador, no del modelo.
    $resultado['version_esquema'] = '1';
    $resultado['metodo'] = 'ia';
    $resultado['correcciones_stt'] = $resueltas;

    $usage = $respuestaApi['usage'] ?? [];
    $tokensEntrada = (int)($usage['prompt_tokens'] ?? 0);
    $tokensSalida = (int)($usage['completion_tokens'] ?? 0);
    $tokensCache = min(
        $tokensEntrada,
        (int)($usage['prompt_tokens_details']['cached_tokens'] ?? 0)
    );

    $precios = $configMotor['pricing'];

    $costo = (
        ($tokensEntrada - $tokensCache) * $precios['input_1m']
        + $tokensCache * $precios['cached_input_1m']
        + $tokensSalida * $precios['output_1m']
    ) / 1000000;

    return [
        'resultado' => $resultado,
        'modelo' => (string)($respuestaApi['model'] ?? $modelo),
        'version_prompt' => (string)$configMotor['version_prompt'],
        'prompt_tokens' => $tokensEntrada,
        'completion_tokens' => $tokensSalida,
        'cost_usd' => round($costo, 6),
        'duracion_ms' => (int)round((hrtime(true) - $inicio) / 1000000),
    ];
}
