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
$motivo         = limpiar_acentos(trim((string)($_POST['motivo'] ?? '')));
$plantilla_base = trim((string)($_POST['plantilla_base'] ?? '')); // mantener UTF-8
$texto          = trim((string)($_POST['texto'] ?? ''));          // mantener UTF-8

$dictado_fold = mb_strtolower($texto, 'UTF-8');
$incluir_conclusion = (
    str_contains($dictado_fold, 'conclusión') ||
    str_contains($dictado_fold, 'conclusion')
);


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


REDACCION DE INFORME ECOGRAFICO VETERINARIO

Usa la siguiente PLANTILLA BASE como formato. Mantén las etiquetas y el orden.
Rellena solo con contenido CLINICO proveniente del DICTADO.

=== CONTEXTO (no incluir en el informe, solo para rangos) ===
Especie: {{especie}}
Raza: {{raza}}
Edad: {{edad}}
Sexo: {{sexo}}
Tipo de estudio: {{tipo_estudio}}
Motivo: {{motivo}}

=== INSTRUCCIONES DE TRANSCRIPCION (obligatorias) ===
1) Transcribe literalmente HALLAZGOS clínicos y MEDIDAS del DICTADO.
   - Excluye texto NO clínico (publicidad, marcas, personas, anuncios, etc.).
   - Si aparece un término CLÍNICO confuso, mantenlo literal y márcalo con:
     <sup class='flag' data-flag='(n)' data-tipo='termino_confuso'></sup>
2) Si un valor numérico es fisiológicamente sospechoso para el CONTEXTO, transcríbelo igual y marca con:
   <sup class='flag' data-flag='(n)' data-tipo='valor_sospechoso'></sup>
   *Se marca SIEMPRE, aunque el texto lo justifique. NO lo trates como incongruencia si está justificado.*
3) Si hay desacuerdo texto-valor (p.ej. dice “normal” pero el número NO lo es), marca con:
   <sup class='flag' data-flag='(n)' data-tipo='incongruencia'></sup>
4) Unidades: mantén las unidades tal como en el dictado (cm, mm). Si falta la unidad, no inventes y marca:
   <sup class='flag' data-flag='(n)' data-tipo='falta_unidad'></sup>
5) No inventes datos ni motivos. No cambies el significado del dictado.

=== FORMATO DE SALIDA (obligatorio) ===
- Devuelve SOLO el informe en HTML (sin <html> ni <body>).
- Mantén la estructura y etiquetas de la PLANTILLA BASE.
- CONCLUSION:
  - SOLO incluir si el dictado trae una conclusión o la solicita explícitamente el dictante.
  - Si corresponde incluirla, formatear así:
    <p><strong>CONCLUSION:</strong><br>
    &nbsp;&nbsp;- …<br>
    </p>
- OBSERVACIONES:
  - Incluir SOLO si existen marcas (1)(2)...
  - Formato exacto:
    <p><strong>Observaciones del Asistente:</strong><br>
    (1) [data-tipo] → explicación concisa sin inventar.<br>
    (2) [data-tipo] → explicación concisa sin inventar.<br>
    </p>
- Para cada marca (1)(2)… coloca el <sup class='flag' data-flag='(n)' ...></sup> pegado al dato involucrado.
- La numeración (1)(2) debe coincidir entre el texto y las Observaciones.

— DISPARADORES DE MARCA (OBLIGATORIOS) —
A) Si el DICTADO usa palabras: aumentad*, engros*, disminuid*, 'pared ... aumentad*', 'relación ... aumentad*/disminuid*', debes colocar:
   <sup class='flag' data-flag='(n)' data-tipo='valor_sospechoso'></sup>
   - Si hay número (ej. '0,41 cm'), pega la marca inmediatamente después del número.
   - Si no hay número, pega la marca inmediatamente después de la palabra clave.

B) Si aparece un término clínico potencialmente confuso (ej.: 'abusados' para bordes/contornos):
   - Mantén el término literal en el texto.
   - Coloca inmediatamente después:
     <sup class='flag' data-flag='(n)' data-tipo='termino_confuso'></sup>

— CONSISTENCIA (OBLIGATORIA) —
- Si colocas al menos una marca <sup ...>, debes incluir la sección:
  <p><strong>Observaciones del Asistente:</strong><br>
  (1) [data-tipo] → explicación concisa sin inventar.<br>
  (2) [data-tipo] → explicación concisa sin inventar.<br>
  </p>
- La numeración (1)(2)… en Observaciones debe coincidir 1:1 con las marcas del texto.
- Si NO hay triggers ni incongruencias, NO coloques marcas ni Observaciones.


=== EJEMPLO DE MARCADO (no copiar valores, solo el estilo) ===
Texto: 'Imagen duodenal con grosor pared aumentada en 0,41 cm.'
Salida: 'Imagen duodenal con grosor pared aumentada en 0,41 cm<sup class='flag' data-flag='(1)' data-tipo='valor_sospechoso'></sup>.'
Observaciones:
(1) valor_sospechoso → texto reporta “aumentada” para la pared duodenal.

SIEMPRE que marques algo, usa EXACTAMENTE este formato:
<sup class='flag' data-flag='N' data-tipo='valor_sospechoso'>(N)</sup>
ó
<sup class='flag' data-flag='N' data-tipo='termino_confuso'>(N)</sup>

