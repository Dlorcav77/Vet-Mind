<?php
require_once("../funciones/conn/conn.php"); // o donde tengas tu conexión
require_once("../configP.php");
$mysqli = conn();

header('Content-Type: application/json; charset=utf-8');

// === Config de logs ===
$LOG_DIR = realpath(__DIR__ . '/../funciones/logs') ?: (__DIR__ . '/../funciones/logs');
if (!is_dir($LOG_DIR)) {
    // crea recursivamente si no existe (permisos: ajusta si tu entorno requiere otros)
    @mkdir($LOG_DIR, 0775, true);
}

/**
 * Escribe una línea en un archivo de log dentro de funciones/logs con timestamp.
 * $file: nombre de archivo (ej: 'prompt_debug.log')
 * $data: string|array|object (si no es string se serializa a JSON)
 */
function log_to(string $file, $data, bool $append = true): void {
    global $LOG_DIR;
    $path  = rtrim($LOG_DIR, '/').'/'.$file;
    $stamp = '['.date('Y-m-d H:i:s').'] ';

    if (!is_string($data)) {
        // JSON bonito y con UTF-8 sin escapar
        $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    @file_put_contents(
        $path,
        $stamp.$data.PHP_EOL,
        ($append ? FILE_APPEND : 0) | LOCK_EX
    );
}

// 🚨 Validación básica
if (!isset($_POST['texto']) || empty(trim($_POST['texto']))) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió texto para procesar.']);
    exit;
}

// --- Función para limpiar acentos y ñ ---
function limpiar_acentos($texto) {
    $texto = strtr($texto, [
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u',
        'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U',
        'ñ'=>'n', 'Ñ'=>'N',
        'ü'=>'u', 'Ü'=>'U'
    ]);
    return $texto;
}

// --- Limpiar y preparar los datos ---
$paciente       = limpiar_acentos(trim($_POST['paciente'] ?? ''));
$especie        = limpiar_acentos(trim($_POST['especie'] ?? ''));
$raza           = limpiar_acentos(trim($_POST['raza'] ?? ''));
$edad           = limpiar_acentos(trim($_POST['edad'] ?? ''));
$sexo           = limpiar_acentos(trim($_POST['sexo'] ?? ''));
$tipo_estudio   = limpiar_acentos(trim($_POST['tipo_estudio'] ?? ''));
$plantilla_base = limpiar_acentos(trim($_POST['plantilla_base'] ?? ''));
$motivo         = limpiar_acentos(trim($_POST['motivo'] ?? ''));
$texto          = limpiar_acentos(trim($_POST['texto'] ?? ''));
$plantilla_id   = $_POST['plantilla_id'] ?? 0;

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
- Si un valor numérico es **sospechoso** o hay **incongruencia** texto↔número, transcribe literal y agrega un (1), (2) ... resaltado con <span style='color:orange;'>... (N)</span>. Si hay duda razonable, no marques.

**Resultado esperado:**
- Devuelve únicamente el informe procesado, en **HTML limpio**, sin incluir el dictado original ni comentarios meta.

**Observaciones del Asistente (solo si hubo marcas):**
- Al final del informe, añade la sección:
  **Observaciones del Asistente:**  
  (1) Breve explicación del motivo de la marca. <br>
  (2) ...

**Sobre la CONCLUSION:**
- Inclúyela SOLO si existe en PLANTILLA_BASE y DICTADO_VET entregó contenido para esa sección.

**recuerda**
- escribir exactamente lo que dice el vet (sin corrige ortografía).
- los títulos no repetir.
- respuesta en español con sus entidades HTML cuando aplique (ej: &ntilde;).
- si dice "eso no va", elimina lo anterior y conserva lo actual.
---

PLANTILLA_BASE:
{$plantilla_base}

TEXTOS_DE_EJ (no copiar datos; solo estilo):
{$texto_ejemplos}

DICTADO_VET:
{$texto}
PROMPT;

$prompt = trim($prompt);
log_to('prompt_debug.log', $prompt);

// --- Validar API KEY (con fallback a variable de entorno) ---
$api_key = isset($OPENAI_API_KEY) ? $OPENAI_API_KEY : (getenv('OPENAI_API_KEY') ?: '');
if (!$api_key) {
    echo json_encode(['status' => 'error', 'message' => 'API Key de OpenAI no configurada.']);
    exit;
}

// --- Llamada a OpenAI API ---
$payload = [
    // 'model' => 'gpt-3.5-turbo', 
    'model' => 'gpt-4o',    
    // 'model' => 'gpt-5-nano',    
    // 'model' => 'gpt-5-mini',    
    // 'model' => 'gpt-5',
    'messages' => [
        // OJO: si quieres permitir omitir secciones no mencionadas, asegúrate que este system no contradiga las reglas del prompt
        ['role' => 'system', 'content' => 'Eres un médico veterinario especialista en informes clínicos. Mantén la estructura HTML; omite secciones no mencionadas en el dictado.'],
        ['role' => 'user', 'content' => $prompt]
    ],
    // 'temperature' => 0.7
];

$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($jsonPayload === false) {
    log_to('json_error.log', json_last_error_msg());
    die('Error generando el JSON: ' . json_last_error_msg());
}

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: ' . 'Bearer ' . $api_key
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
// Timeouts para evitar cuelgues
// curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
// curl_setopt($ch, CURLOPT_TIMEOUT, 60);

// 🐛 Ejecutar y registrar respuesta RAW
$response = curl_exec($ch);
log_to('gpt_raw_response.log', $response);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    curl_close($ch);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión cURL: ' . $error_msg]);
    exit;
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 🧪 Decodificar respuesta
$result = json_decode($response, true);

// Logs útiles
log_to('gpt_http.log', ['http_code' => $http_code]);
if (isset($result['usage'])) {
    log_to('gpt_usage.log', $result['usage']); // tokens, etc.
}
if (isset($result['id'])) {
    log_to('gpt_ids.log', ['id' => $result['id']]);
}

if ($http_code !== 200) {
    $error_detail = $result['error']['message'] ?? ('Respuesta HTTP ' . $http_code);
    log_to('gpt_error.log', [
        'http_code' => $http_code,
        'detail'    => $error_detail,
        'raw'       => $response
    ]);
    echo json_encode(['status' => 'error', 'message' => 'Error API OpenAI: ' . $error_detail]);
    exit;
}

$content = $result['choices'][0]['message']['content'] ?? '';
log_to('gpt_content.log', $content);

echo json_encode([
    'status'  => 'success',
    'content' => $content
]);
