<?php

namespace App\Services\Concerns;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;

/**
 * Lectura rápida de archivos .xlsx.
 *
 * En la petición de subida solo hace falta la fila de títulos: cargar todo el
 * libro para validar el encabezado era el mayor costo de las importaciones
 * "en segundo plano". El archivo completo se lee una sola vez, ya en el job.
 */
trait LeeExcelRapido
{
    /**
     * Devuelve únicamente la fila 1 de la hoja activa.
     *
     * @return array<int, mixed>
     */
    protected function leerFilaTitulos(string $ruta): array
    {
        try {
            $reader = IOFactory::createReaderForFile($ruta);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new class implements IReadFilter
            {
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row === 1;
                }
            });

            $spreadsheet = $reader->load($ruta);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo leer el archivo Excel: '.$e->getMessage(), 0, $e);
        }

        try {
            return $spreadsheet->getActiveSheet()->toArray(null, true, true, false)[0] ?? [];
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * Carga todas las filas de la hoja activa sin estilos.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function leerTodasLasFilas(string $ruta): array
    {
        try {
            $reader = IOFactory::createReaderForFile($ruta);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($ruta);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo leer el archivo Excel: '.$e->getMessage(), 0, $e);
        }

        try {
            return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }
}
