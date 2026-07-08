<?php
declare(strict_types=1);
date_default_timezone_set('America/Santiago');
/**
 * Guarda un request de IA en la tabla ia_requests.
 * Reutilizable por informe (gpt/grok/claude) y revisor.
 * Devuelve el id insertado, o 0 si falla (no rompe el flujo).
 */
function ia_guardar_request(mysqli $mysqli, array $d): int
{
    $rid          = (string)($d['rid'] ?? '');
    $tipo         = (string)($d['tipo'] ?? 'informe');
    $plantilla_id = isset($d['plantilla_id']) ? (int)$d['plantilla_id'] : null;
    $provider     = (string)($d['provider'] ?? '');
    $model        = (string)($d['model'] ?? '');

    $input_json   = isset($d['input']) ? json_encode($d['input'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $system_text  = (string)($d['system'] ?? '');
    $prompt_text  = (string)($d['prompt'] ?? '');
    $content      = (string)($d['content_final'] ?? '');

    $system_hash  = $system_text !== '' ? substr(hash('sha256', $system_text), 0, 16) : null;
    $prompt_hash  = $prompt_text !== '' ? substr(hash('sha256', $prompt_text), 0, 16) : null;

    $pt   = (int)($d['prompt_tokens'] ?? 0);
    $ct   = (int)($d['completion_tokens'] ?? 0);
    $tt   = (int)($d['total_tokens'] ?? ($pt + $ct));
    $cost = (float)($d['cost_usd'] ?? 0);

    $dt_ia = null;
    if (!empty($d['datetime_ia'])) {
        $ts = strtotime((string)$d['datetime_ia']);
        if ($ts !== false) {
            $dt_ia = date('Y-m-d H:i:s', $ts);
        }
    }

    $plantilla_bind = ($plantilla_id !== null && $plantilla_id > 0) ? $plantilla_id : null;

    $created = date('Y-m-d H:i:s'); // PHP ya está en America/Santiago

    $sql = 'INSERT INTO ia_requests
        (rid, tipo, certificado_id, plantilla_id, provider, model,
         input_json, system_text, prompt_text, content_final,
         system_hash, prompt_hash,
         prompt_tokens, completion_tokens, total_tokens, cost_usd, datetime_ia, created_at)
        VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param(
        'ssissssssssiiidss',
        $rid,
        $tipo,
        $plantilla_bind,
        $provider,
        $model,
        $input_json,
        $system_text,
        $prompt_text,
        $content,
        $system_hash,
        $prompt_hash,
        $pt,
        $ct,
        $tt,
        $cost,
        $dt_ia,
        $created
    );

    $ok = $stmt->execute();
    $id = $ok ? (int)$stmt->insert_id : 0;
    $stmt->close();

    return $id;
}

/**
 * Enlaza un request ya guardado con el certificado creado después.
 * Se llama cuando se genera el certificado, usando el rid del request.
 */
function ia_link_certificado(mysqli $mysqli, string $rid, int $certificado_id): bool
{
    if ($rid === '' || $certificado_id <= 0) {
        return false;
    }

    $stmt = $mysqli->prepare(
        'UPDATE ia_requests SET certificado_id = ? WHERE rid = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $certificado_id, $rid);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}