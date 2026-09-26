<?php
declare(strict_types=1);

/**
 * Orquestador de interpretación clínica.
 *
 * actual: conserva el procesamiento existente.
 * php: ejecuta el preprocesador determinista.
 * ia: reservado para el motor económico.
 * comparacion: reservado para el banco de pruebas.
 */
function interpretacion_ejecutar(
    mysqli $mysqli,
    array $entrada,
    string $apiKey = '',
    ?string $modoPrueba = null
): array
{
    $flujo = require __DIR__ . '/../config/flujo.php';
    $modo = $modoPrueba ?? (string)($flujo['interpretacion'] ?? 'actual');
    $texto = trim((string)($entrada['texto'] ?? ''));

    if ($texto === '') {
        throw new InvalidArgumentException('El dictado está vacío.');
    }

    if ($modo === 'actual') {
        return [
            'modo' => 'actual',
            'texto_original' => $texto,
            'transcripcion_id' => null,
            'origen_encontrado' => false,
            'resultado' => null
        ];
    }

    if (!in_array($modo, ['php', 'ia', 'comparacion'], true)) {
        throw new InvalidArgumentException(
            'Modo de interpretación no válido.'
        );
    }

    $usuarioId = (int)($entrada['usuario_id'] ?? 0);
    $flujoId = trim((string)($entrada['flujo_id'] ?? ''));

    if ($usuarioId <= 0) {
        throw new InvalidArgumentException('Usuario no válido.');
    }

    if ($modo === 'comparacion') {
        throw new LogicException(
            'La comparación debe ejecutarse desde el banco de pruebas.'
        );
    }

    require_once __DIR__ . '/lib/interpretacion_origen.php';

    $origen = interpretacion_cargar_origen(
        $mysqli,
        $usuarioId,
        $flujoId,
        $texto
    );

    if ($modo === 'php') {
        require_once __DIR__ . '/interpretacion_php.php';
        require_once __DIR__ . '/lib/interpretacion_store.php';

        $inicio = hrtime(true);
        $resultado = null;
        $error = null;

        try {
            $resultado = interpretacion_procesar_php($texto, $origen);
        } catch (Throwable $e) {
            $error = $e;
        }

        $duracionMs = (int) round((hrtime(true) - $inicio) / 1000000);

        $interpretacionId = interpretacion_guardar_resultado($mysqli, [
            'usuario_id' => $usuarioId,
            'transcripcion_id' => $origen['id'] ?? null,
            'flujo_id' => $flujoId,
            'texto_entrada' => $texto,
            'resultado' => $resultado,
            'motor' => 'php',
            'modelo' => null,
            'version_esquema' => '1',
            'estado' => $error === null ? 'completado' : 'fallido',
            'error_detalle' => $error?->getMessage(),
            'duracion_ms' => $duracionMs
        ]);

        if ($error !== null) {
            throw $error;
        }

        return [
            'modo' => 'php',
            'interpretacion_id' => $interpretacionId,
            'texto_original' => $texto,
            'transcripcion_id' => $origen['id'] ?? null,
            'origen_encontrado' => $origen !== null,
            'resultado' => $resultado
        ];
    }

    if ($modo === 'ia') {
        require_once __DIR__ . '/lib/interpretacion_prompt.php';
        require_once __DIR__ . '/lib/interpretacion_store.php';

        $config = require __DIR__ . '/../config/motores.php';
        $configInt = $config['interpretacion'] ?? [];
        $motor = (string)($configInt['motor_activo'] ?? '');

        if ($motor !== 'openai') {
            throw new LogicException('Motor de interpretación no implementado.');
        }

        $configMotor = $configInt['parametros'][$motor] ?? null;
        $archivo = is_array($configMotor)
            ? (string)($configMotor['archivo'] ?? '')
            : '';

        $rutaMotor = dirname(__DIR__) . '/' . $archivo;

        if ($archivo === '' || !is_file($rutaMotor)) {
            throw new RuntimeException('Motor de interpretación no disponible.');
        }

        require_once $rutaMotor;

        $inicio = hrtime(true);
        $respuesta = null;
        $error = null;

        try {
            $promptData = interpretacion_build_prompt(
                $texto,
                (string)($entrada['plantilla_base'] ?? ''),
                $entrada,
                $origen
            );

            $respuesta = interpretacion_openai(
                $promptData,
                $configMotor,
                $apiKey,
                $origen['resueltas'] ?? []
            );
        } catch (Throwable $e) {
            $error = $e;
        }

        $duracionMs = (int)round((hrtime(true) - $inicio) / 1000000);

        $interpretacionId = interpretacion_guardar_resultado($mysqli, [
            'usuario_id' => $usuarioId,
            'transcripcion_id' => $origen['id'] ?? null,
            'flujo_id' => $flujoId,
            'texto_entrada' => $texto,
            'resultado' => $respuesta['resultado'] ?? null,
            'motor' => $motor,
            'modelo' => $respuesta['modelo'] ?? $configMotor['model'],
            'version_prompt' => $configMotor['version_prompt'] ?? '1',
            'estado' => $error === null ? 'completado' : 'fallido',
            'error_detalle' => $error?->getMessage(),
            'prompt_tokens' => $respuesta['prompt_tokens'] ?? 0,
            'completion_tokens' => $respuesta['completion_tokens'] ?? 0,
            'cost_usd' => $respuesta['cost_usd'] ?? 0,
            'duracion_ms' => $duracionMs
        ]);

        if ($error !== null) {
            throw $error;
        }

        return [
            'modo' => 'ia',
            'interpretacion_id' => $interpretacionId,
            'texto_original' => $texto,
            'transcripcion_id' => $origen['id'] ?? null,
            'origen_encontrado' => $origen !== null,
            'resultado' => $respuesta['resultado']
        ];
    }
    throw new LogicException('Modo de interpretación no implementado.');
}
