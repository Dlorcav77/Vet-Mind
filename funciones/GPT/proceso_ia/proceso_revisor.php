<?php
// funciones/GPT/proceso_revisor.php
// IA revisora (2a pasada). Compara DICTADO vs INFORME y devuelve SOLO una lista
// de posibles inconsistencias. NO reescribe el informe. Motor: gpt-5-mini.
declare(strict_types=1);

$ROOT_DIR = dirname(__DIR__, 3);   // /
require_once($ROOT_DIR . "/configP.php");

date_default_timezone_set('America/Santiago');
header('Content-Type: application/json; charset=utf-8');

const REVISOR_MODEL = 'gpt-5-mini';
const REVISOR_MAX_TOKENS = 6000; // incluye tokens de razonamiento; holgura para no truncar

$dictado = trim((string)($_POST['dictado'] ?? ''));
$informe = trim((string)($_POST['informe'] ?? ''));
$plantilla = trim((string)($_POST['plantilla'] ?? ''));

if ($dictado === '' || $informe === '') {
    echo json_encode(['status'=>'error','message'=>'Falta dictado o informe.']);
    exit;
}

$api_key = $OPENAI_API_KEY ?? '';
if (!$api_key) {
    echo json_encode(['status'=>'error','message'=>'API Key de OpenAI no configurada.']);
    exit;
}

$system = <<<'SYS'
Eres un revisor de control de calidad de informes ecograficos veterinarios.
Recibes TRES textos:
- DICTADO: lo que dijo el ecografista (puede traer notas de correcciones y diferencias entre transcripciones).
- PLANTILLA BASE: el formato con todos los organos en estado NORMAL que la otra IA uso como punto de partida.
- INFORME: el HTML final generado por la otra IA.

COMO SE GENERA EL INFORME (clave para NO marcar falsos positivos):
La otra IA parte de la PLANTILLA BASE y solo cambia los atributos que el DICTADO indica distintos.
Por eso es NORMAL y CORRECTO que el informe contenga organos y atributos en estado normal que el
DICTADO no menciono: esos vienen de la PLANTILLA, NO son inventados. NUNCA los reportes.

Tu UNICA tarea es detectar desviaciones REALES del INFORME respecto al DICTADO. NO reescribes. NO inventas.

METODO OBLIGATORIO:
1. Revisa ORGANO POR ORGANO. Para cada organo, compara lo que dice el DICTADO con lo que dice el
   INFORME (apoyandote en la PLANTILLA para saber que es solo relleno normal).
2. Revisa SIEMPRE el bloque "DIFERENCIAS ENTRE 2 TRANSCRIPCIONES" del DICTADO. Por cada diferencia,
   verifica que el INFORME haya elegido una version coherente con el resto del contexto clinico.
   Presta atencion EXTREMA a diferencias donde aparece o desaparece un "no" (negaciones): son las
   mas peligrosas porque invierten el hallazgo.

Casos a reportar:
1. hallazgo_bajado (EL MAS GRAVE, NUNCA lo omitas): el DICTADO marca un organo o atributo como ALTERADO
   y el INFORME lo dejo NORMAL/CONSERVADO (o conservo el valor normal de la plantilla ignorando el dictado).
   Esto incluye hallazgos CON o SIN medida numerica:
   - Con medida: dictado "Estomago grosor aumentado 0.38" -> informe "pared conservada 0.38". Alta.
   - Cualitativos (sin numero): dictado "linfonodulos yeyunales aumentados de tamano, ecogenicidad
     aumentada, heterogenea" -> informe "no se observan LN reactivos" o "linfonodulos normales". Alta.
     Un organo que el DICTADO describe como aumentado/alterado NUNCA puede quedar como normal/no reactivo.
   - Presencia de un hallazgo: dictado describe una masa, mineralizacion, sedimento, nodulo, etc., y el
     informe no lo refleja. Alta.
   Revisa especialmente organos que el dictado describio explicitamente alterados y el informe dejo con
   el texto normal de la plantilla (linfonodulos, bazo, higado, adrenales, etc.).
2. cambio_lateralidad: lado (izquierdo/derecho) distinto entre dictado e informe. Alta.
3. cambio_medida: numero o unidad distinta entre dictado e informe. NO cuentes los "XX" de la plantilla.
   Incluye medidas TRUNCADAS: si el DICTADO da varias dimensiones ("0,85 por 1 cm", "0,5 x 0,58 cm",
   "1 por 1,3 cm") y el INFORME deja solo una ("0,85 cm"), es cambio_medida. Compara dimension por
   dimension; si el informe perdio alguna dimension que el dictado dio, marcalo. Alta.
4. omitido: hallazgo ALTERADO del dictado que el informe no refleja en ningun organo. Media.
5. inventado: SOLO si el informe afirma un dato clinico ALTERADO o especifico que NO esta en el
   DICTADO NI en la PLANTILLA BASE. Si el dato aparece en la PLANTILLA (aunque no en el dictado), NO es inventado.
