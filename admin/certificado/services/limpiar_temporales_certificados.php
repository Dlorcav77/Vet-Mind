<?php
// admin/certificado/services/limpiar_temporales_certificados.php

if (!function_exists('limpiarDirectorioTemporalCertificados')) {
    function limpiarDirectorioTemporalCertificados($directorio, $segundosMaximos, $recursivo = false)
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

if (!function_exists('limpiarTemporalesCertificados')) {
    function limpiarTemporalesCertificados()
    {
        $tresHoras = 3 * 60 * 60;

        return [
            'tmp_img' => limpiarDirectorioTemporalCertificados('uploads/tmp/img', $tresHoras, false),
            'tmp_informe' => limpiarDirectorioTemporalCertificados('uploads/tmp/informe', $tresHoras, false),
            'tmp_audio' => limpiarDirectorioTemporalCertificados('uploads/tmp/audio', $tresHoras, false),
        ];
    }
}