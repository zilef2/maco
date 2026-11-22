<?php

namespace App\Traits;

use App\Services\ExcelDesdeDriveService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Las subidas del Centro de Importación aceptan el .xlsx adjunto o el enlace de
 * Google Drive donde vive el archivo. El archivo manda cuando se ponen los dos:
 * el enlace es un atajo para no volver a adjuntar lo mismo.
 */
trait ExcelDesdeDriveTrait
{
    /**
     * Reglas para las dos formas de traer el libro. La descarga del enlace corre
     * en la petición, así que su tamaño y su firma se validan en el servicio.
     *
     * @return array<string, array<int, string>>
     */
    public static function reglasDeSubidaExcel(): array
    {
        return [
            'archivo' => ['required_without:enlace', 'file', 'max:10240', 'mimes:xlsx'],
            'enlace'  => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajesDeSubidaExcel(): array
    {
        return [
            'archivo.required_without' => 'Selecciona un archivo .xlsx o pega su enlace de Google Drive.',
            'archivo.file' => 'El archivo no es válido.',
            'archivo.max' => 'El archivo supera el tamaño máximo de 10 MB.',
            'archivo.mimes' => 'Solo se permiten archivos .xlsx.',
            'enlace.max' => 'El enlace de Google Drive es demasiado largo.',
        ];
    }

    /**
     * El archivo que se importa: el adjunto o el que está publicado en el enlace.
     */
    protected function excelDeLaSubida(Request $request): UploadedFile
    {
        $archivo = $request->file('archivo');
        if ($archivo !== null) {
            return $archivo;
        }

        $enlace = trim((string) $request->input('enlace'));
        if ($enlace === '') {
            throw ValidationException::withMessages([
                'archivo' => 'Selecciona un archivo .xlsx o pega su enlace de Google Drive.',
            ]);
        }

        try {
            return app(ExcelDesdeDriveService::class)->descargar($enlace);
        } catch (RuntimeException $e) {
            // Vuelve por la bolsa de errores: la tarjeta muestra el motivo en
            // su consola y la bitácora de importaciones lo registra como fallo.
            throw ValidationException::withMessages(['enlace' => $e->getMessage()]);
        }
    }
}
