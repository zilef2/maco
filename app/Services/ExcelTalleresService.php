<?php

namespace App\Services;

use App\Models\Taller;
use App\Services\Concerns\LeeExcelRapido;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Validación, escaneo de seguridad e importación de talleres desde Excel.
 *
 * El archivo debe ser un .xlsx real (contenedor ZIP/OOXML) donde la fila 1
 * contiene los títulos de las columnas y las siguientes filas los datos.
 */
class ExcelTalleresService
{
    use LeeExcelRapido;

    public const MAX_BYTES = ExcelSecurityScanner::MAX_BYTES;

    /** Columnas obligatorias y permitidas del archivo. */
    public const COLUMNAS_REQUERIDAS = ['nombre'];

    /** La dirección es opcional. */
    public const COLUMNAS_PERMITIDAS = ['nombre', 'direccion'];

    public function __construct(private ExcelSecurityScanner $scanner)
    {
    }

    /**
     * Escaneo de seguridad del archivo .xlsx.
     *
     * @return string[]
     */
    public function escanearSeguridad(string $ruta): array
    {
        return $this->scanner->escanear($ruta);
    }

    /**
     * Valida únicamente la fila de títulos en la petición de subida.
     *
     * @throws RuntimeException cuando el archivo no cumple el formato
     */
    public function validarEncabezados(string $ruta): void
    {
        $this->columnasDesdeTitulos($this->leerFilaTitulos($ruta));
    }

    /**
     * Lee la primera hoja, valida los títulos (fila 1) y todas las filas de
     * datos. Devuelve los registros listos para importar.
     *
     * @return array<int, array{nombre: string, direccion: ?string}>
     *
     * @throws RuntimeException cuando el archivo no cumple el formato
     */
    public function leerRegistros(string $ruta): array
    {
        $filas = $this->leerTodasLasFilas($ruta);
        if (empty($filas)) {
            throw new RuntimeException('El archivo está vacío.');
        }

        $columnas = $this->columnasDesdeTitulos($filas[0] ?? []);
        $indiceNombre = array_search('nombre', $columnas, true);
        $indiceDireccion = array_search('direccion', $columnas, true);
        $registros = [];
        $errores = [];

        foreach (array_slice($filas, 1) as $posicion => $fila) {
            $numeroFila = $posicion + 2; // La fila 1 son los títulos.

            $filaVacia = true;
            foreach ($fila as $valor) {
                if ($valor !== null && trim((string) $valor) !== '') {
                    $filaVacia = false;
                    break;
                }
            }
            if ($filaVacia) {
                continue;
            }

            $nombre = $this->limpiarValor($fila[$indiceNombre] ?? null);
            $direccion = $indiceDireccion !== false
                ? $this->limpiarValor($fila[$indiceDireccion] ?? null)
                : '';

            if ($nombre === '') {
                $errores[] = "Fila {$numeroFila}: el nombre es obligatorio.";
                continue;
            }
            if (mb_strlen($nombre) > 255) {
                $errores[] = "Fila {$numeroFila}: el nombre supera los 255 caracteres.";
                continue;
            }
            if (mb_strlen($direccion) > 255) {
                $errores[] = "Fila {$numeroFila}: la dirección supera los 255 caracteres.";
                continue;
            }

            $registros[] = [
                'nombre' => $nombre,
                'direccion' => $direccion !== '' ? $direccion : null,
            ];
        }

        if (!empty($errores)) {
            throw new RuntimeException('El archivo tiene errores en sus datos: ' . implode(' | ', $errores));
        }
        if (empty($registros)) {
            throw new RuntimeException('El archivo no contiene filas de datos debajo de los títulos.');
        }

        return $registros;
    }

    /**
     * Inserta los talleres nuevos y actualiza la dirección de los existentes.
     * La comparación por nombre no distingue mayúsculas/minúsculas.
     *
     * @param  array<int, array{nombre: string, direccion: ?string}>  $registros
     * @return array{creados: int, actualizados: int}
     */
    public function importar(array $registros): array
    {
        $creados = 0;
        $actualizados = 0;

        DB::transaction(function () use ($registros, &$creados, &$actualizados) {
            $existentes = Taller::query()
                ->get(['id', 'nombre'])
                ->mapWithKeys(fn (Taller $taller) => [mb_strtolower($taller->nombre) => $taller->id])
                ->all();

            foreach ($registros as $registro) {
                $clave = mb_strtolower($registro['nombre']);

                if (isset($existentes[$clave])) {
                    Taller::whereKey($existentes[$clave])->update([
                        'direccion' => $registro['direccion'],
                    ]);
                    $actualizados++;
                    continue;
                }

                $nuevo = Taller::create($registro);
                $existentes[$clave] = $nuevo->id;
                $creados++;
            }
        });

        return [
            'creados' => $creados,
            'actualizados' => $actualizados,
        ];
    }

    /**
     * Resuelve y valida la fila de títulos.
     *
     * @param  array<int, mixed>  $filaTitulos
     * @return array<int, string> índice de columna => nombre canónico
     */
    private function columnasDesdeTitulos(array $filaTitulos): array
    {
        $columnas = [];
        $desconocidas = [];

        foreach ($filaTitulos as $indice => $valor) {
            $titulo = $this->normalizarTitulo((string) $valor);
            if ($titulo === '') {
                continue;
            }

            if (!in_array($titulo, self::COLUMNAS_PERMITIDAS, true)) {
                $desconocidas[] = trim((string) $valor);
                continue;
            }

            if (in_array($titulo, $columnas, true)) {
                throw new RuntimeException("La columna \"{$titulo}\" está repetida en la fila 1.");
            }

            $columnas[$indice] = $titulo;
        }

        if (!empty($desconocidas)) {
            throw new RuntimeException(
                'La fila 1 contiene columnas no permitidas: ' . implode(', ', $desconocidas)
                . '. Solo se aceptan "nombre" y "direccion".'
            );
        }

        if (!in_array('nombre', $columnas, true)) {
            throw new RuntimeException(
                'La fila 1 debe contener los títulos de las columnas y falta la columna obligatoria "nombre".'
            );
        }

        return $columnas;
    }

    /** Normaliza un título: minúsculas, sin acentos ni signos. */
    private function normalizarTitulo(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]/', '', $texto) ?? '';
    }

    /** Limpia el valor de una celda (sin HTML y sin espacios sobrantes). */
    private function limpiarValor(mixed $valor): string
    {
        return trim(strip_tags((string) ($valor ?? '')));
    }
}
