<?php
declare(strict_types=1);

require_once("../funciones/conn/conn.php");
require_once("../configP.php");
require_once(__DIR__ . "/logs/logger.php");
date_default_timezone_set('America/Santiago');

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


$system = '
Eres un médico veterinario especialista en informes ecográficos.
DEBES devolver ÚNICAMENTE el HTML del informe (sin <html> ni <body>), siguiendo EXACTAMENTE la PLANTILLA BASE.
PROHIBIDO incluir placeholders o variables (ej: ${...}, {{...}}, ctx/num, etc.). Si falta un dato, NO inventes ni añadas tokens.
NO incluyas <style> ni CSS. NO cambies el orden ni elimines secciones de la PLANTILLA BASE.

REGLAS DE FLAGS (OBLIGATORIO):
- "valor_sospechoso" ante: aumentad*, engros*, disminuid*, o cuando haya flechas ↑/↓. NO marques "relación" si está conservada y NO hay flecha.
- Si hay número+unidad (cm o mm), el <sup class="flag"> va PEGADO DESPUÉS del número; si no hay número, va después de la palabra clave.
- "termino_confuso" solo para términos ambiguos (p.ej., "abusados").
- "falta_unidad" cuando haya medida sin cm/mm.
- "incongruencia" solo si conviven "conservado/normal" con un término sospechoso en la MISMA frase.
- No dupliques flags para el mismo dato. Máximo 1 flag por dato.

FORMATO FLAG:
- <sup class="flag" data-flag="N" data-tipo="TIPO">(N)</sup> con N=1,2,3… en orden de aparición.
- Asegura coherencia: el número visible (N) y data-flag="N" deben coincidir.

OBSERVACIONES DEL ASISTENTE:
- Incluye el bloque solo si hubo al menos 1 flag.
- UNA línea por flag, en orden, citando órgano si se menciona en negrita cerca del flag y la medida si existe.
- Ejemplo de formato de línea:
(N) valor_sospechoso → Imagen yeyunal: «engrosada» (0,52 cm). Revisar en contexto clínico.

TRANSCRIPCIÓN:
- Transcribe solo contenido CLÍNICO del DICTADO. Sin equipo, publicidad ni conversación.
- Respeta números y unidades tal como vienen (coma o punto). No completes valores ausentes.
- La CONCLUSIÓN solo si viene en el dictado o se pide explícitamente.
';


// === Construir prompt =======================================================
$prompt = "


REDACCION DE INFORME ECOGRAFICO VETERINARIO

Usa la siguiente PLANTILLA BASE como formato. Mantén sus etiquetas y su orden.
Rellena solo con contenido CLINICO proveniente del DICTADO.

=== CONTEXTO (no incluir en el informe) ===
Especie: {{especie}}
Raza: {{raza}}
Edad: {{edad}}
Sexo: {{sexo}}
Tipo de estudio: {{tipo_estudio}}
Motivo: {{motivo}}

=== TRANSCRIPCION (obligatorio) ===
- Transcribe literalmente hallazgos clínicos y medidas del DICTADO.
- Excluye TODO lo no clínico (equipo, publicidad, conversación, narración, etc.).
- Mantén unidades tal como se dictan (cm, mm). Si falta unidad: marca con flag.
- Respeta correcciones del dictante: prevalece SIEMPRE la última indicación (si dice “no incluir X”, elimínalo; si corrige un valor, usa el final).

=== MARCAS (flags) ===
OBLIGATORIO:
- Marcar SIEMPRE “valor_sospechoso” ante “aumentad* / engros* / disminuid* / relación (↑/↓)”, haya o no medida.
- Si hay medida, el flag va después del número; si no, después de la palabra clave.
Coloca un <sup class='flag' ...> pegado al dato:
- Valor sospechoso (p. ej. “aumentad* / engros* / disminuid* / relación ↑/↓”):
  <sup class='flag' data-flag='N' data-tipo='valor_sospechoso'>(N)</sup>
  • Si hay número (ej. “0,41 cm”), va justo después del número; si no, tras la palabra clave.
