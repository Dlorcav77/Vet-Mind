<?php
declare(strict_types=1);

require_once("../funciones/conn/conn.php");
require_once("../configP.php");
require_once(__DIR__ . "/logs/logger.php");
require_once("../funciones/data/ref_ranges.php");
require_once(__DIR__ . '/../funciones/validaciones/validador_morfo.php');

date_default_timezone_set('America/Santiago');




// === DEBUG SWITCH ===========================================================
// Fuerza debug desde servidor (sin tocar el front)
const DEBUG_GPT = false; // ← ponlo en true para no llamar a la API

// También permitimos activar por petición (útil para pruebas puntuales desde el front)
function is_dry_run_enabled(): bool {
    if (DEBUG_GPT === true) return true;
    if (isset($_POST['debug_only']) && $_POST['debug_only'] === '1') return true;
    if (isset($_GET['debug_only'])  && $_GET['debug_only']  === '1') return true;
    if (isset($_GET['dry_run'])     && $_GET['dry_run']     === '1') return true;
    return false;
}



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

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function clip(string $s, int $max = 8000): string {
    return (strlen($s) <= $max) ? $s : (substr($s, 0, $max) . "\n...[cortado]...");
}

/**
 * Renderiza el bloque <details> con el JSON de medidas (pretty-print).
 */
