<?php
declare(strict_types=1);

require_once("../funciones/conn/conn.php");
require_once("../configP.php");
require_once(__DIR__ . "/logs/logger.php");
$mysqli = conn();

header('Content-Type: application/json; charset=utf-8');

// === Límites (ajústalos si quieres) ========================================
define('MAX_PROMPT_BYTES_SOFT', 102400);   // 100 KB → solo log
define('MAX_PROMPT_BYTES_HARD', 307200);   // 300 KB → rechazar
define('MAX_OUTPUT_TOKENS',     1500); 

define('SANITIZE_HTML_OUTPUT', false); // ← déjalo en false por defecto


// === Validación entrada =====================================================
if (!isset($_POST['texto']) || empty(trim((string)$_POST['texto']))) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió texto para procesar.']);
    exit;
}

// === Utilidades =============================================================
function limpiar_acentos(string $texto): string {
    return strtr($texto, [
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u',
        'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U',
        'ñ'=>'n', 'Ñ'=>'N', 'ü'=>'u', 'Ü'=>'U'
    ]);
}

function sanitize_html_output(string $html): string {
    // 1) Remover <script> y <style>
    $html = preg_replace('#<\s*(script|style)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);

    // 2) Remover atributos on* (onclick, onload, etc.)
    $html = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html);

    // 3) Neutralizar href/src con javascript: o data:
    $html = preg_replace('/\s(href|src)\s*=\s*([\'"])\s*(javascript:|data:).*?\2/i', ' $1="#"', $html);

    // 4) Remover iframes/objects/embed/applet (si no los usas en informes)
    $html = preg_replace('#<\s*(iframe|object|embed|applet)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);

    return $html;
}

function approx_tokens(string $s): int {
    return (int) ceil(mb_strlen($s, '8bit') / 4);
}


// === Preparar datos =========================================================
$paciente       = limpiar_acentos(trim((string)($_POST['paciente'] ?? '')));
$especie        = limpiar_acentos(trim((string)($_POST['especie'] ?? '')));
$raza           = limpiar_acentos(trim((string)($_POST['raza'] ?? '')));
$edad           = limpiar_acentos(trim((string)($_POST['edad'] ?? '')));
$sexo           = limpiar_acentos(trim((string)($_POST['sexo'] ?? '')));
$tipo_estudio   = limpiar_acentos(trim((string)($_POST['tipo_estudio'] ?? '')));
$plantilla_base = limpiar_acentos(trim((string)($_POST['plantilla_base'] ?? '')));
$motivo         = limpiar_acentos(trim((string)($_POST['motivo'] ?? '')));
$texto          = limpiar_acentos(trim((string)($_POST['texto'] ?? '')));
$plantilla_id   = (int)($_POST['plantilla_id'] ?? 0);

// Cargar ejemplos de la plantilla
$ejemplos = [];
if ($plantilla_id > 0) {
    $stmt = $mysqli->prepare("SELECT ejemplo FROM plantilla_informe_ejemplo WHERE plantilla_informe_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $plantilla_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ejemplos[] = $row['ejemplo'];
    }
    $stmt->close();
}
$texto_ejemplos = '';
if (!empty($ejemplos)) {
    $texto_ejemplos = "EJEMPLOS DE INFORME PARA ESTA PLANTILLA:\n";
    foreach ($ejemplos as $i => $ej) {
        $texto_ejemplos .= "Ejemplo " . ($i+1) . ":\n" . $ej . "\n\n";
    }
}

// === Construir prompt =======================================================
$prompt = "
Redacta un informe ecografico veterinario usando la PLANTILLA BASE proporcionada como formato.

**Contexto del paciente:** (no agregar al informe, solo para ajustar rangos)
- Especie: {$especie}
- Raza: {$raza}
- Edad: {$edad}
- Sexo: {$sexo}
- Tipo de estudio: {$tipo_estudio}
- Motivo: {$motivo}

**Instrucciones:**
- Transcribe exactamente lo que el medico dijo en el dictado (medidas, hallazgos, observaciones). No corrijas ni modifiques ningun dato.
- Si un valor numerico es **sospechoso** (fuera de rango fisiologico esperado segun especie/raza/edad), transcribe tal cual y agregale un numero (1), (2), ... resaltado con `<span style='color:orange;'>...</span>`.
- Si hay **incongruencia** entre texto y valor (p.ej. texto dice 'normal' pero el valor no lo es), marcala igual en naranja con numero.
- No marques como incongruente si el texto ya justifica que esta aumentado/disminuido y el valor concuerda.

**Reglas clave:**
- Marca solo desviaciones sin justificacion o desacuerdos texto-valor.
- Si texto dice 'aumentado' y el valor es mayor, NO marcar.
- Si texto dice 'normal' y el valor no lo es (o viceversa), SI marcar.

**Resultado:**
- Devuelve solo el informe en **HTML limpio**.
- Agrega seccion **CONCLUSION** (en negrita) con lista de guiones: cada item comienza con `&nbsp;&nbsp;- ` y termina con `<br>`.

**Observaciones del Asistente** (al final, despues de CONCLUSION):
- Lista (1), (2), ... explicando cada marca.

PLANTILLA:
{$plantilla_base}

TEXTOS DE EJ:
{$texto_ejemplos}

DICTADO DEL VETERINARIO:
{$texto}
";
$prompt = limpiar_acentos(trim($prompt));



// === API Key OpenAI ========================================================
$api_key = $OPENAI_API_KEY ?? '';
if (!$api_key) {
    echo json_encode(['status' => 'error', 'message' => 'API Key de OpenAI no configurada.']);
    exit;
}

// === Modelo ================================================================
$model = 'gpt-4o-mini';// in 0.15  out 0.60         
// $model = 'gpt-4o';     // in 2.50  out 10'
// $model = 'gpt-5-nano';    // in 0.05  out 0.40
// $model = 'gpt-5-mini'; // in 0.25  out 2   
// $model = 'gpt-5';      // in 1.25  out 10

// === Logging: request_id y request ========================================
$rid = new_request_id();
header('X-Request-Id: ' . $rid);
$ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip  = $_SERVER['REMOTE_ADDR']     ?? '';
$prompt_hash = sha256_short($prompt);
$user_tag = 'anon_' . substr(hash('sha256', ($ip ?: '-') . '|' . ($ua ?: '-')), 0, 24);

// ===== Guardrails de tamaño del prompt =====================================
$prompt_bytes  = strlen($prompt);
$prompt_tokens = approx_tokens($prompt);

if ($prompt_bytes > MAX_PROMPT_BYTES_HARD) {
    app_log('error', [
        'rid'   => $rid ?? '(pre-rid)',
        'at'    => 'prompt_too_large',
        'bytes' => $prompt_bytes,
        'approx_tokens' => $prompt_tokens
    ], 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'El texto a procesar es demasiado grande. Divide el dictado o reduce la plantilla.']);
    exit;
}

