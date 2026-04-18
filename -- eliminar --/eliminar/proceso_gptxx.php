<?php
// generar_informe_cascada.php
// Requiere: PHP 8.1+, extensiones curl/json, tu conn.php y configP.php

require_once("../funciones/conn/conn.php");
require_once("../configP.php");
$mysqli = conn();

header('Content-Type: application/json; charset=utf-8');

// =====================
// Config general
// =====================
define('VM_GEN_VER', 'V3');     // versión del prompt de generación
define('VM_AUD_VER', 'V1');     // versión del auditor JSON
$MODEL_PRIMARY   = 'gpt-5-mini';
$MODEL_FALLBACK  = 'gpt-5';
$TEMP_GEN        = 0.2;         // baja para transcribir fiel
$TEMP_AUD        = 0.0;         // auditor estricto
$MAX_TOKENS_OUT  = 2000;        // ajusta si tus informes son más largos
$ESCALA_FLAGS_MIN = 2;          // escalar si total flags >= 2
$ESCALA_SEVERA   = ['alta'];    // escalar si alguna flag es "alta"

// =====================
// Helpers
// =====================
function norm($s) { return trim((string)($s ?? '')); }
function log_dbg($file, $data) {
  @file_put_contents($file, (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))."\n", FILE_APPEND);
}
function call_openai_chat($api_key, $payload) {
  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: ' . 'Bearer ' . $api_key
  ]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  curl_setopt($ch, CURLOPT_TIMEOUT, 90);
  $resp = curl_exec($ch);
  $err  = curl_errno($ch) ? curl_error($ch) : null;
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$http, $resp, $err];
}

// =====================
// Validación entrada
// =====================
$api_key = $OPENAI_API_KEY ?? getenv('OPENAI_API_KEY') ?? '';
if (!$api_key) {
  echo json_encode(['status'=>'error','message'=>'API Key de OpenAI no configurada.']); exit;
}
if (!isset($_POST['texto']) || trim($_POST['texto'])==='') {
  echo json_encode(['status'=>'error','message'=>'No se recibió texto para procesar.']); exit;
}

// Datos recibidos
$paciente       = norm($_POST['paciente'] ?? '');
$especie        = norm($_POST['especie'] ?? '');
$raza           = norm($_POST['raza'] ?? '');
$edad           = norm($_POST['edad'] ?? '');
$sexo           = norm($_POST['sexo'] ?? '');
$tipo_estudio   = norm($_POST['tipo_estudio'] ?? '');
$plantilla_base = norm($_POST['plantilla_base'] ?? '');
$motivo         = norm($_POST['motivo'] ?? '');
$dictado        = norm($_POST['texto'] ?? '');
$plantilla_id   = intval($_POST['plantilla_id'] ?? 0);

// Traer ejemplos por plantilla
$ejemplos = [];
if ($plantilla_id > 0) {
  $stmt = $mysqli->prepare("SELECT ejemplo FROM plantilla_informe_ejemplo WHERE plantilla_informe_id = ? ORDER BY id ASC");
  if ($stmt) {
    $stmt->bind_param('i', $plantilla_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $ejemplos[] = $row['ejemplo']; }
    $stmt->close();
  }
}
$texto_ejemplos = '';
if (!empty($ejemplos)) {
  $texto_ejemplos = "EJEMPLOS DE INFORME PARA ESTA PLANTILLA:\n";
  foreach ($ejemplos as $i => $ej) { $texto_ejemplos .= "Ejemplo ".($i+1).":\n".$ej."\n\n"; }
}

// =====================
// Reglas de generación (prefijo cacheable)
// Solo marcar INCONSISTENCIAS o IMPOSIBLES
// =====================
$rules_static = <<<RULES
[VETMIND_GENERACION_".VM_GEN_VER."]

Rol
- Eres un médico veterinario que transcribe y da formato al informe ecográfico en HTML, usando una PLANTILLA BASE.

