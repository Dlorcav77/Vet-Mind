<?php
require_once("../funciones/conn/conn.php"); // conexión
require_once("../configP.php");
require_once(__DIR__ . "/logger.php");      // helper de logs
$mysqli = conn();

header('Content-Type: application/json; charset=utf-8');

// ⏱️ ID y timer de este request
$REQ_ID = log_req_id();
$T0 = log_now_ms();
log_event('proceso_gpt.log', $REQ_ID, 'start', $T0, [
  'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
  'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
]);

// 🚨 Validación básica
if (!isset($_POST['texto']) || empty(trim($_POST['texto']))) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió texto para procesar.']);
    exit;
}

log_event('proceso_gpt.log', $REQ_ID, 'received_input', $T0, [
  'texto_len'     => strlen($_POST['texto'] ?? ''),
  'plantilla_len' => strlen($_POST['plantilla_base'] ?? '')
]);

// --- Función para limpiar acentos y ñ (solo para metadatos cortos) ---
function limpiar_acentos($texto) {
    $texto = strtr($texto, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U',
        'ñ'=>'n','Ñ'=>'N',
        'ü'=>'u','Ü'=>'U'
    ]);
    return $texto;
}

// --- Limpiar y preparar los datos ---
// Mantén literalidad en textos largos (plantilla/dictado/ejemplos)
$paciente       = limpiar_acentos(trim($_POST['paciente'] ?? ''));
$especie        = limpiar_acentos(trim($_POST['especie'] ?? ''));
$raza           = limpiar_acentos(trim($_POST['raza'] ?? ''));
$edad           = limpiar_acentos(trim($_POST['edad'] ?? ''));
$sexo           = limpiar_acentos(trim($_POST['sexo'] ?? ''));
$tipo_estudio   = limpiar_acentos(trim($_POST['tipo_estudio'] ?? ''));
$motivo         = limpiar_acentos(trim($_POST['motivo'] ?? ''));

// 🔹 Literal: NO limpiar acentos aquí
$plantilla_base = trim($_POST['plantilla_base'] ?? '');
$texto          = trim($_POST['texto'] ?? '');
$plantilla_id   = $_POST['plantilla_id'] ?? 0;

// --- Cargar ejemplos de la BD (literal) ---
$ejemplos = [];
if ($plantilla_id) {
    $stmt = $mysqli->prepare("SELECT ejemplo FROM plantilla_informe_ejemplo WHERE plantilla_informe_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $plantilla_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ejemplos[] = $row['ejemplo'];
    }
}
$texto_ejemplos = '';
if (!empty($ejemplos)) {
    $texto_ejemplos = "EJEMPLOS DE INFORME PARA ESTA PLANTILLA:\n";
    foreach ($ejemplos as $i => $ej) {
        $texto_ejemplos .= "Ejemplo " . ($i+1) . ":\n" . $ej . "\n\n";
    }
}

// --- Construir el prompt (heredoc literal) ---
$prompt = <<<PROMPT
Redacta un informe ecográfico veterinario usando la PLANTILLA BASE como formato, pero SOLO con información literal del dictado.

**Contexto del paciente:** (no agregar al informe, solo para ajustar rangos)
- Especie: {$especie}
- Raza: {$raza}
- Edad: {$edad}
- Sexo: {$sexo}
- Tipo de estudio: {$tipo_estudio}
- Motivo: {$motivo}

**Entradas:**
- PLANTILLA_BASE: estructura y títulos a respetar (orden y nombres idénticos).
- TEXTOS_DE_EJ: ejemplos SOLO para estilo/tono (no copiar datos).
- DICTADO_VET: texto literal del médico, fuente única de datos.

**Reglas de redacción (MODO ESTRICTO):**
- Transcribe EXACTAMENTE lo dicho en DICTADO_VET (medidas, hallazgos, frases, unidades, errores). No corrijas ni parafrasees, ni modifiques ningún dato.
- No inventes datos que no estén en DICTADO_VET. No derives ni infieras medidas nuevas.
- Respeta el orden y los títulos de PLANTILLA_BASE, pero:
  - Incluir SOLO las secciones donde DICTADO_VET aporte información explícita.
  - Omitir por completo cualquier sección no mencionada en el dictado (no escribir “no mencionado”).
- Si el dictado incluye caracteres que romperían el HTML, escápalos (por ejemplo: <, >, &), manteniendo el resto exactamente igual.
- No normalices nada. Únicamente puedes estandarizar “cms”, “centimetros”, “centímetros” a “cm”. No cambies otras unidades ni la ortografía.
- Si un valor numérico es **sospechoso** (es decir, claramente incompatible por orden de magnitud para la especie/edad) o hay **incongruencia** entre el texto y el valor (p. ej., dice “normal” pero los números implican lo contrario), transcribe el dato tal cual, agrégale un número entre paréntesis (1), (2), etc., y resáltalo usando la etiqueta HTML <span style='color:orange;'>...dato... (N)</span>. Sé prudente: si hay duda razonable, no marques.

**Importante sobre incongruencias y sospechosos:**
- No marques como **incongruente** si el valor está alterado (aumentado o disminuido) y el texto lo indica correctamente.
- No marques como **sospechoso** si el valor es coherente con lo que se describe (p. ej., dice “aumentado” y el valor es alto para la especie).
- No marques como **sospechoso** si el valor está dentro de normalidad.
- Solo marcar cuando haya **desacuerdo texto↔número** o **magnitudes implausibles** (mm vs cm, tamaños absurdos como “200 cm” en órganos pequeños). Mantén el texto literal y solo agrega la marca.