function build_medidas_json_details($medidas_pack): string {
    $json = json_encode($medidas_pack, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $json_safe = htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return <<<HTML
<details id="medidas-json" style="margin-top:8px;">
  <summary><strong>Datos estructurados (IA)</strong> — MEDIDAS_JSON</summary>
  <pre style="white-space:pre-wrap;margin:6px 0 0 0;">{$json_safe}</pre>
</details>
HTML;
}


/**
 * Inserta el bloque justo debajo de "Validaciones del Sistema".
 * - Si encuentra una lista <ul>/<ol> inmediatamente después, lo pone tras la lista.
 * - Si no encuentra el ancla, lo agrega al final.
 * - Evita duplicados si ya existe un <details id="medidas-json">.
 */
function inject_medidas_json_below_validaciones(string $html, string $block): string {
    // Quitar versión previa si ya existía (evitar duplicados al re-procesar)
    $html = preg_replace('#<details[^>]*id=["\']medidas-json["\'][\s\S]*?</details>#i', '', $html);

    // 1) Buscar <p><strong>Validaciones del Sistema</strong>...</p> (insensible a mayúsculas/acentos)
    if (preg_match('#(<p[^>]*>\s*<strong>\s*Validaciones\s+del\s+Sistema\s*:?\s*</strong>.*?</p>\s*)(<ul[^>]*>.*?</ul>\s*|<ol[^>]*>.*?</ol>\s*)?#is', $html, $m, PREG_OFFSET_CAPTURE)) {
        // Elegimos insertar después de la lista si existe; si no, después del <p> del título.
        $insertPos = isset($m[2]) && $m[2][1] !== -1
            ? $m[2][1] + strlen($m[2][0])
            : $m[1][1] + strlen($m[1][0]);

        return substr($html, 0, $insertPos) . $block . substr($html, $insertPos);
    }

    // 2) Fallback: buscar cualquier nodo que contenga el literal
    if (preg_match('#(Validaciones\s+del\s+Sistema\s*:?.*?</p>)#is', $html, $m2, PREG_OFFSET_CAPTURE)) {
        $insertPos = $m2[0][1] + strlen($m2[0][0]);
        return substr($html, 0, $insertPos) . $block . substr($html, $insertPos);
    }

    // 3) Último recurso: agregar al final
    return $html . $block;
}

function organo_canonico_map(): array {
    // alias en minúsculas => nombre canónico
    return [
        'imagen vesical' => 'Vejiga',
        'vesicula biliar' => 'Vesícula biliar',
        'vesícula biliar' => 'Vesícula biliar',
        'imagen renal izquierda' => 'Riñón izquierdo',
        'riñon izquierdo' => 'Riñón izquierdo',
        'riñón izquierdo' => 'Riñón izquierdo',
        'imagen renal derecha' => 'Riñón derecho',
        'riñon derecho' => 'Riñón derecho',
        'riñón derecho' => 'Riñón derecho',
        'imagen esplenica' => 'Bazo',
        'imagen esplénica' => 'Bazo',
        'bazo' => 'Bazo',
        'imagen gastrica' => 'Estómago (antro)',
        'imagen gástrica' => 'Estómago (antro)',
        'estomago (antro)' => 'Estómago (antro)',
        'estómago (antro)' => 'Estómago (antro)',
        'duodeno' => 'Duodeno',
        'yeyuno' => 'Yeyuno',
        'colon' => 'Colon',
        'imagen hepatica' => 'Hígado',
        'imagen hepática' => 'Hígado',
        'higado' => 'Hígado',
        'hígado' => 'Hígado',
        'imagen pancreatica' => 'Páncreas',
        'imagen pancreática' => 'Páncreas',
        'pancreas' => 'Páncreas',
        'páncreas' => 'Páncreas',
        'adrenales' => null, // separamos en izq/der si aparece el lado
        'adrenal izquierda' => 'Adrenal izquierda',
        'adrenal derecha' => 'Adrenal derecha',
        'linfonodulos' => 'Linfonódulos',
        'linfonódulos' => 'Linfonódulos',
        'peritoneo' => 'Peritoneo',
        'mesenterio' => 'Mesenterio',
        'vasculatura' => 'Vasculatura',
        'ureter proximal izquierdo' => 'Uréter proximal izquierdo',
        'uréter proximal izquierdo' => 'Uréter proximal izquierdo',
        'ureter proximal derecho' => 'Uréter proximal derecho',
        'uréter proximal derecho' => 'Uréter proximal derecho',
        'prostata' => 'Próstata',
        'próstata' => 'Próstata',
        'vejiga' => 'Vejiga',
    ];
}

function backfill_organos_mencionados(array $medidas_json = null, string $texto = '', string $html = ''): array {
    $can = [];
    if ($medidas_json && isset($medidas_json['organos_mencionados']) && is_array($medidas_json['organos_mencionados'])) {
        foreach ($medidas_json['organos_mencionados'] as $o) { $can[$o] = true; }
    }
    $map = organo_canonico_map();

    $src = mb_strtolower($texto . ' ' . strip_tags($html), 'UTF-8');
    foreach ($map as $alias => $canon) {
        if ($canon === null) continue;
        if (mb_strpos($src, $alias) !== false) {
            $can[$canon] = true;
        }
    }
    // Casos: "imágenes adrenales no evaluable" => si menciona "adrenales", añade ambas solo si aparecen lados explícitos
    if (mb_strpos($src, 'adrenales') !== false) {
        if (mb_strpos($src, 'izquierd') !== false) { $can['Adrenal izquierda'] = true; }
        if (mb_strpos($src, 'derech') !== false)   { $can['Adrenal derecha'] = true; }
        // si no especifica lado, no asumimos una u otra
    }

    return ['organos_mencionados' => array_keys($can)];
}




// === Preparar datos =========================================================
$paciente       = limpiar_acentos(trim((string)($_POST['paciente'] ?? '')));
$especie        = limpiar_acentos(trim((string)($_POST['especie'] ?? '')));
$raza           = limpiar_acentos(trim((string)($_POST['raza'] ?? '')));
// $edad           = limpiar_acentos(trim((string)($_POST['edad'] ?? '')));
$edad = limpiar_acentos(trim((string)($_POST['fecha_nacimiento'] ?? '')));
$sexo           = limpiar_acentos(trim((string)($_POST['sexo'] ?? '')));
$tipo_estudio   = limpiar_acentos(trim((string)($_POST['tipo_estudio'] ?? '')));
$motivo         = limpiar_acentos(trim((string)($_POST['motivo_examen'] ?? '')));
$plantilla_base = trim((string)($_POST['plantilla_base'] ?? ''));
$texto          = trim((string)($_POST['texto'] ?? '')); 

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

ETIQUETADO DE ÓRGANOS (OBLIGATORIO)
- Cuando el DICTADO mencione hallazgos o medidas de un órgano, envuelve el contenido en un párrafo:
  <p><strong>ÓRGANO CANÓNICO</strong>: …</p>
- Usa nombres canónicos (respetar mayúsculas/tildes):
  Riñón izquierdo | Riñón derecho | Vejiga | Próstata | Hígado | Vesícula biliar |
  Bazo | Estómago (antro) | Duodeno | Yeyuno | Íleon | Colon |
  Páncreas | Adrenal izquierda | Adrenal derecha | Uréter proximal izquierdo | Uréter proximal derecho |
  Peritoneo | Mesenterio | Linfonódulos | Vasculatura
- Un órgano por párrafo. Si hay múltiples medidas del mismo órgano, agrúpalas en el mismo <p>.
- NO inventes órganos ni muevas medidas entre órganos. Si el órgano no es claro, NO crees sección.
- No marques secciones vacías: solo crear el párrafo si hay hallazgo/medida clínicamente relevante.

EXTRACCIÓN ESTRICTA (OBLIGATORIO)
- Debes detectar TODOS los órganos canónicos que aparezcan en el DICTADO, aunque no tengan medidas.
- Mapea términos/encabezados del dictado al nombre canónico:
  "Imagen esplénica"→"Bazo"; "Imagen gástrica"→"Estómago (antro)"; "Imagen hepática"→"Hígado";
  "Imagen pancreática"→"Páncreas"; "Imagen renal izquierda"→"Riñón izquierdo"; "Imagen renal derecha"→"Riñón derecho";
  "Vesícula biliar"→"Vesícula biliar"; "Linfonódulos"→"Linfonódulos"; "Peritoneo"→"Peritoneo"; "Mesenterio"→"Mesenterio"; "Colon"→"Colon"; "Duodeno"→"Duodeno"; "Yeyuno"→"Yeyuno".
- EXTRAER MEDIDAS: toma TODAS las expresiones numéricas con unidad cercana al órgano (ej.: “0,35cm”, “3.89 cm”, “0,7cm”).
  • Normaliza decimales con PUNTO (0,35 → 0.35).  
  • "valor" debe ser número (no string).  
  • "unidad" debe ser "cm" o "mm" según el texto.
  • No inventes unidades ni redondees; máximo 2 decimales si hace falta.
- Si hay múltiples valores del mismo subparámetro (p.ej. varios urolitos), incluye cada uno como un ítem separado en "medidas".
- SUBPARÁMETROS válidos (elige el que corresponda): tamano_global | urolito_diametro | grosor_pared | pelvis_renal | longitud | grosor | diametro | diametro_ureter | espesor_pared.
- AUDITORÍA (previa a responder):
  • Si aparece un órgano en el texto y no está en "organos_mencionados" → CORRIGE.
  • Si aparece una cifra con cm/mm y no está en "medidas" → CORRIGE.

FLAGS (OBLIGATORIO dentro del texto)
- Marca con <sup class="flag" data-flag="N" data-tipo="TIPO">(N)</sup>, con N=1,2,3… por orden de aparición (coherentes (N) y data-flag).
- Ubicación: si hay número+unidad (cm|mm|mt), el flag va PEGADO DESPUÉS del número; si no hay número, va PEGADO DESPUÉS de la palabra clave.
- Tipos activos:
  - termino_confuso: términos ambiguos o fuera de contexto (p. ej., “abusados”).
- No dupliques flags sobre el mismo dato (máx. 1 flag por dato). No marques “relación” si está conservada y no hay flechas.
- No marques 'termino_confuso' sobre números o medidas (p. ej., “0,25 cm”, “3.8 cm”), ni inmediatamente después de un número. Solo marcar sobre términos léxicos ambiguos.

OBSERVACIONES DEL ASISTENTE
- Genera observaciones SOLO para:
  - incongruencia (cuando haya datos suficientes y certeza),
  - termino confuso no listado (no-lexical).
- Formato OBLIGATORIO del bloque:
  <p><strong>Observaciones del Asistente:</strong><br>
  (N) TIPO → texto corto y clínicamente útil (menciona órgano si aparece en <strong> cerca, y la medida si existe).<br>
  ... (una línea por flag interpretativo)
  </p>
- Cada línea debe empezar EXACTAMENTE con: (N) termino_confuso → ...  o  (N) incongruencia → ...
  • Usa esos literales exactos en minúsculas y sin tildes. NO uses sinónimos ni otros tipos.
- Si no hay observaciones válidas, NO incluyas el bloque.
- Sé conciso: 1 línea por flag relevante.

- Además del HTML, debes devolver un bloque JSON entre <<<MEDIDAS_JSON ... MEDIDAS_JSON con medidas estructuradas.
- El JSON debe corresponder literalmente a las medidas que aparecen en el dictado (no inferir).

VERIFICACION JSON (OBLIGATORIO)
- Debes devolver SIEMPRE "organos_mencionados" como array con TODOS los órganos canónicos detectados en el dictado (aunque no tengan medidas).
- Si encuentras un órgano en el dictado y no está en "organos_mencionados", CORRIGE antes de responder.
- El bloque <<<MEDIDAS_JSON ...>>> debe ser JSON válido, sin comentarios ni comas colgantes. No devuelvas ejemplos ni texto adicional.
- Si no puedes cumplir EXACTAMENTE lo anterior, NO devuelvas HTML: responde ÚNICAMENTE el bloque MEDIDAS_JSON.
SYS;

$prompt = <<<'PROMPT'
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
- Cuando menciones un órgano con medidas/hallazgos, escribe en el informe: <p><strong>ÓRGANO CANÓNICO</strong>: …</p> (un órgano por párrafo; no inventar secciones).

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

=== SALIDA DOBLE (OBLIGATORIO) ===
1) Devuelve el INFORME en HTML.
2) Inmediatamente después, devuelve SOLO este bloque JSON, SIN texto extra:
<<<MEDIDAS_JSON
{
    "organos_mencionados": [],
    "medidas": []
}
MEDIDAS_JSON
Reglas:
- "organos_mencionados": lista SIN REPETIDOS de TODOS los órganos canónicos mencionados en el dictado, aunque no tengan medidas. Usa EXACTAMENTE estos nombres canónicos:
    Riñón izquierdo, Riñón derecho, Vejiga, Próstata, Hígado, Vesícula biliar, Bazo, Estómago (antro), Duodeno, Yeyuno, Íleon, Colon, Páncreas, Adrenal izquierda, Adrenal derecha, Uréter proximal izquierdo, Uréter proximal derecho, Peritoneo, Mesenterio, Linfonódulos, Vasculatura.
