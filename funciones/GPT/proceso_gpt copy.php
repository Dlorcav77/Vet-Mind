<?php
declare(strict_types=1);

require_once("../funciones/conn/conn.php"); // tu conexión
require_once("../configP.php");
require_once(__DIR__ . "/logs/logger.php");  // 👈 NUEVO: helper del paso 1
$mysqli = conn();

header('Content-Type: application/json; charset=utf-8');

// === Config de logging (opcionales, puedes dejarlos en logger.php) =========
// define('LOG_INCLUDE_BODIES', false); // true para loggear prompt/output completos
// define('LOG_SAMPLE_PERCENT', 10);    // % de muestreo de cuerpos si no activas LOG_INCLUDE_BODIES
// define('LOG_RETENTION_DAYS', 30);

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
$ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip  = $_SERVER['REMOTE_ADDR']     ?? '';
$prompt_hash = sha256_short($prompt);

app_log('request', [
    'rid'           => $rid,
    'model'         => $model,
    'plantilla_id'  => $plantilla_id,
    'prompt_hash'   => $prompt_hash,
    'prompt_bytes'  => strlen($prompt),
    'client_ip'     => $ip,
    'user_agent'    => $ua,
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
        ['role' => 'system', 'content' => 'Eres un médico veterinario especialista en informes clínicos. Mantén la estructura HTML y no elimines ninguna sección de la plantilla.'],
        ['role' => 'user',   'content' => $prompt]
    ],
    // 'temperature' => 0.7
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
$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

$response  = curl_exec($ch);
$curl_err  = curl_errno($ch) ? curl_error($ch) : '';
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
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
        'ms'    => $latency_ms
    ], 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión cURL: ' . $curl_err, 'rid' => $rid]);
    exit;
}

$result = json_decode((string)$response, true);
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

// === Éxito ================================================================
echo json_encode([
    'status'  => 'success',
    'content' => $content,
    'rid'     => $rid,          // útil para correlacionar en logs
    'usage'   => [
        'prompt_tokens'     => $prompt_tokens,
        'completion_tokens' => $completion_tokens,
        'total_tokens'      => $total_tokens,
        'cost_usd'          => $cost_usd
    ]
]);
