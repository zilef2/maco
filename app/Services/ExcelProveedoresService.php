<?php

namespace App\Services;

use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Validación, escaneo de seguridad e importación de proveedores desde Excel.
 *
 * Las columnas permitidas se toman directamente del $fillable del modelo:
 * nombre, categoria, celular1 y celular2. nombre y categoria son
 * obligatorias; los celulares son opcionales.
 */
class ExcelProveedoresService
{
    public const COLUMNAS_REQUERIDAS = ['nombre', 'categoria'];

    public function __construct(private ExcelSecurityScanner $scanner)
    {
    }

    /**
     * Columnas permitidas, derivadas del fillable del modelo.
     *
     * @return string[]
     */
    public function columnasPermitidas(): array
    {
        return (new Proveedor())->getFillable();
    }

    /**
     * Columnas opcionales (todas las del fillable menos las obligatorias).
     *
     * @return string[]
     */
    public function columnasOpcionales(): array
    {
        return array_values(array_diff($this->columnasPermitidas(), self::COLUMNAS_REQUERIDAS));
    }

    /**
     * @return string[]
     */
    public function escanearSeguridad(string $ruta): array
    {
        return $this->scanner->escanear($ruta);
    }

    /**
     * Lee la primera hoja, valida los títulos (fila 1) y todas las filas de
     * datos. Devuelve los registros listos para importar.
     *
     * @return array<int, array{nombre: string, categoria: string, celular1: ?string, celular2: ?string}>
     *
     * @throws RuntimeException cuando el archivo no cumple el formato
     */
    public function leerRegistros(string $ruta): array
    {
        try {
            $spreadsheet = IOFactory::load($ruta);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo leer el archivo Excel: ' . $e->getMessage(), 0, $e);
        }

        $filas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if (empty($filas)) {
            throw new RuntimeException('El archivo está vacío.');
        }

        $permitidas = $this->columnasPermitidas();
        $filaTitulos = $filas[0] ?? [];
        $columnas = [];
        $desconocidas = [];

        foreach ($filaTitulos as $indice => $valor) {
            $titulo = $this->normalizarTitulo((string) $valor);
            if ($titulo === '') {
                continue;
            }

            if (!in_array($titulo, $permitidas, true)) {
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
                . '. Solo se aceptan: ' . implode(', ', $permitidas) . '.'
            );
        }

        $faltantes = array_values(array_diff(self::COLUMNAS_REQUERIDAS, $columnas));
        if (!empty($faltantes)) {
            throw new RuntimeException(
                'La fila 1 debe contener los títulos de las columnas y faltan las obligatorias: '
                . implode(', ', $faltantes) . '.'
            );
        }

        $indices = array_flip($columnas); // titulo => índice de columna
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

            $nombre = $this->limpiarValor($fila[$indices['nombre']] ?? null);
            $categoria = $this->limpiarValor($fila[$indices['categoria']] ?? null);

            if ($nombre === '') {
                $errores[] = "Fila {$numeroFila}: el nombre es obligatorio.";
                continue;
            }
            if (mb_strlen($nombre) > 255) {
                $errores[] = "Fila {$numeroFila}: el nombre supera los 255 caracteres.";
                continue;
            }
            if ($categoria === '') {
                $errores[] = "Fila {$numeroFila}: la categoría es obligatoria.";
                continue;
            }
            if (mb_strlen($categoria) > 255) {
                $errores[] = "Fila {$numeroFila}: la categoría supera los 255 caracteres.";
                continue;
            }

            $celular1 = isset($indices['celular1'])
                ? $this->leerCelular($fila[$indices['celular1']] ?? null, $numeroFila, 'celular1', $errores)
                : null;
            $celular2 = isset($indices['celular2'])
                ? $this->leerCelular($fila[$indices['celular2']] ?? null, $numeroFila, 'celular2', $errores)
                : null;

            $registros[] = [
                'nombre' => $nombre,
                'categoria' => $categoria,
                'celular1' => $celular1,
                'celular2' => $celular2,
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
     * Inserta los proveedores nuevos y actualiza los existentes.
     * La comparación por nombre no distingue mayúsculas/minúsculas.
     *
     * @param  array<int, array{nombre: string, categoria: string, celular1: ?string, celular2: ?string}>  $registros
     * @return array{creados: int, actualizados: int}
     */
    public function importar(array $registros): array
    {
        $creados = 0;
        $actualizados = 0;

        DB::transaction(function () use ($registros, &$creados, &$actualizados) {
            $existentes = Proveedor::query()
                ->get(['id', 'nombre'])
                ->mapWithKeys(fn (Proveedor $proveedor) => [mb_strtolower($proveedor->nombre) => $proveedor->id])
                ->all();

            foreach ($registros as $registro) {
                $clave = mb_strtolower($registro['nombre']);

                if (isset($existentes[$clave])) {
                    Proveedor::whereKey($existentes[$clave])->update([
                        'categoria' => $registro['categoria'],
                        'celular1' => $registro['celular1'],
                        'celular2' => $registro['celular2'],
                    ]);
                    $actualizados++;
                    continue;
                }

                $nuevo = Proveedor::create($registro);
                $existentes[$clave] = $nuevo->id;
                $creados++;
            }
        });

        return [
            'creados' => $creados,
            'actualizados' => $actualizados,
        ];
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

    /**
     * Normaliza un celular: acepta separadores comunes y valida que solo
     * queden dígitos. Devuelve null cuando la celda está vacía.
     *
     * @param  array<int, string>  $errores
     */
    private function leerCelular(mixed $valor, int $numeroFila, string $campo, array &$errores): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        // Excel puede devolver el celular como número; se evita la notación científica.
        if (is_int($valor) || is_float($valor)) {
            $texto = number_format((float) $valor, 0, '', '');
        }

        $limpio = preg_replace('/[\s\+\-\(\)\.]+/', '', $texto);
        if ($limpio === '' || !ctype_digit($limpio)) {
            $errores[] = "Fila {$numeroFila}: {$campo} debe contener solo números.";

            return null;
        }

        if (strlen($limpio) > 15) {
            $errores[] = "Fila {$numeroFila}: {$campo} supera los 15 dígitos.";

            return null;
        }

        return $limpio;
    }

    public function procesarSubida($archivo, $usuario): string
    {
        if (!$archivo) {
            throw new RuntimeException('Selecciona un archivo .xlsx.');
        }

        $nombreArchivo = $archivo->getClientOriginalName();
        $problemas = $this->escanearSeguridad($archivo->getRealPath());
        if (!empty($problemas)) {
            throw new RuntimeException(implode(' | ', $problemas));
        }

        $this->leerRegistros($archivo->getRealPath());

        $rutaRelativa = $archivo->store('imports/proveedores', 'local');
        if (!$rutaRelativa) {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }

        \App\Jobs\ImportarProveedoresJob::dispatch(
            $rutaRelativa,
            $nombreArchivo,
            $usuario?->email,
            $usuario?->name,
            $usuario?->id,
        );

        return $rutaRelativa;
    }
}
