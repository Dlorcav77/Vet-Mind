<?php
// funciones/GPT/banco_comparacion/driver_comp.php
declare(strict_types=1);

if (!defined('GPT_SNAPSHOT')) {
    define('GPT_SNAPSHOT', 0); // banco de comparacion: sin snapshot
}

$ROOT_DIR = dirname(__DIR__, 3);   // /
$FUNC_DIR = dirname(__DIR__, 2);   // /funciones
$GPT_DIR  = dirname(__DIR__, 1);   // /funciones/GPT

require_once($FUNC_DIR . "/conn/conn.php");
require_once($ROOT_DIR . "/configP.php");
require_once($FUNC_DIR . "/logs/logger.php");
require_once($GPT_DIR . "/lib/gpt_prompt.php");
require_once($GPT_DIR . "/lib/gpt_postprocess.php");

/**
 * Llama a UN motor con el input dado y devuelve resultado normalizado.
 *
 * @param array  $motor  una entrada de motores.php
 * @param array  $input  campos de paciente/plantilla/texto (igual que produccion)
 * @return array ['ok'=>bool,'content'=>string,'err'=>string,'usage'=>array,'ms'=>int,'http'=>int]
 */
function comp_call_motor(array $motor, array $input): array
{
    global $XAI_API_KEY, $ANTHROPIC_API_KEY, $OPENAI_API_KEY;

    $mysqli = conn();

    // Prompt/system compartido (mismo helper que produccion).
    $promptData = gpt_build_prompt($mysqli, $input);
    $system             = $promptData['system'];
    $prompt             = $promptData['prompt'];
    $incluir_conclusion = $promptData['incluir_conclusion'];

    if ($mysqli instanceof mysqli) { @$mysqli->close(); }

    // Resolver key por nombre de variable.
    $keyVar = $motor['key_var'] ?? '';
    $api_key = $GLOBALS[$keyVar] ?? '';
    if ($api_key === '') {
        return ['ok'=>false,'content'=>'','err'=>"Falta key {$keyVar}",'usage'=>[],'ms'=>0,'http'=>0];
    }

    $api      = $motor['api'];
    $endpoint = $motor['endpoint'];
    $model    = $motor['model'];
    $params   = $motor['params'] ?? [];

    // Armar payload y headers segun api.
    if ($api === 'anthropic') {
        $payload = array_merge([
            'model'    => $model,
            'system'   => $system,
            'messages' => [['role'=>'user','content'=>$prompt]],
        ], $params);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
        ];
    } else {
        // openai y xai comparten formato chat/completions
        $payload = array_merge([
            'model'    => $model,
            'messages' => [
                ['role'=>'system','content'=>$system],
                ['role'=>'user','content'=>$prompt],
            ],
        ], $params);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $api_key,
        ];
    }

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

    // Llamada con reintentos.
    $t0 = microtime(true);
    $attempts = 0; $maxAttempts = 3; $delays = [0,2,5];
    $response = ''; $curl_err = ''; $http_code = 0;

    do {
        if ($attempts > 0) { sleep($delays[$attempts]); }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response  = curl_exec($ch);
        $curl_err  = curl_errno($ch) ? curl_error($ch) : '';
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $retry = in_array($http_code, [429,500,502,503,504], true);
        if ($curl_err === '' && !$retry) { break; }
        $attempts++;
    } while ($attempts < $maxAttempts);

    $ms = (int)round((microtime(true)-$t0)*1000);

    if ($curl_err !== '') {
        return ['ok'=>false,'content'=>'','err'=>'cURL: '.$curl_err,'usage'=>[],'ms'=>$ms,'http'=>$http_code];
    }

    $result = json_decode((string)$response, true);
    if (!is_array($result)) {
        return ['ok'=>false,'content'=>'','err'=>'JSON invalido','usage'=>[],'ms'=>$ms,'http'=>$http_code];
    }
    if ($http_code !== 200) {
        $detail = $result['error']['message'] ?? ('HTTP '.$http_code);
        return ['ok'=>false,'content'=>'','err'=>$detail,'usage'=>[],'ms'=>$ms,'http'=>$http_code];
    }

    // Extraer contenido segun api.
    if ($api === 'anthropic') {
        $content = '';
        foreach ($result['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') { $content .= $block['text']; }
        }
        $content = trim($content);
        $usage = $result['usage'] ?? [];
        $pt = (int)($usage['input_tokens']  ?? 0);
        $ct = (int)($usage['output_tokens'] ?? 0);
    } else {
        $content = (string)($result['choices'][0]['message']['content'] ?? '');
        $usage = $result['usage'] ?? [];
        $pt = (int)($usage['prompt_tokens']     ?? $usage['input_tokens']  ?? 0);
        $ct = (int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);
    }

    // Post-proceso identico a produccion.
    $ctxPaciente = [
        'especie' => $input['especie'] ?? '',
        'raza'    => $input['raza'] ?? '',
        'edad'    => $input['edad'] ?? '',
    ];
    $content = gpt_postprocess_html($content, $incluir_conclusion, $ctxPaciente);

    $cost = gpt_estimate_cost_usd($model, $pt, $ct);

    return [
        'ok'      => true,
        'content' => $content,
        'err'     => '',
        'usage'   => [
            'prompt_tokens'     => $pt,
            'completion_tokens' => $ct,
            'total_tokens'      => $pt + $ct,
            'cost_usd'          => $cost,
        ],
        'ms'      => $ms,
        'http'    => $http_code,
    ];
}