Fidelidad y no-invención
- Transcribe exactamente todos los datos del dictado (números, unidades, conteos).
- No inventes, no completes, no corrijas, no cambies unidades ni redondees.

Cuándo MARCAR (sólo entonces):
1) **Incongruencia semántica**: el texto afirma "normal/conservado/sin hallazgos" y el valor o conteo indica lo contrario.
   - Palabras de normalidad: normal, conservado, dentro de rangos, sin hallazgos, no se observan alteraciones, sin cambios significativos.
   - Palabras de anormalidad: aumentado, disminuido, engrosado, adelgazado, hiperecoico, hipoecoico, calculos/urolitos/quistes presentes, masas, dilatación, etc.
   - Si el texto reconoce la anormalidad (p.ej., “urolitos presentes”, “aumentado”), **no marques** por ese motivo.

2) **Valor imposible / error de escala**: magnitudes evidentemente fuera de órdenes de magnitud esperados (p.ej., un grosor habitual en milímetros escrito en decenas de cm; 33.225 cm en pared; unidades incoherentes).
   - En estos casos **sí marca** aunque el texto no lo contradiga (posible error de unidad mm↔cm).

3) **Inconsistencia interna**: el mismo parámetro aparece con valores diferentes en el informe sin justificación.

Cómo marcar
- Rodea sólo el **dato concreto** con `<span style='color:orange;'>...dato...</span>` y añade número correlativo (1), (2)…
- No marques datos que estén **coherentes con el texto** (por ejemplo, “engrosado” y un grosor efectivamente alto).
- No marques por ser “anormal” si el texto lo declara. Marca únicamente contradicciones o imposibles.

Formato salida
- Devuelve SOLO el informe final en **HTML limpio** (sin el dictado ni comentarios).
- Respeta la estructura de la PLANTILLA BASE.
- Incluye sección **CONCLUSION** (en negrita HTML) y la lista con `&nbsp;&nbsp;` y `<br>`.
- Al final agrega **Observaciones del Asistente** listando (1), (2)… con explicación breve del motivo (incongruencia/escala).

RULES;

// Prefijo cacheable por plantilla (plantilla + ejemplos)
$context_static = "PLANTILLA:\n{$plantilla_base}\n\n";
if ($texto_ejemplos) { $context_static .= "{$texto_ejemplos}\n"; }

// Variables cortas
$contexto_paciente = <<<PAC
**Contexto del paciente (no imprimir):**
- Especie: {$especie}
- Raza: {$raza}
- Edad: {$edad}
- Sexo: {$sexo}
- Tipo de estudio: {$tipo_estudio}
- Motivo: {$motivo}
PAC;
$dictado_msg = "DICTADO DEL VETERINARIO:\n{$dictado}";

// =====================
// 1) GENERAR con 5-mini
// =====================
$cache_key_gen = 'vetmind:gen:'.VM_GEN_VER.':plantilla:'.($plantilla_id ?: 'none');

$gen_payload = [
  'model' => $MODEL_PRIMARY,
  'temperature' => $TEMP_GEN,
  'max_tokens' => $MAX_TOKENS_OUT,
  'prompt_cache_key' => $cache_key_gen,
  'messages' => [
    ['role'=>'system', 'content'=>$rules_static],
    ['role'=>'user',   'content'=>$context_static],
    ['role'=>'user',   'content'=>$contexto_paciente],
    ['role'=>'user',   'content'=>$dictado_msg],
  ],
];

[$http1,$resp1,$err1] = call_openai_chat($api_key, $gen_payload);
log_dbg('/tmp/vm_gen_request.json', ['payload'=>$gen_payload,'http'=>$http1]);
log_dbg('/tmp/vm_gen_response.json', $resp1);

if ($err1) { echo json_encode(['status'=>'error','message'=>'Error conexión GEN: '.$err1]); exit; }