- Término potencialmente confuso (p. ej. “abusados” para bordes):
  <sup class='flag' data-flag='N' data-tipo='termino_confuso'>(N)</sup>
- Falta de unidad en una medida:
  <sup class='flag' data-flag='N' data-tipo='falta_unidad'>(N)</sup>
- Incongruencia texto–valor:
  <sup class='flag' data-flag='N' data-tipo='incongruencia'>(N)</sup>

Reglas de numeración:
- N = 1,2,3… en orden de aparición.
- El texto visible debe ser (N).
- data-flag lleva solo el número (sin paréntesis).

=== OBSERVACIONES (solo si hay flags) ===
Incluye exactamente:
<p><strong>Observaciones del Asistente:</strong><br>
(1) [data-tipo] → explicación concisa sin inventar.<br>
(2) [data-tipo] → explicación concisa sin inventar.<br>
</p>
La numeración debe coincidir 1:1 con las marcas del texto. Si no hay flags, no incluyas esta sección.

=== CONCLUSION ===
Solo si el dictado la trae o lo solicita explícitamente.
Formato:
<p><strong>CONCLUSION:</strong><br>
&nbsp;&nbsp;- …<br>
</p>

=== SALIDA (obligatorio) ===
- Devuelve SOLO el informe en HTML (sin <html> ni <body>).
- Mantén la estructura y etiquetas de la PLANTILLA BASE.
- No inventes datos ni motivos; no completes valores ausentes.

=== PLANTILLA BASE (no modificar) ===
<<<PLANTILLA_BASE
{{plantilla_base}}
PLANTILLA_BASE

=== EJEMPLOS (solo estilo; NO copiar valores) ===
<<<EJEMPLOS
{{texto_ejemplos}}
EJEMPLOS

=== DICTADO (fuente de verdad clínica) ===
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
        ['role' => 'system', 'content' => $system],
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

$content = (string)$result['choices'][0]['message']['content'];