- "medidas": cada item con las claves:
    {"organo": "<canónico>", "subparametro": "<uno de: tamano_global|urolito_diametro|grosor_pared|pelvis_renal|longitud|grosor|diametro|diametro_ureter|espesor_pared>", "valor": <número decimal con punto>, "unidad": "cm"|"mm"}
- Mapea encabezados del dictado a canónicos (ej.: "Imagen esplénica"→"Bazo"; "Imagen gástrica"→"Estómago (antro)"; "Imagen vesical"→"Vejiga"; "Imagen hepática"→"Hígado"; "Imagen pancreática"→"Páncreas"; "Imagen renal izquierda"→"Riñón izquierdo"; "Imagen renal derecha"→"Riñón derecho").
- Incluye TODAS las cifras con cm/mm cercanas a cada órgano. No inventes.
- Si hubo órganos en el texto, "organos_mencionados" NO puede estar vacío.
- Si hubo cifras con cm/mm, TODAS deben aparecer en "medidas".
- Si no puedes cumplir EXACTAMENTE lo anterior, NO devuelvas HTML: responde SOLO el bloque MEDIDAS_JSON.
PROMPT;





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
$MISSING_KEY = !$api_key; 

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









// === DRY RUN (evita la llamada a OpenAI) ===================================
$DRY_RUN = is_dry_run_enabled() || $MISSING_KEY;

