<?php
declare(strict_types=1);

/**
 * funciones para armar el prompt y el system
 */

function gpt_limpiar_acentos(string $texto): string {
    return strtr($texto, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U',
        'ñ'=>'n','Ñ'=>'N','ü'=>'u','Ü'=>'U'
    ]);
}

function gpt_approx_tokens(string $s): int {
    return (int) ceil(mb_strlen($s, '8bit') / 4);
}

/**
 * carga ejemplos desde la BD si hay plantilla_id
 */
function gpt_cargar_ejemplos(mysqli $mysqli, int $plantilla_id): string {
    if ($plantilla_id <= 0) return '';
    $stmt = $mysqli->prepare("SELECT ejemplo FROM plantilla_informe_ejemplo WHERE plantilla_informe_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $plantilla_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $ejemplos = [];
    while ($row = $res->fetch_assoc()) {
        $ejemplos[] = $row['ejemplo'];
    }
    $stmt->close();

    if (empty($ejemplos)) return '';

    $texto = "EJEMPLOS DE INFORME PARA ESTA PLANTILLA (solo estilo, no inventar datos):\n";
    foreach ($ejemplos as $i => $ej) {
        $texto .= "Ejemplo " . ($i+1) . ":\n" . $ej . "\n\n";
    }
    return $texto;
}

/**
 * arma el system + prompt final
 * devuelve también si hay que incluir conclusión
 */
function gpt_build_prompt(mysqli $mysqli, array $input): array
{
    $plantilla_id = (int)($input['plantilla_id'] ?? 0);
    $texto_ejemplos = gpt_cargar_ejemplos($mysqli, $plantilla_id);

    $dictado   = trim((string)$input['texto']);
    $dictado_l = mb_strtolower($dictado, 'UTF-8');
    $incluir_conclusion = (str_contains($dictado_l, 'conclusión') || str_contains($dictado_l, 'conclusion'));

    // ── SYSTEM: reglas fijas para todos los informes ──
    $system = <<<'SYS'
Eres un médico veterinario especialista en informes ecográficos.

SALIDA (OBLIGATORIO)
- Devuelve SOLO el fragmento HTML del informe (sin <html> ni <body>), usando EXACTAMENTE la PLANTILLA BASE (orden y etiquetas intocables).
- No incluyas CSS, JS, iframes ni estilos inline. No uses Markdown ni fences.
- No cambies los títulos ni reordenes las secciones que trae la PLANTILLA BASE.
- Si la PLANTILLA BASE ya trae una descripción completa de un órgano y el dictado solo aporta 1 dato nuevo (p. ej. una medida, un engrosamiento, una observación puntual), entonces agrega SOLO ese dato al final de la descripción existente, sin reescribir ni resumir el texto de la plantilla.
- No inventes datos; si el dictado no menciona algo, deja el texto base de la plantilla o elimínalo si claramente no aplica.

TRANSCRIPCIÓN CLÍNICA
- Transcribe SOLO contenido CLÍNICO del DICTADO.
- IGNORA texto que sea: publicidad, descripciones de cámaras/equipos, instrucciones al usuario, frases de demostración, nombres de personas, marcas o frases de venta.
- Respeta números y unidades tal cual (coma o punto).
- No completes valores ausentes.
- Si el dictado trae un órgano que NO está en la plantilla, colócalo al final en un bloque adicional titulado: <strong>HALLAZGOS ADICIONALES:</strong> usando el mismo estilo narrativo.

CONCLUSIÓN
- Inclúyela solo si el dictado la trae o la solicita explícitamente.
- Si el dictado menciona hallazgos relevantes (urolitos, engrosamientos, alteraciones difusas) puedes resumirlos en la conclusión SI y solo si el dictado ya dio esa información.
- Si no hay conclusión en el dictado, NO inventes una.

FLAGS (OBLIGATORIO dentro del texto)
- Marca con <sup class="flag" data-flag="N" data-tipo="TIPO">(N)</sup>, con N=1,2,3… por orden de aparición.
- Ubicación: si hay número+unidad (cm|mm|mt), el flag va PEGADO DESPUÉS del número; si no hay número, va PEGADO DESPUÉS de la palabra clave.
- Usa los tipos así:
  1. falta_unidad →
      - SOLO cuando hay número sin unidad (ej. “3,5” a secas),
    - o cuando hay unidad sin número (ej. “cm” a secas),
    - o cuando la unidad está cortada (“m” cuando debería ser “mm” o “cm”).
    - **Si la medida ya viene como “3,79 cm” o “4,3 cm” NO marques falta_unidad.**
  2. termino_confuso →
    - cuando la palabra/frase no pertenece a un informe ecográfico veterinario,
    - o es claramente un error de dictado/tecleo,
    - o viene mezclada con publicidad/conversación.
  3. valor_sospechoso →
    - SOLO cuando la medida es extrema o imposible para perros o gatos de cualquier talla.
    - Por ejemplo:
      - < 0,05 cm o > 1,5 cm en grosores de pared
      - < 2 cm o > 12 cm en órganos mayores (riñón, hígado)
      - < 1 cm o > 6 cm en próstata
      - < 0,1 cm o > 1 cm en pared vesical
    - Si la medida está dentro de esos rangos amplios, **no la marques** como sospechosa.
- Mantén activos estos tipos:
  - valor_sospechoso
  - falta_unidad
  - termino_confuso
  - incongruencia
- No dupliques flags sobre el mismo dato.


OBSERVACIONES DEL ASISTENTE
- Genera observaciones SOLO para:
  - incongruencia (cuando haya datos suficientes y certeza),
  - termino_confuso (para decir “parece error de dictado, confirme término”).
- NO generes observaciones para:
  - falta_unidad (eso lo resuelve el humano fácilmente),
  - valor_sospechoso cuando el único problema es que el modelo no conoce el rango.
- Formato OBLIGATORIO:
  <p><strong>Observaciones del Asistente:</strong><br>
  (N) TIPO → órgano o zona afectada; qué revisar o confirmar; propuesta breve de corrección.<br>
  </p>
- Si no hay observaciones válidas, NO incluyas el bloque.
SYS;

    // ── CONTEXTO del caso ──
    $especie        = gpt_limpiar_acentos(trim((string)($input['especie'] ?? '')));
    $raza           = gpt_limpiar_acentos(trim((string)($input['raza'] ?? '')));
    $edad           = gpt_limpiar_acentos(trim((string)($input['edad'] ?? '')));
    $sexo           = gpt_limpiar_acentos(trim((string)($input['sexo'] ?? '')));
    $tipo_estudio   = gpt_limpiar_acentos(trim((string)($input['tipo_estudio'] ?? '')));
    $motivo         = gpt_limpiar_acentos(trim((string)($input['motivo'] ?? '')));
    $plantilla_base = (string)($input['plantilla_base'] ?? '');

    // ── PROMPT de usuario ──
    $prompt = "
REDACCION DE INFORME ECOGRAFICO VETERINARIO

Usa la siguiente PLANTILLA BASE como formato. Mantén sus etiquetas y su orden.
Rellena solo con contenido CLINICO proveniente del DICTADO.
NO uses Markdown, NO uses ``` y NO agregues estilos.

=== CONTEXTO (no incluir en el informe) ===
Especie: {$especie}
Raza: {$raza}
Edad: {$edad}
Sexo: {$sexo}
Tipo de estudio: {$tipo_estudio}
Motivo: {$motivo}

=== TRANSCRIPCION (obligatorio) ===
- Transcribe literalmente hallazgos clínicos y medidas del DICTADO.
- Excluye TODO lo no clínico (publicidad, marcas, frases de venta, explicación de equipos).
- Mantén unidades tal como se dictan.
- Respeta correcciones del dictante.

=== CONCLUSION ===
Solo si el dictado la trae o lo solicita.

=== SALIDA (obligatorio) ===
- Devuelve SOLO el informe en HTML.
- Mantén la estructura y etiquetas de la PLANTILLA BASE.
- Si el dictado agrega un dato puntual sobre una sección ya escrita en la plantilla (p. ej. “bazo con grosor 1,53 cm”), inserta SOLO ese dato en esa sección, sin reescribir todo el párrafo de la plantilla.
- Si hay hallazgos que no estén en la plantilla, añádelos al final en <strong>HALLAZGOS ADICIONALES:</strong>

=== PLANTILLA BASE (no modificar) ===
<<<PLANTILLA_BASE
{$plantilla_base}
PLANTILLA_BASE

=== EJEMPLOS (solo estilo, pueden no venir) ===
<<<EJEMPLOS
{$texto_ejemplos}
EJEMPLOS

=== DICTADO (fuente de verdad clínica) ===
<<<DICTADO
{$dictado}
DICTADO
";

    $prompt = trim($prompt);

    return [
        'system'             => $system,
        'prompt'             => $prompt,
        'incluir_conclusion' => $incluir_conclusion,
    ];
}
