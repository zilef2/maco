<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;
use Throwable;

/**
 * Archivo MPP: Materiales, Productos y Proveedores en un solo Excel.
 *
 * El libro debe traer tres hojas llamadas "material", "producto" y
 * "proveedor"; cualquier otra hoja se descarta. Cada hoja se lee con el mismo
 * formato de su tarjeta en el Centro de Importación (ExcelMaterialesService,
 * ExcelProductosService y ExcelTalleresService), así que lo que sirve para
 * el MPP sirve también para la carga por entidad. La hoja "proveedor" es la
 * ficha de terceros: su columna de clasificación decide si cada fila termina
 * en Taller, Empresa externa o Proveedor.
 *
 * En la petición de subida solo se valida la fila de títulos; las filas de
 * datos se validan e insertan en el job. La importación es una sola
 * transacción: si una hoja falla, no queda nada importado de las otras dos.
 */
class ExcelMPPService
{
    /**
     * Hojas que se leen, en el orden en que se importan. Los materiales apuntan
     * al proveedor habitual, así que la ficha de terceros va primero: un
     * proveedor nuevo escrito en el mismo archivo ya está en la BD cuando llega
     * su material.
     */
    public const HOJAS = ['proveedor', 'material', 'producto'];

    public function __construct(
        private ExcelSecurityScanner $scanner,
        private ExcelMaterialesService $materiales,
        private ExcelProductosService $productos,
        private ExcelTalleresService $talleres,
    ) {
    }

    /**
     * @return string[] Problemas del contenedor (vacío si el archivo es seguro)
     */
    public function escanearSeguridad(string $ruta): array
    {
        return $this->scanner->escanear($ruta);
    }

    /**
     * Lo que se comprueba antes de encolar el archivo: que estén las tres hojas
     * y que sus títulos cumplan el formato de cada entidad.
     *
     * @throws RuntimeException con el aviso que debe ver el usuario
     */
    public function validarLibro(string $ruta): void
    {
        $titulos = $this->titulosPorHoja($ruta);

        $faltantes = array_values(array_diff(self::HOJAS, array_keys($titulos)));
        if (!empty($faltantes)) {
            throw new RuntimeException($this->avisoHojasFaltantes($faltantes));
        }

        foreach (self::HOJAS as $hoja) {
            $this->conAvisoDeHoja($hoja, function () use ($hoja, $titulos): void {
                if ($hoja === 'material') {
                    $this->materiales->validarTitulos($titulos[$hoja]);
                } elseif ($hoja === 'producto') {
                    $this->productos->validarTitulos($titulos[$hoja]);
                } else {
                    $this->talleres->validarTitulos($titulos[$hoja]);
                }
            });
        }
    }

    /**
     * Importa las tres hojas del archivo.
     *
     * @param  int|null  $usuarioId  El que subió el archivo; en la cola no hay
     *                               sesión y los movimientos de inventario
     *                               necesitan saber quién los causó.
     *
     * @return array{creados: int, actualizados: int, filas: int, desglose: array<string, array{creados: int, actualizados: int}>}
     *
     * @throws RuntimeException cuando alguna hoja no se puede importar
     */
    public function importar(string $ruta, ?int $usuarioId = null): array
    {
        $filasPorHoja = $this->filasPorHoja($ruta);

        $faltantes = array_values(array_diff(self::HOJAS, array_keys($filasPorHoja)));
        if (!empty($faltantes)) {
            throw new RuntimeException($this->avisoHojasFaltantes($faltantes));
        }

        $desglose = [];

        DB::transaction(function () use ($filasPorHoja, $usuarioId, &$desglose): void {
            foreach (self::HOJAS as $hoja) {
                $desglose[$hoja] = $this->conAvisoDeHoja(
                    $hoja,
                    fn (): array => $this->importarHoja($hoja, $filasPorHoja[$hoja], $usuarioId)
                );
            }
        });

        return [
            'creados' => array_sum(array_column($desglose, 'creados')),
            'actualizados' => array_sum(array_column($desglose, 'actualizados')),
            'filas' => array_sum(array_column($desglose, 'filas')),
            'desglose' => array_map(
                fn (array $resultado): array => [
                    'creados' => $resultado['creados'],
                    'actualizados' => $resultado['actualizados'],
                ],
                $desglose
            ),
        ];
    }

