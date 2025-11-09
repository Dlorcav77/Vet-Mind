<?php
declare(strict_types=1);

// ahora en /funciones/GPT
$ROOT_DIR = dirname(__DIR__, 2);   // /
$FUNC_DIR = dirname(__DIR__);      // /funciones

require_once($FUNC_DIR . "/conn/conn.php");
require_once($ROOT_DIR . "/configP.php");
require_once($FUNC_DIR . "/logs/logger.php");
require_once($FUNC_DIR . "/data/ref_ranges.php");
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


$system = <<<'SYS'
Eres un médico veterinario especialista en informes ecográficos.

SALIDA (OBLIGATORIO)
- Devuelve SOLO el fragmento HTML del informe (sin <html> ni <body>), usando EXACTAMENTE la PLANTILLA BASE (orden/etiquetas intocables).
- No incluyas CSS, JS, iframes ni estilos inline. No uses Markdown ni fences.
- No inventes datos; elimina secciones/campos de la plantilla que no apliquen.

TRANSCRIPCIÓN
- Transcribe SOLO contenido CLÍNICO del DICTADO (sin equipo/publicidad/conversación).
- Respeta números y unidades tal cual (coma o punto). No completes valores ausentes.
- CONCLUSIÓN: 
  - Inclúyela si el dictado la trae o si explícitamente pide que la redactes; si no, omítela.
  - Si viene con otro formato, adapta el contenido al formato “CONCLUSION” de la plantilla sin alterar su sentido.

FLAGS (OBLIGATORIO dentro del texto)
- Marca con <sup class="flag" data-flag="N" data-tipo="TIPO">(N)</sup>, con N=1,2,3… por orden de aparición (coherentes (N) y data-flag).
- Ubicación: si hay número+unidad (cm|mm|mt), el flag va PEGADO DESPUÉS del número; si no hay número, va PEGADO DESPUÉS de la palabra clave.
- Tipos activos:
  - valor_sospechoso: SOLO magnitudes obviamente absurdas (p. ej., 200 cm un riñón, 0.0003 mm) o flechas ↑/↓ explícitas.
  - falta_unidad: medida SIN cm/mm/mt (con o sin espacio).
  - termino_confuso: términos ambiguos o fuera de contexto (p. ej., “abusados”).
  - incongruencia: SOLO si hay datos del paciente suficientes (p. ej., especie/raza/edad) y estás SEGURO de que el valor contradice el texto (“normal/aumentado”). Si no hay certeza, NO marques.
- No dupliques flags sobre el mismo dato (máx. 1 flag por dato). No marques “relación” si está conservada y no hay flechas.

OBSERVACIONES DEL ASISTENTE
- Genera observaciones SOLO para:
  - incongruencia (cuando haya datos suficientes y certeza),
  - termino_confuso no listado (no-lexical).
- NO generes observaciones para:
  - falta_unidad, ni
  - valor_sospechoso por magnitud absurda o flechas.
- Formato OBLIGATORIO del bloque:
  <p><strong>Observaciones del Asistente:</strong><br>
  (N) TIPO → texto corto y clínicamente útil (menciona órgano si aparece en <strong> cerca, y la medida si existe).<br>
  ... (una línea por flag interpretativo)
  </p>
- Cada línea debe empezar EXACTAMENTE con: (N) termino_confuso → ...  o  (N) incongruencia → ...
  • Usa esos literales exactos en minúsculas y sin tildes. NO uses sinónimos ni otros tipos.
- Si no hay observaciones válidas, NO incluyas el bloque.
- Sé conciso: 1 línea por flag relevante.
SYS;



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

Reglas de numeración:
- N = 1,2,3… en orden de aparición.
- El texto visible debe ser (N).
- data-flag lleva solo el número (sin paréntesis).

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