if (defined('SANITIZE_HTML_OUTPUT') && SANITIZE_HTML_OUTPUT) {
    $content = sanitize_html_output($content);
}
if (!function_exists('auto_flag_valor_sospechoso')) {
    function auto_flag_valor_sospechoso(string $html): string {
        // Solo palabras de sospecha + flechas. NO marcamos "relación" sola.
        $kwPattern = '(?:aumentad\w*|engros\w*|disminuid\w*|↑|&uarr;|&#8593;|↓|&darr;|&#8595;)';
        $regex = '/\b(?P<kw>'.$kwPattern.')(?P<tail>(?:(?!<sup\b).){0,50})/iu';

        return preg_replace_callback($regex, function ($m) {
            $kw   = $m['kw'];
            $tail = $m['tail'];

            // Si ya hay un flag inmediato, no duplicar
            if (preg_match('/^\s*<sup[^>]*class=[\'"]flag[\'"]/i', $tail)) {
                return $m[0];
            }

            // ¿Hay número + unidad después de la keyword (pegamos flag al número)?
            if (preg_match('/(?P<num>\d+(?:[.,]\d+)?\s*(?:cm|mm))(?!(?:\s*<sup[^>]*class=[\'"]flag[\'"]))/iu', $tail, $mn, PREG_OFFSET_CAPTURE)) {
                $num     = $mn[0][0];
                $pos     = $mn[0][1];
                $before  = substr($tail, 0, $pos + strlen($num));
                $after   = substr($tail, $pos + strlen($num));
                $flag    = '<sup class="flag" data-tipo="valor_sospechoso">(0)</sup>';
                return $kw . $before . $flag . $after;
            }

            // Si no hay número cercano, flag en la keyword
            return $kw . '<sup class="flag" data-tipo="valor_sospechoso">(0)</sup>' . $tail;
        }, $html);
    }
}
if (!function_exists('auto_flag_numero_inverosimil')) {
    function auto_flag_numero_inverosimil(string $html): string {
        // 100 cm o más → sospechoso en este contexto clínico general
        $regex = '/(?P<num>\b\d{3,}(?:[.,]\d+)?\s*cm\b)(?!\s*<sup[^>]*class=[\'"]flag[\'"])/iu';
        return preg_replace_callback($regex, function($m){
            return $m['num'].'<sup class="flag" data-tipo="valor_sospechoso">(0)</sup>';
        }, $html);
    }
}
if (!function_exists('auto_flag_termino_confuso')) {
    function auto_flag_termino_confuso(string $html): string {
        // "abusados" → término potencialmente confuso
        return preg_replace(
            '/\b(abusados)\b(?!\s*<sup[^>]*class=[\'"]flag[\'"])/iu',
            '$1<sup class="flag" data-tipo="termino_confuso">(0)</sup>',
            $html
        );
    }
}
if (!function_exists('auto_flag_falta_unidad')) {
    function auto_flag_falta_unidad(string $html): string {
        // Contextos + hasta ~40 chars sin entrar a tags, luego número SIN unidad.
        // Grupo atómico (...) evita retroceder de "0,25" a "0".
        $regex = '/(?P<prefix>(?:grosor|pared|tama(?:ñ|&ntilde;)o|longitud|altura|ancho|di(?:á|&aacute;)metro)[^<]{0,40}?)(?P<num>(?>\d+(?:[.,]\d+)?))(?!\s*(?:cm|mm))/iu';

        return preg_replace_callback($regex, function($m){
            return $m['prefix'] . $m['num'] . '<sup class="flag" data-tipo="falta_unidad">(0)</sup>';
        }, $html);
    }
}
if (!function_exists('auto_flag_incongruencia_simple')) {
    function auto_flag_incongruencia_simple(string $html): string {
        // Orden: "conservado/normal" ... luego ... "aumentad*/engros*/disminuid*" en la misma frase (~60 chars máx)
        $r1 = '/\b(?P<norm>conservad\w*|normal)\b(?P<mid>[^\.]{0,60}?)\b(?P<sus>aumentad\w*|engros\w*|disminuid\w*)\b(?!\s*<sup[^>]*class=[\'"]flag[\'"])/iu';
        $html = preg_replace($r1, '${norm}${mid}${sus}<sup class="flag" data-tipo="incongruencia">(0)</sup>', $html);

        // Orden inverso: sospechoso ... luego ... conservado/normal en la misma frase
        $r2 = '/\b(?P<sus2>aumentad\w*|engros\w*|disminuid\w*)\b(?P<mid2>[^\.]{0,60}?)\b(?P<norm2>conservad\w*|normal)\b(?!\s*<sup[^>]*class=[\'"]flag[\'"])/iu';
        $html = preg_replace($r2, '${sus2}<sup class="flag" data-tipo="incongruencia">(0)</sup>${mid2}${norm2}', $html);

        return $html;
    }
}

// Ejecutar passes (el orden ayuda a ubicar bien las marcas)
// $content = auto_flag_valor_sospechoso($content);
// $content = auto_flag_termino_confuso($content);
// $content = auto_flag_falta_unidad($content);
// $content = auto_flag_incongruencia_simple($content);
// $content = auto_flag_numero_inverosimil($content);
// ===========================================================================
// OJO: No ponemos número aquí. Tu normalizador de flags más abajo asigna
// data-flag="N" y texto visible (N) en secuencia.

// === Post-procesado simple: validación flags/observaciones ===
// if (preg_match_all('/data-flag=["\']\(?(\d+)\)?["\']/', $content, $m_flags)) {
//     $flags = array_unique($m_flags[1]);
//     if (!empty($flags)) {
//         if (!preg_match("/<strong>Observaciones del Asistente:<\\/strong>/i", $content)) {
//             // Si hay flags pero no observaciones, forzamos sección vacía
//             $obs = "<p><strong>Observaciones del Asistente:</strong><br>";
//             foreach ($flags as $f) {
//                 $obs .= "($f) [sin explicación generada por el modelo]<br>";
//             }
//             $obs .= "</p>";
//             $content .= $obs;
//         }
//     }
// }

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

