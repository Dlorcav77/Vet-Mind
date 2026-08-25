<?php

declare(strict_types=1);


/**
 * Valida solamente la forma de una ruta interna del panel.
 *
 * Permite:
 * tutor/tutores.php
 * certificado/certificados.php?action=modificar&id=10
 */
function ruta_panel_valida(string $ruta): bool
{
    $ruta = trim($ruta);

    if ($ruta === '' || str_contains($ruta, "\0")) {
        return false;
    }

    $partes = parse_url($ruta);

    if ($partes === false) {
        return false;
    }

    // No se permiten URLs externas.
    if (
        isset($partes['scheme'])
        || isset($partes['host'])
        || isset($partes['user'])
        || isset($partes['pass'])
        || isset($partes['fragment'])
    ) {
        return false;
    }

    $path = $partes['path'] ?? '';

    if (
        $path === ''
        || $path[0] === '/'
        || str_contains($path, '..')
        || str_contains($path, '\\')
    ) {
        return false;
    }

    return (bool) preg_match(
        '#^(?:[A-Za-z0-9_-]+/)+[A-Za-z0-9_.-]+\.php$#',
        $path
    );
}


/**
 * Devuelve el archivo físico correspondiente a una ruta,
 * siempre que realmente esté dentro de /admin.
 */
function archivo_ruta_panel(string $ruta): string|false
{
    $partes = parse_url($ruta);

    if ($partes === false) {
        return false;
    }

    $path = $partes['path'] ?? '';

    $base = realpath(__DIR__);
    $archivo = realpath(__DIR__ . '/' . $path);

    if ($base === false || $archivo === false) {
        return false;
    }

    if (
        !str_starts_with(
            $archivo,
            $base . DIRECTORY_SEPARATOR
        )
    ) {
        return false;
    }

    return $archivo;
}


/**
 * Una ruta navegable debe corresponder a una pantalla
 * real de VetMind.
 *
 * Las pantallas actuales se identifican mediante:
 *
 * data-page-id="..."
 *
 * Esto deja fuera upd*.php y otros endpoints de proceso.
 */
function ruta_panel_navegable(string $ruta): bool
{
    if (!ruta_panel_valida($ruta)) {
        return false;
    }

    $archivo = archivo_ruta_panel($ruta);

    if ($archivo === false) {
        return false;
    }

    $contenido = file_get_contents($archivo);

    if ($contenido === false) {
        return false;
    }

    return (bool) preg_match(
        '/data-page-id\s*=/i',
        $contenido
    );
}


/**
 * Resuelve la pantalla solicitada.
 *
 * Si no es una pantalla navegable válida,
 * vuelve al inicio.
 */
function resolver_ruta_panel(
    mixed $solicitada,
    string $predeterminada = 'inicio/inicio.php'
): string {
    $ruta = trim((string) $solicitada);

    if ($ruta === '') {
        return $predeterminada;
    }

    if (!ruta_panel_navegable($ruta)) {
        return $predeterminada;
    }

    return $ruta;
}