**Resultado esperado:**
- Devuelve únicamente el informe procesado, en **HTML limpio**, sin incluir el dictado original ni comentarios meta.

**Observaciones del Asistente (solo si hubo marcas):**
- Al final del informe, añade la sección:
  **Observaciones del Asistente:**  
  (1) Breve explicación del motivo de la marca (p. ej., “Incongruencia con ‘normal’: valor sugiere magnitud incompatible para {especie}. Verificar unidad mm vs cm.”).<br>
  (2) ...

**Sobre la CONCLUSION:**
- Inclúyela SOLO si existe en PLANTILLA_BASE y DICTADO_VET entregó contenido para esa sección.
- Si el dictado no provee conclusión, **omitirla** (no generar ni resumir).

---

PLANTILLA_BASE:
{$plantilla_base}

TEXTOS_DE_EJ (no copiar datos; solo estilo):
{$texto_ejemplos}

DICTADO_VET:
{$texto}
PROMPT;

//  validar que si en el dictado dice no tomes encuenta eso que el sistema sepa a que se refiere

// 📏 Métrica de largo de prompt
log_event('proceso_gpt.log', $REQ_ID, 'prompt_ready', $T0, [
  'prompt_len' => strlen($prompt)
]);

file_put_contents('/tmp/prompt_debug.log', $prompt);

// --- Validar API KEY ---
$api_key = $OPENAI_API_KEY ?? '';
if (!$api_key) {
    echo json_encode(['status' => 'error', 'message' => 'API Key de OpenAI no configurada.']);
    exit;
}

// --- Modelo parametrizable (rápido por defecto) ---
// $model = getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';
$model = getenv('OPENAI_MODEL') ?: 'gpt-5';
// $model = 'gpt-5-mini'; // si quieres probar este
// Nota: para gpt-5 no envíes 'temperature' custom.

// --- Llamada a OpenAI API ---
$payload = [
    'model'    => $model,
    'messages' => [
        [
          'role' => 'system',
          'content' => 'Eres un médico veterinario especialista en informes clínicos. Devuelve HTML limpio. Respeta los títulos y el orden de PLANTILLA_BASE, pero OMITE cualquier sección no mencionada explícitamente en el dictado. No inventes contenido.'
        ],
        ['role' => 'user', 'content' => $prompt]
    ],
    'max_tokens' => 2000 // ajusta según la plantilla
];

$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($jsonPayload === false) {
    file_put_contents('/tmp/json_error.log', json_last_error_msg());
    die('Error generando el JSON: ' . json_last_error_msg());
}

// 📏 Métrica de payload
log_event('proceso_gpt.log', $REQ_ID, 'payload_built', $T0, [
  'payload_len' => strlen($jsonPayload)
]);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $api_key
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

// ⏱️ Evitar “procesando infinito”
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10s para conectar
curl_setopt($ch, CURLOPT_TIMEOUT, 60);        // 60s total

// (opcional) HTTP/2 + compresión
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
curl_setopt($ch, CURLOPT_ACCEPT_ENCODING, ''); // gzip/br si disponible

// 🐛 Ejecutar y registrar respuesta
$response  = curl_exec($ch);
$curlErrNo = curl_errno($ch);
$curlErr   = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// ⏱️ métricas finas de cURL
$ci = curl_getinfo($ch);
curl_close($ch);

// Guarda crudo por si hay que depurar
file_put_contents(__DIR__ . '/logs/gpt_raw_' . $REQ_ID . '.log', (string)$response);

// 📊 log de la llamada
log_event('proceso_gpt.log', $REQ_ID, 'openai_response', $T0, [
  'http_code'     => $http_code,
  'curl_errno'    => $curlErrNo,
  'namelookup'    => $ci['namelookup_time'] ?? null,
  'connect'       => $ci['connect_time'] ?? null,
  'appconnect'    => $ci['appconnect_time'] ?? null,
  'starttransfer' => $ci['starttransfer_time'] ?? null,
  'total'         => $ci['total_time'] ?? null,
  'request_bytes' => strlen($jsonPayload),
  'response_bytes'=> strlen($response ?: '')
]);

if ($curlErrNo) {
    log_event('proceso_gpt.log', $REQ_ID, 'curl_error', $T0, ['err' => $curlErr, 'errno' => $curlErrNo]);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión cURL: ' . $curlErr]);
    exit;
}

$result = json_decode($response, true);
if ($http_code !== 200) {
    $error_detail = $result['error']['message'] ?? 'Respuesta HTTP ' . $http_code;
    log_event('proceso_gpt.log', $REQ_ID, 'openai_error', $T0, ['detail' => $error_detail]);
    echo json_encode(['status' => 'error', 'message' => 'Error API OpenAI: ' . $error_detail]);
    exit;
}

$content = $result['choices'][0]['message']['content'] ?? null;
if (!$content) {
    log_event('proceso_gpt.log', $REQ_ID, 'empty_content', $T0, []);
    echo json_encode(['status' => 'error', 'message' => 'Respuesta inesperada de OpenAI. Revisa logs.']);
    exit;
}

// si viene uso de tokens, lo anotamos
$usage = $result['usage'] ?? null;
log_event('proceso_gpt.log', $REQ_ID, 'done', $T0, [
  'content_len' => strlen($content),
  'usage'       => $usage
]);

echo json_encode([
    'status'  => 'success',
    'content' => $content
]);