$gen = json_decode($resp1, true);
if ($http1!==200 || !isset($gen['choices'][0]['message']['content'])) {
  $msg = $gen['error']['message'] ?? ('HTTP '.$http1);
  echo json_encode(['status'=>'error','stage'=>'generate','message'=>'Error API GEN: '.$msg]);
  exit;
}
$html_informe = $gen['choices'][0]['message']['content'];
$usage1 = $gen['usage'] ?? [];
$cached1 = $usage1['prompt_tokens_details']['cached_tokens'] ?? 0;

// =====================
// 2) AUDITAR con 5-mini (JSON)
// =====================
$rules_auditor = <<<AUD
[VETMIND_AUDITOR_".VM_AUD_VER."]

Objetivo
- NO reescribas el informe. Analiza **INCONGRUENCIAS** o **IMPOSIBLES/ESCALA** comparando DICTADO vs INFORME_HTML.

Criterios (estrictos)
- Marca **incongruencia** si el informe afirma normalidad/conservación y el dictado describe un hallazgo anormal (o viceversa).
- Marca **imposible/escala** si hay magnitudes evidentemente fuera de escala (p.ej., mm escritos como decenas de cm) o valores físicamente inverosímiles.
- Ignora anormalidades **declaradas** como tales en el dictado (eso es coherente).
- No inventes rangos médicos. Usa reglas de coherencia semántica y órdenes de magnitud.

Salida OBLIGATORIA en JSON **válido**, sin texto adicional:
{
  "ok": boolean,
  "flags": [
    {
      "tipo": "incongruencia" | "imposible" | "inconsistencia_interna",
      "frase": "cita breve del informe o dictado donde ocurre",
      "motivo": "explicación breve",
      "severidad": "alta" | "media" | "baja",
      "confidence": 0.0-1.0
    }
  ]
}
AUD;

$cache_key_aud = 'vetmind:aud:'.VM_AUD_VER.':plantilla:'.($plantilla_id ?: 'none');

$aud_payload = [
  'model' => $MODEL_PRIMARY,
  'temperature' => $TEMP_AUD,
  'max_tokens' => 800,
  'prompt_cache_key' => $cache_key_aud,
  'messages' => [
    ['role'=>'system','content'=>$rules_auditor],
    ['role'=>'user','content'=>"**Contexto del paciente (no imprimir):**\n- Especie: {$especie}\n- Raza: {$raza}\n- Edad: {$edad}\n- Sexo: {$sexo}\n- Tipo de estudio: {$tipo_estudio}\n- Motivo: {$motivo}"],
    ['role'=>'user','content'=>"DICTADO:\n{$dictado}"],
    ['role'=>'user','content'=>"INFORME_HTML:\n{$html_informe}"],
  ],
];

[$http2,$resp2,$err2] = call_openai_chat($api_key, $aud_payload);
log_dbg('/tmp/vm_aud_request.json', ['payload'=>$aud_payload,'http'=>$http2]);
log_dbg('/tmp/vm_aud_response.json', $resp2);

if ($err2) {
  echo json_encode([
    'status'=>'success',
    'stage'=>'generate_only',
    'content'=>$html_informe,
    'note'=>'Auditor no disponible: '.$err2,
    'usage'=>['gen'=>$usage1]
  ]);
  exit;
}

$aud = json_decode($resp2, true);
$usage2 = $aud['usage'] ?? [];
$cached2 = $usage2['prompt_tokens_details']['cached_tokens'] ?? 0;

$aud_json = [];
if ($http2===200 && isset($aud['choices'][0]['message']['content'])) {
  // Debe ser JSON puro
  $aud_str = trim($aud['choices'][0]['message']['content']);
  $aud_json = json_decode($aud_str, true);
  if (!is_array($aud_json)) { $aud_json = ['ok'=>true,'flags'=>[]]; }
} else {
  $aud_json = ['ok'=>true,'flags'=>[]];
}

