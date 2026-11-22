<?php

namespace App\Services;

use App\Exceptions\ExcelRechazado;
use Throwable;

/**
 * El detalle completo de los errores del archivo MPP, en storage/logs/MPP.log.
 *
 * El correo trae los primeros avisos y el diagnóstico con IA; el log del job
 * trae la traza. Ninguno de los dos sirve para corregir el Excel, así que aquí
 * se escribe solo la lista de problemas, una línea por problema y sin
 * repetidos, en la forma corta que ya usa la validación:
 *
 *   "Marquilla / etiqueta Macondas" (coincide con varios registros)
 *   "Elástico blanco para terminación externa" (no existe en la app)
 *
 * Se escribe en la subida (errores de hojas y de títulos) y en el job (errores
 * de las filas), que son los dos momentos en que el archivo puede rechazarse.
 */
class RegistroErroresMpp
{
    public function registrar(string $nombreArchivo, ?string $usuario, Throwable $error): void
    {
        $errores = $error instanceof ExcelRechazado && $error->errores() !== []
            ? $error->errores()
            : [$error->getMessage()];

        $this->escribir(sprintf(
            "[%s] %s%s | %d error(es)\n%s\n",
            now()->format('Y-m-d H:i:s'),
            $nombreArchivo,
            $usuario !== null ? ' (usuario: ' . $usuario . ')' : '',
            count($errores),
            implode("\n", array_map(fn (string $e): string => '  - ' . $e, $errores))
        ));
    }

    /**
     * Un log no puede tumbar ni la subida del archivo ni el job: si el disco
     * dice que no, la importación sigue su camino y el correo se envía igual.
     */
    private function escribir(string $texto): void
    {
        try {
            $ruta = storage_path('logs/MPP.log');

            if (!is_dir(dirname($ruta))) {
                @mkdir(dirname($ruta), 0775, true);
            }

            @file_put_contents($ruta, $texto, FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
        }
    }
}
