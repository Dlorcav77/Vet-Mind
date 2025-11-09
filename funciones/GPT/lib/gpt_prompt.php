<?php
declare(strict_types=1);

/**
 * funciones para armar el prompt y el system
 * separamos esto para que proceso_gpt.php quede limpio
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

    $texto = "EJEMPLOS DE INFORME PARA ESTA PLANTILLA:\n";
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
- Marca con <sup class="flag" data-flag="N" data-tipo="TIPO">(N)</sup>, con N=1,2,3… por orden de aparición.
- Ubicación: si hay número+unidad (cm|mm|mt), el flag va PEGADO DESPUÉS del número; si no hay número, va PEGADO DESPUÉS de la palabra clave.
- Tipos activos:
  - valor_sospechoso
  - falta_unidad
  - termino_confuso
  - incongruencia
- No dupliques flags sobre el mismo dato.

OBSERVACIONES DEL ASISTENTE
- Genera observaciones SOLO para:
  - incongruencia (cuando haya datos suficientes y certeza),
  - termino_confuso no listado.
- NO generes observaciones para:
  - falta_unidad
  - valor_sospechoso por magnitud absurda o flechas.
- Formato OBLIGATORIO:
  <p><strong>Observaciones del Asistente:</strong><br>
  (N) TIPO → texto...<br>
  </p>
- Si no hay observaciones válidas, NO incluyas el bloque.
SYS;

    // sanitizar algunos campos de contexto
    $especie      = gpt_limpiar_acentos(trim((string)($input['especie'] ?? '')));
    $raza         = gpt_limpiar_acentos(trim((string)($input['raza'] ?? '')));
    $edad         = gpt_limpiar_acentos(trim((string)($input['edad'] ?? '')));
    $sexo         = gpt_limpiar_acentos(trim((string)($input['sexo'] ?? '')));
    $tipo_estudio = gpt_limpiar_acentos(trim((string)($input['tipo_estudio'] ?? '')));
    $motivo       = gpt_limpiar_acentos(trim((string)($input['motivo'] ?? '')));
    $plantilla_base = (string)($input['plantilla_base'] ?? '');

    $prompt = "
REDACCION DE INFORME ECOGRAFICO VETERINARIO

Usa la siguiente PLANTILLA BASE como formato. Mantén sus etiquetas y su orden.
Rellena solo con contenido CLINICO proveniente del DICTADO.

=== CONTEXTO (no incluir en el informe) ===
Especie: {$especie}
Raza: {$raza}
Edad: {$edad}
Sexo: {$sexo}
Tipo de estudio: {$tipo_estudio}
Motivo: {$motivo}

=== TRANSCRIPCION (obligatorio) ===
- Transcribe literalmente hallazgos clínicos y medidas del DICTADO.
- Excluye TODO lo no clínico.
- Mantén unidades tal como se dictan.
- Respeta correcciones del dictante.

=== CONCLUSION ===
Solo si el dictado la trae o lo solicita.

=== SALIDA (obligatorio) ===
- Devuelve SOLO el informe en HTML.
- Mantén la estructura y etiquetas de la PLANTILLA BASE.

=== PLANTILLA BASE (no modificar) ===
<<<PLANTILLA_BASE
{$plantilla_base}
PLANTILLA_BASE

=== EJEMPLOS (solo estilo) ===
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