// === Normaliza flags: SIEMPRE reasigna numeración secuencial ===
$seq = 0;
$content = preg_replace_callback(
    '#<sup\b[^>]*class=[\'"]flag[\'"][^>]*>(.*?)</sup>#is',
    function ($m) use (&$seq) {
        $seq++;
        $tipo = 'valor_sospechoso';
        if (preg_match('/data-tipo=["\']([^"\']+)["\']/i', $m[0], $mt)) {
            $tipo = $mt[1];
        }
        return "<sup class='flag' data-flag=\"{$seq}\" data-tipo=\"{$tipo}\">({$seq})</sup>";
    },
    $content
);


// === Generador de "Observaciones del Asistente" (determinista) =============
// Reemplaza cualquier Observaciones existente por una nueva, consistente con las flags finales.
if (!function_exists('build_observaciones_asistente')) {
    function build_observaciones_asistente(string $html): string {
        // Quitar Observaciones previas si existen
        $html = preg_replace('#<p>\s*<strong>\s*Observaciones del Asistente:\s*</strong>.*?</p>#is', '', $html);

        // Si no hay ninguna flag, salir
        if (stripos($html, 'class="flag"') === false && stripos($html, "class='flag'") === false) {
            return $html;
        }

        // Capturar todas las flags con su offset para contexto
        if (!preg_match_all('#<sup\b[^>]*class=[\'"]flag[\'"][^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $len = strlen($html);
        $items = []; // cada item: ['n'=>int, 'tipo'=>string, 'organo'=>string, 'kw'=>string, 'numu'=>string]

        foreach ($m[0] as $match) {
            $supTag = $match[0];
            $pos    = $match[1];

            // Número y tipo
            $n = null; $tipo = 'valor_sospechoso';
            if (preg_match('/data-flag=["\'](\d+)["\']/i', $supTag, $mn)) {
                $n = (int)$mn[1];
            }
            if (preg_match('/data-tipo=["\']([^"\']+)["\']/i', $supTag, $mt)) {
                $tipo = strtolower($mt[1]);
            }
            if (!$n) { continue; }

            // Limites del párrafo donde está la flag
            $pStart = strrpos(substr($html, 0, $pos), '<p');
            if ($pStart === false) { $pStart = max(0, $pos - 300); }
            $pEnd = strpos($html, '</p>', $pos);
            if ($pEnd === false) { $pEnd = min($len, $pos + 300); }
            $para = substr($html, $pStart, $pEnd - $pStart);

            // --- hallar órgano (si el último <strong> es "derecha/izquierda", concatenar el anterior) ---
            $organo = '';
            $beforeFlag = substr($para, 0, $pos - $pStart);
            if (preg_match_all('#<strong>([^<]+)</strong>#i', $beforeFlag, $ms, PREG_OFFSET_CAPTURE)) {
                $last = trim(end($ms[1])[0]);
                if (preg_match('/^(derecha|izquierda)$/i', $last) && count($ms[1]) >= 2) {
                    $prev = trim($ms[1][count($ms[1]) - 2][0]);
                    $organo = $prev . ' ' . $last;  // ej: "Imagen renal derecha"
                } else {
                    $organo = $last;
                }
            }

            // --- ventana y posición relativa del flag ---
            $winStart = max(0, ($pos - $pStart) - 120);
            $flagRel  = ($pos - $pStart) - $winStart;  // pos relativa del flag en la ventana
            $win      = substr($para, $winStart, 240);

            // --- keyword clínica cercana ---
            $kw = '';
            if (preg_match('/(aumentad\w*|engros\w*|disminuid\w*|relaci(?:ón|&oacute;n)|abusados)/iu', $win, $mk)) {
                $kw = $mk[1];
            }

            // --- medida: elegir la MÁS CERCANA con unidad (cm|mm) ---
            $numu = '';
            if (preg_match_all('/\d+(?:[.,]\d+)?\s*(?:cm|mm)/iu', $win, $mmu, PREG_OFFSET_CAPTURE)) {
                $best = null; $bestDist = 1e9;
                foreach ($mmu[0] as $cand) {
                    $start = $cand[1];
                    $dist  = abs($start - $flagRel);
                    if ($dist < $bestDist) { $bestDist = $dist; $best = $cand[0]; }
                }
                if ($best !== null) { $numu = $best; }
            }

            // Si no hay medida con unidad y el flag es "falta_unidad", intenta número sin unidad (con grupo atómico para no cortar "0,25cm")
            if ($numu === '' && $tipo === 'falta_unidad') {
                if (preg_match_all('/(?>\d+(?:[.,]\d+)?)(?!\s*(?:cm|mm))/iu', $win, $mnu, PREG_OFFSET_CAPTURE)) {
                    // elegir el más cercano
                    $best = null; $bestDist = 1e9;
                    foreach ($mnu[0] as $cand) {
                        $start = $cand[1];
                        $dist  = abs($start - $flagRel);
                        if ($dist < $bestDist) { $bestDist = $dist; $best = $cand[0]; }
                    }
                    if ($best !== null) { $numu = $best; }
                }
            }


            $items[$n] = [
                'n'     => $n,
                'tipo'  => $tipo,
                'organo'=> $organo,
                'kw'    => $kw,
                'numu'  => $numu
            ];
        }

        if (empty($items)) {
            return $html;
        }

        ksort($items);
        $lines = [];
        foreach ($items as $it) {
            $n     = $it['n'];
            $tipo  = $it['tipo'];
            $org   = $it['organo'];
            $kw    = $it['kw'];
            $numu  = $it['numu'];

            $contexto = $org ? ($org . ': ') : '';

            switch ($tipo) {
                case 'termino_confuso':
                    $kwtxt = $kw ? "término potencialmente confuso «{$kw}»" : "término potencialmente confuso";
                    $lines[] = "($n) termino_confuso → {$contexto}{$kwtxt}. Confirma significado exacto.";
                    break;

                case 'falta_unidad':
                    $ntxt = $numu ? " «{$numu}»" : "";
                    $lines[] = "($n) falta_unidad → {$contexto}medida sin unidad (cm/mm){$ntxt}. Agrega la unidad correspondiente.";
                    break;

                case 'incongruencia':
                    $kwtxt = $kw ? "«{$kw}»" : "hallazgos discordantes";
                    $lines[] = "($n) incongruencia → {$contexto}coexisten descriptores de normalidad/conservación y {$kwtxt} en la misma frase; revisar consistencia con valores reportados.";
                    break;

                // valor_sospechoso u otros
                default:
                    $kwtxt = $kw ? "se reporta «{$kw}»" : "hallazgo sospechoso";
                    if ($numu) {
                        // Si trae unidad, la mostramos tal cual; si no, indicamos que faltaría unidad.
                        if (preg_match('/cm|mm/i', $numu)) {
                            $lines[] = "($n) valor_sospechoso → {$contexto}{$kwtxt} ({$numu}). Revisar en contexto clínico.";
                        } else {
                            $lines[] = "($n) valor_sospechoso → {$contexto}{$kwtxt} con medida «{$numu}» sin unidad. Confirmar unidad (cm/mm).";
                        }
                    } else {
                        $lines[] = "($n) valor_sospechoso → {$contexto}{$kwtxt} sin medida. Considerar consignar medida si aplica.";
                    }
                    break;
            }
        }

        // Insertar bloque final
        if (!empty($lines)) {
            $obs = "<p><strong>Observaciones del Asistente:</strong><br>\n" .
                   implode("<br>\n", $lines) .
                   "<br>\n</p>";
            $html .= $obs;
        }
        return $html;
    }
}

// Gate ligero: solo si hay <sup class="flag">, entonces construimos Observaciones
if (stripos($content, 'class="flag"') !== false || stripos($content, "class='flag'") !== false) {
    $content = build_observaciones_asistente($content);
}
// ===========================================================================





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