if ($DRY_RUN) {
    // Previews y JSON pretty
    $system_preview  = clip($system, 12000);

    $input_debug = [
        'paciente'            => $paciente,
        'especie'             => $especie,
        'raza'                => $raza,
        'edad'                => $edad,
        'sexo'                => $sexo,
        'tipo_estudio'        => $tipo_estudio,
        'motivo'              => $motivo,
        'plantilla_base_len'  => strlen($plantilla_base),
        'texto_len'           => strlen($texto),
        'ejemplos_count'      => count($ejemplos),
        'incluir_conclusion'  => $incluir_conclusion,
    ];

    $openai_payload_preview = [
        'model'       => $model,
        'temperature' => $payload['temperature'],
        'max_tokens'  => $payload['max_tokens'],
        'messages'    => [
            ['role' => 'system', 'content' => clip($system, 4000)],
            ['role' => 'user',   'content' => clip($prompt, 8000)],
        ],
        'approx_prompt_tokens' => approx_tokens($prompt),
        'user'        => $user_tag,
    ];

    $debug_html = '
        <style>
          .dbg pre{white-space:pre-wrap;margin:0}
          .dbg details{margin:.5rem 0;border:1px solid #eee;border-radius:.5rem;padding:.5rem}
          .dbg summary{cursor:pointer;font-weight:600}
          .badge{display:inline-block;background:#eef;padding:.1rem .5rem;border-radius:.5rem;border:1px solid #ccd;margin-left:.5rem}
        </style>
        <div class="dbg">
          <p><strong>DEBUG:</strong> Modo dry-run activo <span class="badge">'.h($model).'</span></p>

          <details open>
            <summary>Contexto del paciente</summary>
            <pre>'.h(json_encode($input_debug, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>
          </details>

          <details open>
            <summary>PLANTILLA BASE (longitud: '.strlen($plantilla_base).')</summary>
            <pre>'.h(clip($plantilla_base, 16000)).'</pre>
          </details>

          <details open>
            <summary>DICTADO / TEXTO (longitud: '.strlen($texto).')</summary>
            <pre>'.h(clip($texto, 16000)).'</pre>
          </details>

          <details>
            <summary>SYSTEM PROMPT</summary>
            <pre>'.h($system_preview).'</pre>
          </details>

          <details>
            <summary>Payload (preview)</summary>
            <pre>'.h(json_encode($openai_payload_preview, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>
          </details>
        </div>
    ';

    $content_demo = ''
      . '<h4>DEBUG: Modo dry-run activo (' . h($model) . ')</h4>'
      . '<h5>Contexto del paciente</h5>'
      . '<pre>' . h(json_encode($input_debug, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) . '</pre>'
      . '<h5>PLANTILLA BASE (len: ' . strlen($plantilla_base) . ')</h5>'
      . '<pre>' . h(clip($plantilla_base, 16000)) . '</pre>'
      . '<h5>DICTADO / TEXTO (len: ' . strlen($texto) . ')</h5>'
      . '<pre>' . h(clip($texto, 16000)) . '</pre>'
      . '<h5>SYSTEM PROMPT</h5>'
      . '<pre>' . h(clip($system, 4000)) . '</pre>'
      . '<h5>Payload (preview)</h5>'
      . '<pre>' . h(json_encode($openai_payload_preview, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) . '</pre>';

    app_log('dry_run', [
        'rid'           => $rid,
        'model'         => $model,
        'prompt_bytes'  => strlen($prompt),
        'approx_tokens' => approx_tokens($prompt),
        'reason'        => $MISSING_KEY ? 'missing_api_key' : 'manual_debug'
    ], 'INFO');

    echo json_encode([
        'status'         => 'dry_run',
        'rid'            => $rid,
        'model'          => $model,
        'input'          => $input_debug,
        'openai_payload' => $openai_payload_preview,
        'debug_html'     => $debug_html,   // HTML “bonito”
        'content_demo'   => $content_demo  // HTML “simple” (CKEditor-safe)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($mysqli instanceof mysqli) { @$mysqli->close(); }
    exit;
}














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


$medidas_json = null;
$decoded_full = null;

if (preg_match('#<<<MEDIDAS_JSON\s*(\{.*?\})\s*MEDIDAS_JSON#is', $content, $m)) {
    $json_raw = trim($m[1]);

    // Intento A
    $decoded_full = json_decode($json_raw, true);

    // Intento B (fix comillas simples) si falló
    if (!is_array($decoded_full)) {
        $json_fix = preg_replace_callback(
            "#'([^'\\\\]|\\\\.)*'#",
            fn($mm) => '"' . str_replace(['\\"','"'], ['"','\"'], substr($mm[0],1,-1)) . '"',
            $json_raw
        );
        $decoded_full = json_decode($json_fix, true);
        if (is_array($decoded_full)) {
            app_log('info', ['rid'=>$rid,'at'=>'medidas_json_ok_after_fix'], 'INFO');
            app_log_body('medidas_json_raw', ['rid'=>$rid, 'raw'=>$json_fix]);
        }
    } else {
        app_log('info', ['rid'=>$rid,'at'=>'medidas_json_ok'], 'INFO');
        app_log_body('medidas_json_raw', ['rid'=>$rid, 'raw'=>$json_raw]);
    }

    // Quita el bloque del HTML
    $content = preg_replace('#<<<MEDIDAS_JSON.*?MEDIDAS_JSON#is', '', $content);

    // Extrae medidas para compatibilidad con el resto
    if (isset($decoded_full['medidas']) && is_array($decoded_full['medidas'])) {
        $medidas_json = $decoded_full['medidas'];
    }
}

if ($decoded_full === null) {
    app_log('warn', ['rid'=>$rid,'at'=>'medidas_json_missing_first_pass'], 'WARN');
}









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

            // incluye U+00A0 literal
            $win_norm = str_replace("\xC2\xA0", ' ', $win);
            $win_norm = preg_replace('/(?:&nbsp;|&#160;)/i', ' ', $win_norm);


            // Keyword cercana (igual que antes, pero sobre win_norm)
            $kw = '';
            if (preg_match('/(aumentad\w*|engros\w*|disminuid\w*|relaci(?:ón|&oacute;n)|abusados|↑|↓)/iu', $win_norm, $mk)) {
                $kw = $mk[1];
            }

            // Medida con unidad más cercana (permite hasta 2 espacios, aunque \s* ya lo cubre)
            $numu = '';
            if (preg_match_all('/\d+(?:[.,]\d+)?\s{0,2}(?:cm|mm|mt)\b/iu', $win_norm, $mmu, PREG_OFFSET_CAPTURE)) {
                $best = null; $bestDist = 1e9;
                foreach ($mmu[0] as $cand) {
                    $start = $cand[1];
                    $dist  = abs($start - $flagRel);
                    if ($dist < $bestDist) { $bestDist = $dist; $best = $cand[0]; }
                }
                if ($best !== null) { $numu = $best; }
            }

            // Número sin unidad si aplica (solo si no encontramos unidad)
            if ($numu === '' && $tipo === 'falta_unidad') {
                if (preg_match_all('/(?>\d+(?:[.,]\d+)?)(?!\s{0,2}(?:cm|mm|mt)\b)/iu', $win_norm, $mnu, PREG_OFFSET_CAPTURE)) {
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
        $dropFlags = [];
        foreach ($items as $it) {
            $n    = $it['n'];
            $tipo = $it['tipo'];
            $ctxo = $it['organo'] ? ($it['organo'] . ': ') : '';
            $kw   = $it['kw'];
            $numu = $it['numu'];

            switch ($tipo) {
                case 'falta_unidad':
                    if ($numu && preg_match('/\b(cm|mm|mt)\b/i', $numu)) {
                        // falso positivo: marcó falta_unidad pero encontramos unidad
                        $dropFlags[] = $n; // ← marcar para eliminación
                        break;
                    }
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
                    // 1) Si está pegado a una MEDIDA → falso positivo, eliminar flag
                    if ($numu) {
                        $dropFlags[] = $n;
                        break;
                    }

                    // 2) Solo aceptamos términos en un LÉXICO explícito (p.ej. “abusados”)
                    if ($isLexConf($kw)) {
                        $lines[] = "($n) termino_confuso → {$ctxo}término potencialmente confuso «{$kw}». Confirma significado exacto.";
                        break;
                    }

                    // 3) (opcional) si viniera una línea explicativa de la IA, la usamos; si no, lo descartamos
                    $ia = $shiftIA('termino_confuso');
                    if ($ia) {
                        $lines[] = "($n) termino_confuso → {$ctxo}" . preg_replace('/^[a-z_]+\s*→\s*/i','',$ia);
                    } else {
                        // Antes: generabas “término ambiguo” por defecto → esto era el ruido.
                        // Ahora: lo descartamos completamente.
                        $dropFlags[] = $n;
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

        if (!empty($dropFlags)) {
            $pattern = '#<sup\b[^>]*class=[\'"]flag[\'"][^>]*data-flag=["\'](' . implode('|', array_map('intval',$dropFlags)) . ')[\'"][^>]*>\(\1\)</sup>#i';
            $html = preg_replace($pattern, '', $html);
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







$ctxValid = ['especie'=>$especie ?: '', 'raza'=>$raza ?: '', 'edad'=>$edad ?: ''];
$can_validate = ($ctxValid['especie'] !== '' && $ctxValid['raza'] !== '');

$has_strong = preg_match('#<strong>[^<]+</strong>#i', $content);
$recognized_organs = false;
if ($has_strong) {
    // chequeo rápido: ¿al menos un órgano canónico?
    $meds_probe = parser_extraer_medidas($content);
    foreach ($meds_probe as $m_) {
        if (canonizar_organo($m_['organo'] ?? '', $m_['contexto'] ?? null)) {
            $recognized_organs = true;
            break;
        }
    }
}

app_log('validador_gate', [
  'rid' => $rid,
  'can_validate' => $can_validate,
  'has_strong' => (bool)$has_strong,
  'recognized_organs' => (bool)$recognized_organs,
], 'INFO');

if ($can_validate) {
    if ($medidas_json) {
        // Preferir JSON de la IA
        $valres = validar_informe_desde_json($mysqli, $content, $medidas_json, $ctxValid);
    } else {
        // Fallback a parser HTML actual
        $valres = validar_informe_html($mysqli, $content, $ctxValid);
    }
    $content = $valres['html_out'];
    $validaciones_items = $valres['items'];
}

// === Construir paquete para el <details> (organos + medidas)
$medidas_pack = [
  'organos_mencionados' => [],
  'medidas' => is_array($medidas_json) ? $medidas_json : []
];

if (is_array($decoded_full)) {
    if (isset($decoded_full['organos_mencionados']) && is_array($decoded_full['organos_mencionados'])) {
        // limpia duplicados y vacíos
        $medidas_pack['organos_mencionados'] = array_values(array_unique(array_filter($decoded_full['organos_mencionados'], fn($x)=>is_string($x) && trim($x)!=='')));
    }
    if (isset($decoded_full['medidas']) && is_array($decoded_full['medidas'])) {
        $medidas_pack['medidas'] = $decoded_full['medidas']; // prioriza lo completo
    }
}

// Fallback local si faltan órganos
if (empty($medidas_pack['organos_mencionados'])) {
    if (!function_exists('organo_canonico_map')) {
        // (si no pegaste antes el map, aquí va inline mínimo)
        function organo_canonico_map(): array {
            return [
                'imagen vesical' => 'Vejiga', 'vesicula biliar' => 'Vesícula biliar', 'vesícula biliar' => 'Vesícula biliar',
                'imagen renal izquierda' => 'Riñón izquierdo', 'riñon izquierdo' => 'Riñón izquierdo', 'riñón izquierdo' => 'Riñón izquierdo',
                'imagen renal derecha' => 'Riñón derecho', 'riñon derecho' => 'Riñón derecho', 'riñón derecho' => 'Riñón derecho',
                'imagen esplenica' => 'Bazo', 'imagen esplénica' => 'Bazo', 'bazo' => 'Bazo',
                'imagen gastrica' => 'Estómago (antro)', 'imagen gástrica' => 'Estómago (antro)', 'estomago (antro)' => 'Estómago (antro)', 'estómago (antro)' => 'Estómago (antro)',
                'duodeno' => 'Duodeno', 'yeyuno' => 'Yeyuno', 'colon' => 'Colon',
                'imagen hepatica' => 'Hígado', 'imagen hepática' => 'Hígado', 'higado' => 'Hígado', 'hígado' => 'Hígado',
                'imagen pancreatica' => 'Páncreas', 'imagen pancreática' => 'Páncreas', 'pancreas' => 'Páncreas', 'páncreas' => 'Páncreas',
                'linfonodulos' => 'Linfonódulos', 'linfonódulos' => 'Linfonódulos',
                'peritoneo' => 'Peritoneo', 'mesenterio' => 'Mesenterio',
                'ureter proximal izquierdo' => 'Uréter proximal izquierdo', 'uréter proximal izquierdo' => 'Uréter proximal izquierdo',
                'ureter proximal derecho' => 'Uréter proximal derecho', 'uréter proximal derecho' => 'Uréter proximal derecho',
                'prostata' => 'Próstata', 'próstata' => 'Próstata', 'vejiga' => 'Vejiga'
            ];
        }
    }
    $src = mb_strtolower($texto . ' ' . strip_tags($content), 'UTF-8');
    $can = [];
    foreach (organo_canonico_map() as $alias => $canon) {
        if ($canon && mb_strpos($src, $alias) !== false) { $can[$canon] = true; }
    }
    // Adrenales: sin lado explícito no forzamos
    if (mb_strpos($src, 'adrenal izquierda') !== false) { $can['Adrenal izquierda'] = true; }
    if (mb_strpos($src, 'adrenal derecha') !== false)   { $can['Adrenal derecha'] = true; }

    $medidas_pack['organos_mencionados'] = array_keys($can);
}

// === Inyectar debajo de "Validaciones del Sistema"
$content = inject_medidas_json_below_validaciones(
    $content,
    build_medidas_json_details($medidas_pack) // <-- IMPORTANTE: pasar el PACK, no $medidas_json
);

// (opcional) log para verificar qué estamos metiendo
app_log_body('medidas_pack', ['rid'=>$rid, 'medidas_pack'=>$medidas_pack]);









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

$want_json_debug = (
  (isset($_GET['show_json'])  && $_GET['show_json']  === '1') ||
  (isset($_POST['show_json']) && $_POST['show_json'] === '1')
);

if (SANITIZE_HTML_OUTPUT) {
    $content = sanitize_html_output($content);
}

// === Éxito ================================================================
$payload_out = [
    'status'        => 'success',
    'content'       => $content,        // HTML del informe (con validaciones inyectadas)
    'medidas_json'  => $medidas_json,   // <-- SIEMPRE adjuntamos lo que vino del modelo (array o null)
    'rid'           => $rid,
    'usage'         => [
        'prompt_tokens'     => $prompt_tokens,
        'completion_tokens' => $completion_tokens,
        'total_tokens'      => $total_tokens,
        'cost_usd'          => $cost_usd
    ]
];

echo json_encode($payload_out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
