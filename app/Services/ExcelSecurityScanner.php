<?php

namespace App\Services;

use ZipArchive;

/**
 * Escaneo de seguridad para archivos .xlsx.
 *
 * Un .xlsx es un contenedor ZIP/OOXML. Se revisa la firma de bytes, la
 * estructura interna, rutas inseguras, macros, objetos incrustados, vínculos
 * externos, recursos peligrosos y el tamaño descomprimido (zip bomb).
 */
class ExcelSecurityScanner
{
    public const MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    /** Extensiones que nunca deben viajar dentro de un Excel. */
    private const EXTENSIONES_PELIGROSAS = [
        'exe', 'dll', 'com', 'msi', 'bat', 'cmd', 'ps1', 'vbs', 'js',
        'scr', 'jar', 'php', 'phtml', 'html', 'htm', 'svg',
    ];

    /** Tamaño máximo del contenido descomprimido (protección zip bomb). */
    private const MAX_DESCOMPRIMIDO = 200 * 1024 * 1024; // 200 MB

    /**
     * @return string[] Lista de problemas encontrados (vacía si el archivo es seguro).
     */
    public function escanear(string $ruta): array
    {
        $problemas = [];

        if (!is_file($ruta)) {
            return ['El archivo no existe o no se pudo leer.'];
        }

        if (filesize($ruta) > self::MAX_BYTES) {
            $problemas[] = 'El archivo supera el tamaño máximo de 10 MB.';
        }

        // Firma ZIP/OOXML: PK\x03\x04
        $handle = fopen($ruta, 'rb');
        $magic = $handle ? fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }
        if ($magic !== "PK\x03\x04") {
            $problemas[] = 'El archivo no tiene una firma XLSX válida (magic bytes incorrectos).';

            return array_values(array_unique($problemas));
        }

        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            $problemas[] = 'No se pudo abrir el archivo como contenedor ZIP/XLSX.';

            return array_values(array_unique($problemas));
        }

        $tieneContentTypes = false;
        $totalDescomprimido = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $nombre = (string) ($stat['name'] ?? '');
            $normalizado = str_replace('\\', '/', $nombre);
            $minusculas = mb_strtolower($normalizado);

            if ($minusculas === '[content_types].xml') {
                $tieneContentTypes = true;
            }

            // Zip slip / rutas absolutas
            if (str_contains($normalizado, '../')
                || str_starts_with($normalizado, '/')
                || preg_match('/^[a-z]:/i', $normalizado)) {
                $problemas[] = "Ruta insegura dentro del archivo: {$nombre}";
            }

            // Macros
            if (str_ends_with($minusculas, 'vbaproject.bin')) {
                $problemas[] = 'El archivo contiene macros (vbaProject.bin) y no está permitido.';
            }

            // Objetos incrustados
            if (str_contains($minusculas, 'xl/embeddings/')) {
                $problemas[] = "El archivo contiene objetos incrustados ({$nombre}) y no está permitido.";
            }

            // Vínculos externos
            if (str_contains($minusculas, 'xl/externallinks/')) {
                $problemas[] = 'El archivo contiene vínculos externos y no está permitido.';
            }

            $extension = mb_strtolower(pathinfo($normalizado, PATHINFO_EXTENSION));
            if (in_array($extension, self::EXTENSIONES_PELIGROSAS, true)) {
                $problemas[] = "El archivo contiene un recurso potencialmente peligroso: {$nombre}";
            }

            $totalDescomprimido += (int) ($stat['size'] ?? 0);
            if ($totalDescomprimido > self::MAX_DESCOMPRIMIDO) {
                $problemas[] = 'El contenido descomprimido excede el límite de seguridad (posible zip bomb).';
                break;
            }
        }

        $zip->close();

        if (!$tieneContentTypes) {
            $problemas[] = 'El archivo no tiene la estructura interna de un Excel (.xlsx).';
        }

        return array_values(array_unique($problemas));
    }
}
