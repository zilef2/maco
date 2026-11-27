<?php

namespace App\Services;

use App\Exceptions\ExcelRechazado;
use Throwable;

/**
 * El detalle completo de los errores del archivo MPP, en storage/logs/MPP.log.
 *
 * El correo trae los primeros avisos y el diagnóstico con IA; el log del job
 * trae la traza. Ninguno de los dos sirve para corregir el Excel, así que aquí
 * se escribe solo la lista de problemas, un renglón por problema y sin
 * repetidos, en la forma corta que ya usa la validación:
 *
 *   [2026-11-26 10:00:00] MPP.xlsx (usuario: Ana) | 2 error(es)
 *   Hoja "material":
 *     - Fila 64: "Base si o no" debe ser si o no y llegó "Suela EVA-caucho"
 *     - Fila 65: "Base si o no" debe ser si o no y llegó "Suela EVA"
 *
 * Antes cada mensaje quedaba en un solo renglón con los problemas separados
 * por " | ", que en producción es ilegible: aquí se abre la lista, se separa
 * cada fila y el nombre de la hoja queda como título de su grupo.
 *
 * Se escribe en la subida (errores de hojas y de títulos) y en el job (errores
 * de las filas), que son los dos momentos en que el archivo puede rechazarse.
 */
class RegistroErroresMpp
{
    /** Preámbulos con que los servicios anuncian su lista de problemas. */
    private const PREAMBULOS = [
        'No se importó nada por los siguientes problemas: ',
        'No se pudo importar por los siguientes problemas: ',
        'El archivo tiene errores en sus datos: ',
    ];

    public function registrar(string $nombreArchivo, ?string $usuario, Throwable $error): void
    {
        $lineas = $this->desglosar($error);

        $this->escribir(sprintf(
            "[%s] %s%s | %d error(es)\n%s\n\n",
            now()->format('Y-m-d H:i:s'),
            $nombreArchivo,
            $usuario !== null ? ' (usuario: ' . $usuario . ')' : '',
            count(array_filter($lineas, fn (string $linea): bool => str_starts_with($linea, '  - '))),
            implode("\n", $lineas)
        ));
    }

    /**
     * Convierte el mensaje (y la lista completa de la excepción) en renglones:
     * un título por hoja y una línea por cada problema.
     *
     * @return string[]
     */
    private function desglosar(Throwable $error): array
    {
        $detallados = $error instanceof ExcelRechazado ? $error->errores() : [];
        $fuentes = $detallados !== [] ? $detallados : [$error->getMessage()];

        $lineas = [];
        $vistos = [];
        $hojaActual = null;

        $abrirHoja = function (string $hoja) use (&$lineas, &$hojaActual): void {
            if ($hoja !== $hojaActual) {
                $lineas[] = 'Hoja "' . $hoja . '":';
                $hojaActual = $hoja;
            }
        };

        // El nombre de la hoja vive en el mensaje de la excepción ("La hoja
        // "material": ..."), aunque la lista detallada venga por separado.
        $hojaDelMensaje = $this->hojaDelTexto($error->getMessage());
        if ($hojaDelMensaje !== null) {
            $abrirHoja($hojaDelMensaje);
        }

        foreach ($fuentes as $fuente) {
            foreach (preg_split('/\s*\|\s*/', $fuente) ?: [$fuente] as $parte) {
                $parte = trim($this->quitarPreambulos($parte));

                // Algunos validadores ya anteponen "La hoja "x":" a cada
                // problema; se usa como título y se quita del renglón.
                $hoja = $this->hojaDelTexto($parte);
                if ($hoja !== null) {
                    $abrirHoja($hoja);
                    $parte = trim($this->quitarPreambulos($this->sinHoja($parte)));
                }

                if ($parte === '') {
                    continue;
                }

                // Sin repetidos dentro de la misma hoja, pero sin perder el
                // título de las demás hojas que vengan después.
                $clave = ($hojaActual ?? '') . "\0" . $parte;
                if (!isset($vistos[$clave])) {
                    $vistos[$clave] = true;
                    $lineas[] = '  - ' . $parte;
                }
            }
        }

        return $lineas;
    }

    private function hojaDelTexto(string $texto): ?string
    {
        return preg_match('/^La hoja "([^"]+)":/u', trim($texto), $coincidencias) === 1
            ? $coincidencias[1]
            : null;
    }

    private function sinHoja(string $texto): string
    {
        return preg_replace('/^La hoja "[^"]+":\s*/u', '', trim($texto), 1) ?? $texto;
    }

    private function quitarPreambulos(string $texto): string
    {
        foreach (self::PREAMBULOS as $preambulo) {
            $texto = str_replace($preambulo, '', $texto);
        }

        return trim($texto);
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