if ($prompt_bytes > MAX_PROMPT_BYTES_SOFT) {
    app_log('warn', [
        'rid'   => $rid ?? '(pre-rid)',
        'at'    => 'prompt_large_soft',
        'bytes' => $prompt_bytes,
        'approx_tokens' => $prompt_tokens
    ], 'WARN');
}

app_log('request', [
    'rid'           => $rid,
    'model'         => $model,
    'plantilla_id'  => $plantilla_id,
    'prompt_hash'   => $prompt_hash,
    'prompt_bytes'  => strlen($prompt),
    'client_ip'     => $ip,
    'user_agent'    => $ua,
    'openai_user'   => $user_tag,
    'app_prompt_bytes'  => $prompt_bytes,
    'app_prompt_tokens' => $prompt_tokens,
]);

// (Opcional) guardar cuerpo del prompt (según flags)
app_log_body('prompt', [
    'rid'    => $rid,
    'model'  => $model,
    'prompt' => $prompt,
]);

// === Llamada a OpenAI ======================================================
$payload = [
    'model'     => $model,
    'messages'  => [
        ['role' => 'system', 'content' => '
                        Eres un médico veterinario especialista en informes clínicos por ecografía.
                        RESPETA la estructura HTML de la plantilla y NO elimines secciones existentes.
                        NO inventes datos. NO completes valores ausentes.
                        Solo usa contenido CLÍNICO del dictado. Ignora marketing, marcas, “copy” publicitario,
                        nombres propios no clínicos y frases que no describan hallazgos/medidas/interpretaciones.
                        Si hay dudas, prioriza transcripción literal clínica y marca inconsistencias con (1)(2)...
                        Prohíbido: cambiar unidades, crear diagnósticos no mencionados, añadir bibliografía.
                        Salida: ÚNICAMENTE HTML válido UTF-8.
                        '],
        ['role' => 'user',   'content' => $prompt]
    ],
    // 'temperature' => 0.7,
    'user'      => $user_tag, 
    'max_tokens'=> MAX_OUTPUT_TOKENS,
];

$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($jsonPayload === false) {
    app_log('error', [
        'rid'   => $rid,
        'at'    => 'encode_payload',
        'error' => json_last_error_msg()
    ], 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Error generando payload JSON.']);
    exit;
}

$t0 = microtime(true);

// === Retry/backoff ante 429/5xx/timeouts ===
$attempts = 0;
$maxAttempts = 3;
$delays = [0, 2, 5]; // segundos entre intentos

$response = '';
$curl_err = '';
$http_code = 0;

do {
    if ($attempts > 0) {
        sleep($delays[$attempts]); // backoff progresivo
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

    // ⏱️ Timeouts razonables + HTTP/2 cuando esté
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    if (defined('CURL_HTTP_VERSION_2TLS')) {
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
    }

    $response  = curl_exec($ch);
    $curl_err  = curl_errno($ch) ? curl_error($ch) : '';
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Reintentar ante errores transitorios
    $isTimeout = ($curl_err !== '' && str_contains(strtolower($curl_err), 'timed out'));
    $isRetryableHttp = in_array($http_code, [429, 500, 502, 503, 504], true);

    if ($curl_err === '' && $http_code > 0 && !$isRetryableHttp) {
        break; // éxito o fallo no reintetable
    }

    $attempts++;
} while ($attempts < $maxAttempts);

$t1 = microtime(true);
$latency_ms = (int)round(($t1 - $t0) * 1000);

// Guardar raw (opcional)
app_log_body('raw_response', [
    'rid'        => $rid,
    'http_code'  => $http_code,
    'body'       => (string)$response,
]);

if ($curl_err !== '') {
    app_log('error', [
        'rid'   => $rid,
        'at'    => 'openai_http',
        'error' => $curl_err,
        'ms'    => $latency_ms,
        'try'   => $attempts
    ], 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión cURL: ' . $curl_err, 'rid' => $rid]);
    exit;
}

$result = json_decode((string)$response, true);
if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
    app_log('error', [
        'rid'   => $rid,
        'at'    => 'openai_json_decode',
        'http'  => $http_code,
        'error' => json_last_error_msg()
    ], 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Respuesta inválida de OpenAI (JSON).', 'rid' => $rid]);
    exit;
}

if ($http_code !== 200) {
    $error_detail = $result['error']['message'] ?? ('HTTP ' . $http_code);
    app_log('error', [
        'rid'   => $rid,
        'at'    => 'openai_api',
        'http'  => $http_code,
        'error' => $error_detail,
        'ms'    => $latency_ms
    ], 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Error API OpenAI: ' . $error_detail, 'rid' => $rid]);
    exit;
}

if (!isset($result['choices'][0]['message']['content'])) {
    app_log('error', [
        'rid'  => $rid,
        'at'   => 'openai_shape',
        'ms'   => $latency_ms
    ], 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Respuesta inesperada de OpenAI.', 'rid' => $rid]);
    exit;
}


// === Tokens y costo estimado ==============================================
$usage = $result['usage'] ?? [];
$prompt_tokens     = (int)($usage['prompt_tokens']     ?? ($usage['input_tokens']  ?? 0));
$completion_tokens = (int)($usage['completion_tokens'] ?? ($usage['output_tokens'] ?? 0));
$total_tokens      = (int)($usage['total_tokens']      ?? ($prompt_tokens + $completion_tokens));
$cost_usd          = gpt_estimate_cost_usd($model, $prompt_tokens, $completion_tokens);

// === Contenido final =======================================================
$content = (string)$result['choices'][0]['message']['content'];

// 🔒 Sanitizar si el toggle está activo
if (defined('SANITIZE_HTML_OUTPUT') && SANITIZE_HTML_OUTPUT) {
    $content = sanitize_html_output($content);
}

// Log output (opcional)
app_log_body('output', [
    'rid'     => $rid,
    'model'   => $model,
    'content' => $content,
]);

// Log response (métricas)
app_log('response', [
    'rid'               => $rid,
    'model'             => $model,
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

// === Éxito ================================================================
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

