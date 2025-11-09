<?php
declare(strict_types=1);

// rutas base
$ROOT_DIR = dirname(__DIR__, 2);   // /
$FUNC_DIR = dirname(__DIR__);      // /funciones
$GPT_DIR  = __DIR__;               // /funciones/GPT

require_once($FUNC_DIR . "/conn/conn.php");
require_once($ROOT_DIR . "/configP.php");
require_once($FUNC_DIR . "/logs/logger.php");
// require_once($FUNC_DIR . "/data/ref_ranges.php");


// nuestros nuevos helpers
require_once($GPT_DIR . "/lib/gpt_prompt.php");
require_once($GPT_DIR . "/lib/gpt_postprocess.php");

date_default_timezone_set('America/Santiago');

header('Content-Type: application/json; charset=utf-8');

$mysqli = conn();

// límites
define('MAX_PROMPT_BYTES_SOFT', 102400);   // 100 KB
define('MAX_PROMPT_BYTES_HARD', 307200);   // 300 KB
define('MAX_OUTPUT_TOKENS',     1500);

// 1. validar entrada mínima
$texto_dictado = trim((string)($_POST['texto'] ?? ''));
if ($texto_dictado === '') {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió texto para procesar.']);
    exit;
}

// 2. recoger datos de paciente / plantilla
$input = [
    'paciente'      => trim((string)($_POST['paciente'] ?? '')),
    'especie'       => trim((string)($_POST['especie'] ?? '')),
    'raza'          => trim((string)($_POST['raza'] ?? '')),
    'edad'          => trim((string)($_POST['edad'] ?? '')),
    'sexo'          => trim((string)($_POST['sexo'] ?? '')),
    'tipo_estudio'  => trim((string)($_POST['tipo_estudio'] ?? '')),
    'motivo'        => trim((string)($_POST['motivo'] ?? '')),
    'plantilla_base'=> (string)($_POST['plantilla_base'] ?? ''),
    'texto'         => $texto_dictado,
    'plantilla_id'  => (int)($_POST['plantilla_id'] ?? 0),
];

// 3. armar prompt y system usando el helper
$promptData = gpt_build_prompt($mysqli, $input); // devuelve ['system'=>..., 'prompt'=>..., 'incluir_conclusion'=>bool]
$system             = $promptData['system'];
$prompt             = $promptData['prompt'];
$incluir_conclusion = $promptData['incluir_conclusion'];
$plantilla_id       = $input['plantilla_id'];

// 4. chequeo de tamaño
$prompt_bytes  = strlen($prompt);
$prompt_tokens = gpt_approx_tokens($prompt);

if ($prompt_bytes > MAX_PROMPT_BYTES_HARD) {
    echo json_encode(['status' => 'error', 'message' => 'El texto a procesar es demasiado grande. Divide el dictado o reduce la plantilla.']);
    exit;
}

// 5. API key
$api_key = $OPENAI_API_KEY ?? '';
if (!$api_key) {
    echo json_encode(['status' => 'error', 'message' => 'API Key de OpenAI no configurada.']);
    exit;
}

// 6. logging base
$rid = new_request_id();
header('X-Request-Id: ' . $rid);
$ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip  = $_SERVER['REMOTE_ADDR']     ?? '';
$user_tag = 'anon_' . substr(hash('sha256', ($ip ?: '-') . '|' . ($ua ?: '-')), 0, 24);

app_log('request', [
    'rid'          => $rid,
    'model'        => 'gpt-4o',
    'plantilla_id' => $plantilla_id,
    'prompt_bytes' => $prompt_bytes,
    'client_ip'    => $ip,
    'user_agent'   => $ua,
], 'INFO');

// opcional guardar prompt
app_log_body('prompt', [
    'rid'    => $rid,
    'prompt' => $prompt,
]);

// 7. llamada a OpenAI
$payload = [
    'model'     => 'gpt-4o',
    'messages'  => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' => $prompt],
    ],
    'temperature' => 0.1,
    'user'        => $user_tag,
    'max_tokens'  => MAX_OUTPUT_TOKENS,
];

$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

$t0 = microtime(true);
$attempts   = 0;
$maxAttempts= 3;
$delays     = [0, 2, 5];
$response   = '';
$curl_err   = '';
$http_code  = 0;

do {
    if ($attempts > 0) {
        sleep($delays[$attempts]);
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response  = curl_exec($ch);
    $curl_err  = curl_errno($ch) ? curl_error($ch) : '';
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $isRetryableHttp = in_array($http_code, [429,500,502,503,504], true);
    if ($curl_err === '' && !$isRetryableHttp) {
        break;
    }
    $attempts++;
} while ($attempts < $maxAttempts);

$t1 = microtime(true);
$latency_ms = (int)round(($t1 - $t0) * 1000);

// guardar raw
app_log_body('raw_response', [
    'rid'       => $rid,
    'http_code' => $http_code,
    'body'      => (string)$response,
]);

if ($curl_err !== '') {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión cURL: '.$curl_err, 'rid' => $rid]);
    exit;
}

$result = json_decode((string)$response, true);
if ($result === null) {
    echo json_encode(['status' => 'error', 'message' => 'Respuesta inválida de OpenAI (JSON).', 'rid' => $rid]);
    exit;
}

if ($http_code !== 200) {
    $err_detail = $result['error']['message'] ?? ('HTTP '.$http_code);
    echo json_encode(['status' => 'error', 'message' => 'Error API OpenAI: '.$err_detail, 'rid' => $rid]);
    exit;
}

$content = (string)($result['choices'][0]['message']['content'] ?? '');

// 8. postprocesar con nuestro helper
$ctxPaciente = [
    'especie' => $input['especie'],
    'raza'    => $input['raza'],
    'edad'    => $input['edad'],
];
$content = gpt_postprocess_html($content, $incluir_conclusion, $ctxPaciente);

// 9. métricas
$usage = $result['usage'] ?? [];
$prompt_tokens     = (int)($usage['prompt_tokens']     ?? ($usage['input_tokens']  ?? 0));
$completion_tokens = (int)($usage['completion_tokens'] ?? ($usage['output_tokens'] ?? 0));
$total_tokens      = (int)($usage['total_tokens']      ?? ($prompt_tokens + $completion_tokens));
$cost_usd          = gpt_estimate_cost_usd('gpt-4o', $prompt_tokens, $completion_tokens);

app_log('response', [
    'rid'               => $rid,
    'http'              => $http_code,
    'ms'                => $latency_ms,
    'prompt_tokens'     => $prompt_tokens,
    'completion_tokens' => $completion_tokens,
    'total_tokens'      => $total_tokens,
    'cost_usd'          => $cost_usd
], 'INFO');

if ($mysqli instanceof mysqli) {
    @$mysqli->close();
}

// 10. respuesta final
echo json_encode([
    'status'  => 'success',
    'content' => $content,
    'rid'     => $rid,
    'usage'   => [
        'prompt_tokens'     => $prompt_tokens,
        'completion_tokens' => $completion_tokens,
        'total_tokens'      => $total_tokens,
        'cost_usd'          => $cost_usd
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
