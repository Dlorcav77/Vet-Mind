<?php
// admin/certificado/services/limpiar_temporales_certificados.php

if (!function_exists('limpiarDirectorioTemporalCertificados')) {
    function limpiarDirectorioTemporalCertificados(
        $directorio,
        $segundosMaximos,
        $recursivo = false,
        array $rutasProtegidas = []
    )
    {
        $basePath = realpath(__DIR__ . '/../../../');

        if ($basePath === false) {
            return [
                'ok' => false,
                'eliminados' => 0,
                'errores' => 0,
                'message' => 'No se pudo resolver basePath.'
            ];
        }

        $dirPath = realpath($basePath . '/' . ltrim($directorio, '/'));

        if ($dirPath === false || !is_dir($dirPath)) {
            return [
                'ok' => true,
                'eliminados' => 0,
                'errores' => 0,
                'message' => 'Directorio no existe.'
            ];
        }

        $rutasPermitidas = [
            realpath($basePath . '/uploads/tmp/img'),
            realpath($basePath . '/uploads/tmp/informe'),
            realpath($basePath . '/uploads/tmp/audio'),
        ];

        $permitido = false;

        foreach ($rutasPermitidas as $rutaPermitida) {
            if ($rutaPermitida !== false && $dirPath === $rutaPermitida) {
                $permitido = true;
                break;
            }
        }

        if (!$permitido) {
            return [
                'ok' => false,
                'eliminados' => 0,
                'errores' => 1,
                'message' => 'Directorio no permitido.'
            ];
        }

        $ahora = time();
        $eliminados = 0;
        $errores = 0;

        $protegidas = [];

        foreach ($rutasProtegidas as $rutaProtegida) {
            $realProtegida = realpath($rutaProtegida);

            if ($realProtegida !== false) {
                $protegidas[$realProtegida] = true;
            }
        }

        if ($recursivo) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
        } else {
            $iterator = new DirectoryIterator($dirPath);
        }

        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }

            $itemPath = $item->getPathname();

            if ($item->isDir()) {
                continue;
            }

            if (!$item->isFile()) {
                continue;
            }

            $mtime = $item->getMTime();

            if (($ahora - $mtime) < $segundosMaximos) {
                continue;
            }

            $realItemPath = realpath($itemPath);

            if ($realItemPath === false) {
                $errores++;
                continue;
            }

            if (strpos($realItemPath, $dirPath . DIRECTORY_SEPARATOR) !== 0) {
                $errores++;
                continue;
            }

            if (isset($protegidas[$realItemPath])) {
                continue;
            }

            if (@unlink($realItemPath)) {
                $eliminados++;
            } else {
                $errores++;
            }
        }

        return [
            'ok' => true,
            'eliminados' => $eliminados,
            'errores' => $errores,
            'message' => 'Limpieza ejecutada.'
        ];
    }
}

if (!function_exists('obtenerAudiosTemporalesProtegidosBorradores')) {
    function obtenerAudiosTemporalesProtegidosBorradores()
    {
        /*
         * Si este servicio se ejecuta desde un contexto donde conn()
         * no está disponible, preferimos NO limpiar audios.
         *
         * Así evitamos borrar accidentalmente un audio perteneciente
         * a un borrador activo.
         */
        if (!function_exists('conn')) {
            return null;
        }

        $mysqli = conn();

        if (!$mysqli) {
            return null;
        }

        $basePath = realpath(__DIR__ . '/../../../');

        if ($basePath === false) {
            return null;
        }

        $protegidos = [];

        $stmt = $mysqli->prepare("
            SELECT payload_json
            FROM certificados_borradores
            WHERE estado = 'activo'
              AND payload_json IS NOT NULL
              AND payload_json <> ''
        ");

        if (!$stmt) {
            return null;
        }

        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $payload = json_decode(
                (string)($row['payload_json'] ?? ''),
                true
            );

            if (!is_array($payload)) {
                continue;
            }

            $audioTmp = trim(
                (string)($payload['audio_tmp'] ?? '')
            );

            if ($audioTmp === '') {
                continue;
            }

            $audioTmp = str_replace('\\', '/', $audioTmp);
            $audioTmp = ltrim($audioTmp, '/');

            $prefix = 'uploads/tmp/audio/';

            if (strpos($audioTmp, $prefix) !== 0) {
                continue;
            }

            $nombre = basename($audioTmp);

            if (
                $nombre === '' ||
                $nombre === '.' ||
                $nombre === '..'
            ) {
                continue;
            }

            $rutaReal = realpath(
                $basePath . '/' . $prefix . $nombre
            );

            if ($rutaReal !== false) {
                $protegidos[$rutaReal] = true;
            }
        }

        $stmt->close();

        return array_keys($protegidos);
    }
}

if (!function_exists('limpiarTemporalesCertificados')) {
    function limpiarTemporalesCertificados()
    {
        $tresHoras = 3 * 60 * 60;

        $audiosProtegidos =
            obtenerAudiosTemporalesProtegidosBorradores();

        /*
        * Si no pudimos consultar los borradores activos,
        * no limpiamos audio. Es preferible conservar algunos
        * temporales a eliminar el audio de un borrador válido.
        */
        if ($audiosProtegidos === null) {
            $resultadoAudio = [
                'ok' => true,
                'eliminados' => 0,
                'errores' => 0,
                'message' => 'Limpieza de audio omitida: no fue posible validar borradores activos.'
            ];
        } else {
            $resultadoAudio =
                limpiarDirectorioTemporalCertificados(
                    'uploads/tmp/audio',
                    $tresHoras,
                    false,
                    $audiosProtegidos
                );
        }

        return [
            'tmp_img' =>
                limpiarDirectorioTemporalCertificados(
                    'uploads/tmp/img',
                    $tresHoras,
                    false
                ),

            'tmp_informe' =>
                limpiarDirectorioTemporalCertificados(
                    'uploads/tmp/informe',
                    $tresHoras,
                    false
                ),

            'tmp_audio' => $resultadoAudio,
        ];
    }
}