    /**
     * Título de hoja escrito como sea ("Material", "PRODUCTO ", " proveedor ") y
     * su nombre canónico, o null si la hoja se descarta.
     */
    public static function claveDeHoja(string $titulo): ?string
    {
        $clave = preg_replace(
            '/[^a-z0-9]/',
            '',
            mb_strtolower(trim($titulo), 'UTF-8')
        );
        $clave = strtr((string) $clave, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

        return in_array($clave, self::HOJAS, true) ? $clave : null;
    }

    /**
     * Lee solo la fila 1 de cada hoja que interesa.
     *
     * @return array<string, array<int, mixed>> nombre canónico => títulos
     */
    private function titulosPorHoja(string $ruta): array
    {
        $spreadsheet = $this->cargar($ruta, true);

        try {
            $porHoja = [];
            foreach ($spreadsheet->getAllSheets() as $hoja) {
                $clave = self::claveDeHoja($hoja->getTitle());
                if ($clave === null || isset($porHoja[$clave])) {
                    continue;
                }
                $porHoja[$clave] = $hoja->toArray(null, true, true, false)[0] ?? [];
            }

            return $porHoja;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Lee todas las filas de las tres hojas que interesa.
     *
     * @return array<string, array<int, array<int, mixed>>> nombre canónico => filas
     */
    private function filasPorHoja(string $ruta): array
    {
        $spreadsheet = $this->cargar($ruta, false);

        try {
            $porHoja = [];
            foreach ($spreadsheet->getAllSheets() as $hoja) {
                $clave = self::claveDeHoja($hoja->getTitle());
                if ($clave === null || isset($porHoja[$clave])) {
                    continue;
                }
                $porHoja[$clave] = $hoja->toArray(null, true, true, false);
            }

            return $porHoja;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Carga el libro sin estilos y solo con las hojas del MPP: en la validación
     * de títulos además se lee únicamente la fila 1, que es lo que hace rápida
     * la petición de subida.
     */
    private function cargar(string $ruta, bool $soloTitulos): Spreadsheet
    {
        try {
            $reader = IOFactory::createReaderForFile($ruta);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new class($soloTitulos) implements IReadFilter
            {
                public function __construct(private bool $soloTitulos)
                {
                }

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    // Las hojas que no son material, producto ni proveedor se
                    // descartan sin leerlas.
                    if (ExcelMPPService::claveDeHoja($worksheetName) === null) {
                        return false;
                    }

                    return ! $this->soloTitulos || $row === 1;
                }
            });

            return $reader->load($ruta);
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException('No se pudo leer el archivo Excel: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Valida las filas de una hoja e inserta. Devuelve el conteo de esa hoja.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @return array{creados: int, actualizados: int, filas: int}
     */
    private function importarHoja(string $hoja, array $filas, ?int $usuarioId = null): array
    {
        if ($hoja === 'proveedor') {
            $registros = $this->talleres->registrosDesdeFilas($filas);

            return $this->conFilas($this->talleres->importar($registros), count($registros));
        }

        if ($hoja === 'material') {
            $lectura = $this->materiales->registrosDesdeFilas($filas);

            return $this->conFilas(
                $this->materiales->importar($lectura['registros'], $usuarioId),
                count($lectura['registros'])
            );
        }

        $lectura = $this->productos->registrosDesdeFilas($filas);

        return $this->conFilas($this->productos->importar($lectura['registros']), count($lectura['registros']));
    }

    /**
     * @param  array{creados: int, actualizados: int}  $resultado
     * @return array{creados: int, actualizados: int, filas: int}
     */
    private function conFilas(array $resultado, int $filas): array
    {
        return $resultado + ['filas' => $filas];
    }

    /**
     * En un libro de tres hojas, un aviso sin hoja no se puede corregir: todo
     * error se precede con el nombre de la hoja que lo produjo.
     */
    private function conAvisoDeHoja(string $hoja, callable $accion)
    {
        try {
            return $accion();
        } catch (Throwable $e) {
            throw new RuntimeException('La hoja "' . $hoja . '": ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param string[] $faltantes
     */
    private function avisoHojasFaltantes(array $faltantes): string
    {
        return 'Faltan las hojas: ' . implode(', ', $faltantes)
            . '. El archivo debe traer tres hojas llamadas "material", "producto" y "proveedor".';
    }
}
