<?php
declare(strict_types=1);
/** @var string $system Generado por el motor de revisión incluido a continuación. */
/** @var string $user Generado por el motor de revisión incluido a continuación. */


if (
    !defined('VETMIND_REVISOR_DISPATCH')
    || !isset($configMotor)
) {
    http_response_code(403);
    exit;
}

$api_key = $XAI_API_KEY ?? '';
if (!$api_key) {
    echo json_encode(['status'=>'error','message'=>'API Key de xAI/Grok no configurada.']);
    exit;
}

$payload = [
    'model'       => $configMotor['model'],
    'reasoning_effort' => $configMotor['reasoning_effort'],
    'messages'    => [
        ['role'=>'system','content'=>$system],
        ['role'=>'user','content'=>$user],
    ],
    'max_tokens'       => $configMotor['max_tokens'],
    'temperature'      => $configMotor['temperature'],
    'response_format'  => $configMotor['response_format'],
];
$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

$rid = new_request_id();

$t0 = microtime(true);
$ch = curl_init('https://api.x.ai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $jsonPayload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $api_key,
    ],
    CURLOPT_CONNECTTIMEOUT => $configMotor['timeouts']['connect'],
    CURLOPT_TIMEOUT        => $configMotor['timeouts']['total'],
]);
$resp = curl_exec($ch);
$err  = curl_errno($ch) ? curl_error($ch) : '';
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$ms = (int)round((microtime(true)-$t0)*1000);

if ($err !== '') {
    echo json_encode(['status'=>'error','message'=>'cURL: '.$err]);
    exit;
}
$result = json_decode((string)$resp, true);
if (!is_array($result)) {
    echo json_encode(['status'=>'error','message'=>'Respuesta no-JSON de xAI/Grok.']);
    exit;
}
if ($http !== 200) {
    $d = $result['error']['message'] ?? ('HTTP '.$http);
    echo json_encode(['status'=>'error','message'=>'Error API xAI/Grok: '.$d]);
    exit;
}

$choice  = $result['choices'][0] ?? [];
$content = (string)($choice['message']['content'] ?? '');
$finish  = (string)($choice['finish_reason'] ?? '');

$usage = $result['usage'] ?? [];
$pt = (int)($usage['prompt_tokens'] ?? 0);
$ct = (int)($usage['completion_tokens'] ?? 0);

$cost = 0.0;
if (isset($usage['cost_in_usd_ticks'])) {
    $cost = round(((float)$usage['cost_in_usd_ticks']) / 10_000_000_000, 6);
}
if ($cost <= 0) {
    $cost = gpt_estimate_cost_usd($configMotor['model'], $pt, $ct);
}
$tt = (int)($usage['total_tokens'] ?? ($pt + $ct));
$usageOut = ['prompt_tokens'=>$pt,'completion_tokens'=>$ct,'cost_usd'=>$cost,'ms'=>$ms];