6. discrepancia_negacion: revisa el bloque "DIFERENCIAS ENTRE 2 TRANSCRIPCIONES". Si en una
   discrepancia una version contiene una negacion ("no") y la otra no (por ejemplo "ureter no" vs
   "uretano"/"ureter", "no visible" vs "visible", "no se observa" vs "se observa"), y el informe
   eligio la version SIN negacion (o al reves), MARCALO. Una negacion invierte el hallazgo
   (presencia/ausencia) y es critica. Severidad alta. Indica ambas versiones y pide confirmar en el audio.
7. organo_sin_dictado: si el INFORME describe un organo con hallazgos o medidas y ese organo NO
   se menciona en el DICTADO (su contenido viene solo de la PLANTILLA), marcalo severidad BAJA
   con tipo "organo_sin_dictado", para que el humano confirme si ese organo se evaluo o no.
   NO lo marques si el organo solo trae estado normal de plantilla sin medidas inventadas;
   marcalo cuando tenga un "XX" de medida faltante o cuando convenga confirmar que se evaluo.

NO reportes (no son problemas):
- Organos o atributos en estado normal que vienen de la PLANTILLA y el dictado no menciono.
- Diferencias de redaccion, plurales, mayusculas u orden de palabras.
- Los marcadores "XX" ni los flags "(N)".

Severidad: "alta" si cambia el sentido clinico; "media" si es omision parcial; "baja" si es menor.

Responde EXCLUSIVAMENTE con un objeto JSON, sin texto antes ni despues. Formato exacto:
{"items":[{"severidad":"alta|media|baja","tipo":"hallazgo_bajado|inventado|cambio_lateralidad|cambio_medida|omitido|discrepancia_negacion|organo_sin_dictado","zona":"organo o zona","dictado":"lo que dice el dictado","informe":"lo que dice el informe","detalle":"que revisar"}]}
Si no encuentras problemas, responde exactamente {"items":[]}.
SYS;

$user = "=== DICTADO ===\n{$dictado}\n\n=== PLANTILLA BASE ===\n{$plantilla}\n\n=== INFORME (HTML) ===\n{$informe}";

$payload = [
    'model'                 => REVISOR_MODEL,
    'messages'              => [
        ['role'=>'system','content'=>$system],
        ['role'=>'user','content'=>$user],
    ],
    'max_completion_tokens' => REVISOR_MAX_TOKENS,
    'reasoning_effort'      => 'low',
    'response_format'       => ['type'=>'json_object'],
];
$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

$t0 = microtime(true);
$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $jsonPayload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $api_key,
    ],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 120,
]);
$resp = curl_exec($ch);
$err  = curl_errno($ch) ? curl_error($ch) : '';
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$ms = (int)round((microtime(true)-$t0)*1000);

if ($err !== '') {
    echo json_encode(['status'=>'error','message'=>'cURL: '.$err]);
    exit;
}
$result = json_decode((string)$resp, true);
if (!is_array($result)) {
    echo json_encode(['status'=>'error','message'=>'Respuesta no-JSON de OpenAI.']);
    exit;
}
if ($http !== 200) {
    $d = $result['error']['message'] ?? ('HTTP '.$http);
    echo json_encode(['status'=>'error','message'=>'Error API OpenAI: '.$d]);
    exit;
}

$choice  = $result['choices'][0] ?? [];
$content = (string)($choice['message']['content'] ?? '');
$finish  = (string)($choice['finish_reason'] ?? '');

$usage = $result['usage'] ?? [];
$pt = (int)($usage['prompt_tokens'] ?? 0);
$ct = (int)($usage['completion_tokens'] ?? 0);
$cost = round($pt/1_000_000*0.25 + $ct/1_000_000*2.00, 6);
$usageOut = ['prompt_tokens'=>$pt,'completion_tokens'=>$ct,'cost_usd'=>$cost,'ms'=>$ms];

// SEGURIDAD: respuesta cortada o vacia => NO decir "sin problemas". Devolver error.
if ($finish === 'length' || trim($content) === '') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'El revisor no entrego una respuesta completa (finish=' . ($finish ?: 'vacio') . '). No se puede confiar en el resultado; reintenta.',
        'usage'   => $usageOut,
        'raw'     => $content,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$clean  = trim(preg_replace('/```[a-z]*|```/i', '', $content));
$parsed = json_decode($clean, true);

// Si no se pudo parsear o no trae 'items' como array => error (no fingir limpio).
if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'No se pudo interpretar la respuesta del revisor (JSON invalido).',
        'usage'   => $usageOut,
        'raw'     => $content,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'status' => 'success',
    'items'  => $parsed['items'],
    'raw'    => $content,
    'usage'  => $usageOut,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);