Reglas:
- Reemplaza N por 1,2,3… según el orden de aparición.
- El texto entre <sup> y </sup> DEBE ser (N). No lo dejes vacío.
- data-flag DEBE llevar solo el número sin paréntesis (ej: data-flag='3').

=== PLANTILLA BASE (no modificar etiquetas ni su orden) ===
<<<PLANTILLA_BASE
{{plantilla_base}}
PLANTILLA_BASE

=== TEXTOS DE EJEMPLO (solo estilo; NO copiar valores) ===
<<<EJEMPLOS
{{texto_ejemplos}}
EJEMPLOS

=== DICTADO DEL VETERINARIO (fuente de verdad clinica) ===
<<<DICTADO
{{texto}}
DICTADO


";
// Reemplazo de placeholders en el prompt (interpolación)
$prompt = strtr($prompt, [
    '{{especie}}'        => $especie,
    '{{raza}}'           => $raza,
    '{{edad}}'           => $edad,
    '{{sexo}}'           => $sexo,
    '{{tipo_estudio}}'   => $tipo_estudio,
    '{{motivo}}'         => $motivo,
    '{{plantilla_base}}' => $plantilla_base,
    '{{texto_ejemplos}}' => $texto_ejemplos,
    '{{texto}}'          => $texto,
]);

// Mantén UTF-8 tal cual (no desacentuar el prompt final)
$prompt = trim($prompt);




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
                        Eres un médico veterinario especialista en informes ecográficos.
                        Respeta la estructura HTML de la plantilla y NO elimines secciones existentes.
                        Transcribe de forma LITERAL los hallazgos clínicos y medidas del dictado.
                        No inventes datos ni motivos. No completes valores ausentes.
                        Solo transcribe contenido CLÍNICO. Excluye texto publicitario o no clínico.
                        Si un término clínico es confuso, mantenlo literal y márcalo con (n) como "termino_confuso".
                        Si un valor es sospechoso, márcalo SIEMPRE como "valor_sospechoso".
                        Marca "incongruencia" SOLO cuando el texto dice "normal" pero el valor NO lo es (o viceversa).
                        No cambies unidades ni el formato de los números (punto/coma da igual).
                        La sección CONCLUSIÓN SOLO debe aparecer si el dictado la incluye o la solicita explícitamente.
                        Salida: ÚNICAMENTE el HTML del informe (sin <html> ni <body>).
                        '],
        ['role' => 'user',   'content' => $prompt]
    ],
    'temperature' => 0.2,
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
// === Post-procesado simple: validación flags/observaciones ===
if (preg_match_all('/data-flag=["\']\((\d+)\)["\']/', $content, $m_flags)) {
    $flags = array_unique($m_flags[1]);
    if (!empty($flags)) {
        if (!preg_match("/<strong>Observaciones del Asistente:<\\/strong>/i", $content)) {
            // Si hay flags pero no observaciones, forzamos sección vacía
            $obs = "<p><strong>Observaciones del Asistente:</strong><br>";
            foreach ($flags as $f) {
                $obs .= "($f) [sin explicación generada por el modelo]<br>";
            }
            $obs .= "</p>";
            $content .= $obs;
        }
    }
}

if (!$incluir_conclusion) {
    // Quita un bloque de CONCLUSION / CONCLUSIÓN con posible espacio/saltos de línea
    $content = preg_replace(
        '#<p>\s*<strong>\s*CONCLUSI(Ó|O)N:\s*</strong>\s*<br>\s*.*?</p>#is',
        '',
        $content
    );
}

// === Inyecta CSS de flags si hay al menos un <sup class="flag"> y aún no hay estilos ===
if (preg_match('/class=["\']flag["\']/i', $content)) {
    $flagCss = <<<HTML
    <style>
    .flag{font-weight:600;}
    .flag[data-tipo="valor_sospechoso"]{color:#E67E22;}
    .flag[data-tipo="incongruencia"]{color:#C0392B;}
    .flag[data-tipo="termino_confuso"]{color:#8E44AD;}
    .flag[data-tipo="falta_unidad"]{color:#D35400;}
    </style>
    HTML;

    // Evita duplicar estilos si por casualidad ya vinieron
    if (!preg_match('#<style[^>]*>\s*\.flag#is', $content)) {
        $content = $flagCss . $content; // prepend
    }
}

// === Normaliza flags: numera en orden y asegura contenido (N) visible ===
$seq = 0;
$content = preg_replace_callback(
    '#<sup\b([^>]*)class=[\'"]flag[\'"]([^>]*)>(.*?)</sup>#is',
    function ($m) use (&$seq) {
        $seq++;
        $attrs = $m[1] . $m[2];
        $tipo = 'valor_sospechoso';
        if (preg_match('/data-tipo=["\']([^"\']+)["\']/i', $attrs, $mt)) {
            $tipo = $mt[1];
        }
        // reconstruye con data-flag="N" y texto visible (N)
        return "<sup class='flag' data-flag=\"{$seq}\" data-tipo=\"{$tipo}\">({$seq})</sup>";
    },
    $content
);




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