=== CHECKLIST ANTES DE RESPONDER (obligatorio) ===
- PLANTILLA: ¿respetaste el orden y las etiquetas sin añadir/quitar secciones?
- CLINICO: ¿incluiste solo hallazgos clínicos? ¿respetaste números y unidades tal cual?
- FLAGS: ¿usaste tipos y ubicación según SYSTEM? ¿sin duplicados? ¿sin marcar 'relación' si está conservada y sin flechas?
- INCONGRUENCIA: solo si hay datos suficientes del paciente y certeza (si no, NO marcar).
- OBSERVACIONES: solo para 'termino_confuso' e 'incongruencia'. Cada línea debe empezar EXACTAMENTE con (N) tipo → ...
- CONCLUSION: ¿solo si viene o se pidió explícitamente?
- SALIDA: ¿fragmento HTML sin <html>/<body> ni CSS/JS/Markdown?

";
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
$prompt = trim($prompt);


// === API Key OpenAI ========================================================
$api_key = $OPENAI_API_KEY ?? '';
if (!$api_key) {
    echo json_encode(['status' => 'error', 'message' => 'API Key de OpenAI no configurada.']);
    exit;
}

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




// === Modelo ================================================================
// $model = 'gpt-4o-mini';// in 0.15  out 0.60         
$model = 'gpt-4o';     // in 2.50  out 10'
// $model = 'gpt-5-nano';    // in 0.05  out 0.40
// $model = 'gpt-5-mini'; // in 0.25  out 2   
// $model = 'gpt-5';      // in 1.25  out 10

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
    'temperature' => 0.1,
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

// Léxico fijo para termino_confuso (puedes ir agregando)
$LEX_TERMINO_CONFUSO = ['abusados'];


if (!function_exists('parse_observaciones_ia')) {
    function parse_observaciones_ia(string $html): array {
        // Devuelve un mapa por tipo, en orden de aparición: ['incongruencia' => [linea1, linea2...], 'termino_confuso' => [...]]
        $result = ['incongruencia' => [], 'termino_confuso' => [], 'falta_unidad' => [], 'valor_sospechoso' => []];

        if (preg_match('#<p>\s*<strong>\s*Observaciones del Asistente:\s*</strong>\s*<br>\s*(.*?)\s*</p>#is', $html, $m)) {
            $block = $m[1];
            $lines = preg_split('#<br>\s*#i', $block);
            foreach ($lines as $line) {
                $plain = trim(strip_tags($line));
                if ($plain === '') continue;

                // Detecta tipo por palabra clave dentro de la línea
                $tipo = null;
                foreach (array_keys($result) as $t) {
                    if (preg_match('/\b' . preg_quote($t, '/') . '\b/i', $plain)) { $tipo = strtolower($t); break; }
                }
                if (!$tipo) continue;
                // Elimina prefijo "(N) " si existe; dejamos solo el contenido
                $plain = preg_replace('/^\(\d+\)\s*/', '', $plain);
                $result[$tipo][] = $plain;
            }
        }
        return $result;
    }
}

