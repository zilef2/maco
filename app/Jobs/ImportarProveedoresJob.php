<?php

namespace App\Jobs;

use App\Services\ExcelProveedoresService;
use App\Services\ImportacionExcelNotificador;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Procesa en segundo plano el archivo de proveedores previamente validado.
 *
 * Si todo sale bien envía un correo al usuario; si falla, el administrador
 * recibe el detalle completo y el usuario un aviso genérico.
 */
class ImportarProveedoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Sin reintentos: evita correos duplicados. */
    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public string $rutaRelativa,
        public string $nombreArchivo,
        public ?string $emailUsuario,
        public ?string $nombreUsuario,
        public ?int $usuarioId = null,
    ) {
    }

    public function handle(
        ExcelProveedoresService $excel,
        ImportacionExcelNotificador $notificador,
    ): void {
        $ruta = Storage::disk('local')->path($this->rutaRelativa);

        try {
            // Defensa en profundidad: se repiten el escaneo y la validación
            // antes de tocar la base de datos.
            $problemas = $excel->escanearSeguridad($ruta);
            if (!empty($problemas)) {
                throw new RuntimeException(implode(' | ', $problemas));
            }

            $registros = $excel->leerRegistros($ruta);
            $resultado = $excel->importar($registros);

            $notificador->exito($this->emailUsuario, 'proveedores', $resultado);

            Log::info('Importación de proveedores completada', [
                'archivo' => $this->nombreArchivo,
                'usuario_id' => $this->usuarioId,
                'creados' => $resultado['creados'],
                'actualizados' => $resultado['actualizados'],
            ]);
        } catch (Throwable $e) {
            $notificador->fallo(
                $this->emailUsuario,
                $this->nombreUsuario,
                $this->nombreArchivo,
                'proveedores',
                $e,
            );

            Log::error('Falló la importación de proveedores: ' . $e->getMessage(), [
                'archivo' => $this->nombreArchivo,
                'usuario_id' => $this->usuarioId,
            ]);

            throw $e;
        } finally {
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }
    }
}