// Decidir si escalar
$flags = $aud_json['flags'] ?? [];
$flag_count = is_array($flags) ? count($flags) : 0;
$hay_severa = false;
foreach ($flags as $f) {
  if (isset($f['severidad']) && in_array(strtolower($f['severidad']), $ESCALA_SEVERA, true)) { $hay_severa = true; break; }
}
$debe_escalar = ($flag_count >= $ESCALA_FLAGS_MIN) || $hay_severa;

// =====================
// 3) ESCALAR (opcional) con GPT-5
// =====================
if ($debe_escalar) {
  $rules_fix = <<<FIX
[VETMIND_FIX_GPT5]

Tarea
- Recibirás DICTADO, INFORME_HTML y una lista de banderas (flags).
- Devuelve **únicamente** el INFORME_HTML **corregido**:
  - Mantén todos los datos del dictado (no inventes ni cambies números).
  - Si la bandera es "incongruencia", ajusta el **marcado naranja** solo donde corresponda (o elimínalo si no aplica).
  - Si la bandera es "imposible/escala", **marca** el dato con el estilo naranja y conserva el número tal cual.
  - No agregues textos explicativos fuera de la sección “Observaciones del Asistente”.
- Respeta la PLANTILLA y la sección **CONCLUSION** (negrita + lista con &nbsp;&nbsp; y <br>).
FIX;

  $flags_str = json_encode($flags, JSON_UNESCAPED_UNICODE);

  $fix_payload = [
    'model' => $MODEL_FALLBACK,
    'temperature' => 0.0,
    'max_tokens' => $MAX_TOKENS_OUT,
    'prompt_cache_key' => 'vetmind:fix:'.VM_GEN_VER.':plantilla:'.($plantilla_id ?: 'none'),
    'messages' => [
      ['role'=>'system','content'=>$rules_fix],
      ['role'=>'user','content'=>"PLANTILLA (referencia de formato, no imprimir):\n{$plantilla_base}"],
      ['role'=>'user','content'=>"DICTADO:\n{$dictado}"],
      ['role'=>'user','content'=>"INFORME_HTML:\n{$html_informe}"],
      ['role'=>'user','content'=>"FLAGS_JSON:\n{$flags_str}"],
    ],
  ];

  [$http3,$resp3,$err3] = call_openai_chat($api_key, $fix_payload);
  log_dbg('/tmp/vm_fix_request.json', ['payload'=>$fix_payload,'http'=>$http3]);
  log_dbg('/tmp/vm_fix_response.json', $resp3);

  if (!$err3) {
    $fix = json_decode($resp3, true);
    if ($http3===200 && isset($fix['choices'][0]['message']['content'])) {
      $html_informe = $fix['choices'][0]['message']['content'];
      $usage3 = $fix['usage'] ?? [];
      $cached3 = $usage3['prompt_tokens_details']['cached_tokens'] ?? 0;
      echo json_encode([
        'status'=>'success',
        'stage'=>'generated_and_fixed',
        'content'=>$html_informe,
        'audit'=>$aud_json,
        'usage'=>[
          'gen'=>$usage1 + ['cached_tokens'=>$cached1],
          'aud'=>$usage2 + ['cached_tokens'=>$cached2],
          'fix'=>$usage3 + ['cached_tokens'=>$cached3],
        ]
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }
  }
}

// =====================
// Respuesta sin escalar
// =====================
echo json_encode([
  'status'=>'success',
  'stage'=> $debe_escalar ? 'generated_scale_failed' : 'generated_audited_ok',
  'content'=>$html_informe,
  'audit'=>$aud_json,
  'usage'=>[
    'gen'=>$usage1 + ['cached_tokens'=>$cached1],
    'aud'=>$usage2 + ['cached_tokens'=>$cached2],
  ]
], JSON_UNESCAPED_UNICODE);
