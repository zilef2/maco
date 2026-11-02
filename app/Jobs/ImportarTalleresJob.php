<?php

namespace App\Jobs;

use App\Services\ExcelImportacionTracker;
use App\Services\ExcelTalleresService;
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
 * Procesa en segundo plano el archivo de talleres previamente validado.
 *
 * Si todo sale bien envía un correo al usuario; si falla, el administrador
 * recibe el detalle completo y el usuario un aviso genérico.
 */
class ImportarTalleresJob implements ShouldQueue
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
        public ?int $importacionId = null,
    ) {
    }

    public function handle(
        ExcelTalleresService $excel,
        ImportacionExcelNotificador $notificador,
        ExcelImportacionTracker $tracker,
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

            $tracker->completar($this->importacionId, $resultado, count($registros));

            $notificador->exito($this->emailUsuario, 'talleres', $resultado);

            Log::info('Importación de talleres completada', [
                'importacion_id' => $this->importacionId,
                'archivo' => $this->nombreArchivo,
                'usuario_id' => $this->usuarioId,
                'creados' => $resultado['creados'],
                'actualizados' => $resultado['actualizados'],
            ]);
        } catch (Throwable $e) {
            $tracker->fallar($this->importacionId, $e->getMessage());

            $notificador->fallo(
                $this->emailUsuario,
                $this->nombreUsuario,
                $this->nombreArchivo,
                'talleres',
                $e,
            );

            Log::error('Falló la importación de talleres: ' . $e->getMessage(), [
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

    /**
     * Cierra la métrica si el worker marca el job como fallido.
     */
    public function failed(?Throwable $e): void
    {
        app(ExcelImportacionTracker::class)->fallar(
            $this->importacionId,
            $e?->getMessage() ?? 'La importación de talleres no pudo completarse.',
        );
    }
}