if (!function_exists('build_observaciones_asistente')) {
    function build_observaciones_asistente(string $html, array $ctx = [], array $lex = []): string {
        // Extrae y guarda observaciones IA por tipo (para mezclar)
        $obsIAByType = parse_observaciones_ia($html);

        // Quita cualquier Observaciones previa (IA o backend)
        $html = preg_replace('#<p>\s*<strong>\s*Observaciones del Asistente:\s*</strong>.*?</p>#is', '', $html);

        // Si no hay flags, salir
        if (stripos($html, 'class="flag"') === false && stripos($html, "class='flag'") === false) {
            return $html;
        }

        // ¿Tenemos contexto mínimo?
        $hasCtx = (bool) (trim((string)($ctx['especie'] ?? '')) !== '' || trim((string)($ctx['raza'] ?? '')) !== '' || trim((string)($ctx['edad'] ?? '')) !== '');

        // Capturar todas las flags con su offset para contexto (igual que tu versión)
        if (!preg_match_all('#<sup\b[^>]*class=[\'"]flag[\'"][^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $len = strlen($html);
        $items = []; // ['n'=>int,'tipo'=>string,'organo'=>string,'kw'=>string,'numu'=>string]

        foreach ($m[0] as $match) {
            $supTag = $match[0];
            $pos    = (int)$match[1]; 

            // Número y tipo
            $n = null; $tipo = 'valor_sospechoso';
            if (preg_match('/data-flag=["\'](\d+)["\']/i', $supTag, $mn)) {
                $n = (int)$mn[1];
            }
            if (preg_match('/data-tipo=["\']([^"\']+)["\']/i', $supTag, $mt)) {
                $tipo = strtolower($mt[1]);
            }
            if (!$n) { continue; }

            // Límites del párrafo donde está la flag
            $pStart = strrpos(substr($html, 0, $pos), '<p');
            if ($pStart === false) { $pStart = max(0, $pos - 300); }
            $pEnd = strpos($html, '</p>', $pos);
            if ($pEnd === false) { $pEnd = min($len, $pos + 300); }
            $para = substr($html, $pStart, $pEnd - $pStart);

            // Órgano (mismo método que tienes)
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

            // Ventana de texto
            $winStart = max(0, ($pos - $pStart) - 120);
            $flagRel  = ($pos - $pStart) - $winStart;
            $win      = substr($para, $winStart, 240);

            // Keyword cercana
            $kw = '';
            if (preg_match('/(aumentad\w*|engros\w*|disminuid\w*|relaci(?:ón|&oacute;n)|abusados|↑|↓)/iu', $win, $mk)) {
                $kw = $mk[1];
            }

            // Medida con unidad más cercana
            $numu = '';
            if (preg_match_all('/\d+(?:[.,]\d+)?\s*(?:cm|mm|mt)/iu', $win, $mmu, PREG_OFFSET_CAPTURE)) {
                $best = null; $bestDist = 1e9;
                foreach ($mmu[0] as $cand) {
                    $start = $cand[1];
                    $dist  = abs($start - $flagRel);
                    if ($dist < $bestDist) { $bestDist = $dist; $best = $cand[0]; }
                }
                if ($best !== null) { $numu = $best; }
            }
            // Número sin unidad si aplica
            if ($numu === '' && $tipo === 'falta_unidad') {
                if (preg_match_all('/(?>\d+(?:[.,]\d+)?)(?!\s*(?:cm|mm|mt))/iu', $win, $mnu, PREG_OFFSET_CAPTURE)) {
                    $best = null; $bestDist = 1e9;
                    foreach ($mnu[0] as $cand) {
                        $start = $cand[1];
                        $dist  = abs($start - $flagRel);
                        if ($dist < $bestDist) { $bestDist = $dist; $best = $cand[0]; }
                    }
                    if ($best !== null) { $numu = $best; }
                }
            }

            $items[] = ['n'=>$n,'tipo'=>$tipo,'organo'=>$organo,'kw'=>$kw,'numu'=>$numu];
        }

        // Construir líneas finales mezclando IA + backend
        $lines = [];
        $lexConf = array_map('mb_strtolower', $lex['termino_confuso'] ?? []);

        // Helpers
        $isLexConf = function(string $kw) use ($lexConf): bool {
            return ($kw !== '' && in_array(mb_strtolower($kw), $lexConf, true));
        };

        // Colas de IA solo para tipos interpretativos:
        $iaQueue = [
            'incongruencia'   => $obsIAByType['incongruencia']   ?? [],
            'termino_confuso' => $obsIAByType['termino_confuso'] ?? [],
        ];
        $shiftIA = function(string $tipo) use (&$iaQueue) {
            if (!isset($iaQueue[$tipo]) || empty($iaQueue[$tipo])) return null;
            return array_shift($iaQueue[$tipo]); // devuelve texto IA sin "(N) "
        };

        // Render por item
        usort($items, fn($a,$b)=>$a['n']<=>$b['n']);
        foreach ($items as $it) {
            $n    = $it['n'];
            $tipo = $it['tipo'];
            $ctxo = $it['organo'] ? ($it['organo'] . ': ') : '';
            $kw   = $it['kw'];
            $numu = $it['numu'];

            switch ($tipo) {
                case 'falta_unidad':
                    $ntxt = $numu ? " «{$numu}»" : "";
                    $lines[] = "($n) falta_unidad → {$ctxo}medida sin unidad (cm/mm/mt){$ntxt}. Agrega la unidad correspondiente.";
                    break;

                case 'valor_sospechoso':
                    // Estándar mecánico (absurdo/flechas), no usamos IA
                    if ($numu) {
                        $lines[] = "($n) valor_sospechoso → {$ctxo}magnitud/indicador inusual ({$numu}). Revisar si hay error de escala o digitación.";
                    } else {
                        $lines[] = "($n) valor_sospechoso → {$ctxo}hallazgo marcado por flechas/descriptor. Revisar en contexto clínico.";
                    }
                    break;

                case 'termino_confuso':
                    if ($isLexConf($kw)) {
                        $lines[] = "($n) termino_confuso → {$ctxo}término potencialmente confuso «{$kw}». Confirma significado exacto.";
                    } else {
                        $ia = $shiftIA('termino_confuso');
                        if ($ia) {
                            // Usa texto de IA tal cual después de "→"
                            // Si IA no incluyó órgano, ya lo aportamos en ctxo
                            $lines[] = "($n) termino_confuso → {$ctxo}" . preg_replace('/^[a-z_]+\s*→\s*/i','',$ia);
                        } else {
                            $kwtxt = $kw ? "«{$kw}»" : "término ambiguo";
                            $lines[] = "($n) termino_confuso → {$ctxo}{$kwtxt}. Confirma significado exacto.";
                        }
                    }
                    break;

                case 'incongruencia':
                    if (!$hasCtx) { /* sin contexto suficiente, omitir */ break; }
                    $ia = $shiftIA('incongruencia');
                    if ($ia) {
                        $lines[] = "($n) incongruencia → {$ctxo}" . preg_replace('/^[a-z_]+\s*→\s*/i','',$ia);
                    } else {
                        $kwtxt = $kw ? "«{$kw}»" : "hallazgos discordantes";
                        $add   = $numu ? " ({$numu})" : "";
                        $lines[] = "($n) incongruencia → {$ctxo}coexisten descriptores y valores potencialmente discordantes {$kwtxt}{$add}. Revisar consistencia clínica.";
                    }
                    break;

                default:
                    // Por si viene un tipo inesperado
                    $lines[] = "($n) {$tipo} → {$ctxo}revisar hallazgo reportado" . ($numu ? " ({$numu})" : "") . ".";
                    break;
            }
        }

        if (!empty($lines)) {
            $obs = "<p><strong>Observaciones del Asistente:</strong><br>\n" .
                   implode("<br>\n", $lines) .
                   "<br>\n</p>";
            $html .= $obs;
        }
        return $html;
    }
}


// 1) (ya lo tienes)
if (!$incluir_conclusion) {
    $content = preg_replace('#<p>\s*<strong>\s*CONCLUSI(Ó|O)N:\s*</strong>\s*<br>\s*.*?</p>#is', '', $content);
}

// 2) Renumerar
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

// 3) Observaciones (solo una vez, con contexto)
$contextoPaciente = ['especie' => $especie, 'raza' => $raza, 'edad' => $edad];
$lexicos = ['termino_confuso' => $LEX_TERMINO_CONFUSO];
if (stripos($content, 'class="flag"') !== false || stripos($content, "class='flag'") !== false) {
    $content = build_observaciones_asistente($content, $contextoPaciente, $lexicos);
}

// 4) Inyectar CSS (puede ir antes o después; aquí después de obs)
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
    if (!preg_match('#<style[^>]*>\s*\.flag#is', $content)) {
        $content = $flagCss . $content;
    }
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


