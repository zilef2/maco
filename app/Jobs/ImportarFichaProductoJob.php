<?php

namespace App\Jobs;

use App\Services\ExcelFichasProductoService;
use App\Services\ExcelImportacionTracker;
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
 * Escribe en la BD las fichas de un archivo ya validado en la subida.
 *
 * La validación fuerte (códigos inexistentes, consumos inválidos, OPs que
 * bloquean la ficha) corre en la petición, antes de encolar este job, para que
 * el usuario se entere ahí mismo. Aquí se repiten el escaneo y la lectura por
 * si el archivo cambió de estado o alguien creó una OP mientras esperaba la cola.
 */
class ImportarFichaProductoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Sin reintentos: evita correos duplicados y fichas escritas dos veces. */
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
        ExcelFichasProductoService $excel,
        ImportacionExcelNotificador $notificador,
        ExcelImportacionTracker $tracker,
    ): void {
        $ruta = Storage::disk('local')->path($this->rutaRelativa);

        try {
            $problemas = $excel->escanearSeguridad($ruta);
            if (!empty($problemas)) {
                throw new RuntimeException(implode(' | ', $problemas));
            }

            $resultado = $excel->importar($ruta, $this->usuarioId);

            $tracker->completar($this->importacionId, $resultado, $resultado['filas']);

            $notificador->exito($this->emailUsuario, 'fichas de producto', $resultado);

            Log::info('Importación de fichas de producto completada', [
                'importacion_id' => $this->importacionId,
                'archivo' => $this->nombreArchivo,
                'usuario_id' => $this->usuarioId,
                'desglose' => $resultado,
            ]);
        } catch (Throwable $e) {
            $tracker->fallar($this->importacionId, $e->getMessage());

            $notificador->fallo(
                $this->emailUsuario,
                $this->nombreUsuario,
                $this->nombreArchivo,
                'fichas de producto',
                $e,
            );

            Log::error('Falló la importación de fichas de producto: ' . $e->getMessage(), [
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

    public function failed(?Throwable $e): void
    {
        app(ExcelImportacionTracker::class)->fallar(
            $this->importacionId,
            $e?->getMessage() ?? 'La importación de fichas de producto no pudo completarse.',
        );
    }
}
