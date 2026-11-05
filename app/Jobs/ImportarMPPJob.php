<?php

namespace App\Jobs;

use Alejo\Notificaciones\Facades\NotificacionesFacade as Notificaciones;
use App\Services\ExcelImportacionTracker;
use App\Services\ExcelMPPService;
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
 * Procesa en segundo plano el archivo MPP (materiales, productos y
 * proveedores) previamente validado en la subida.
 *
 * Si todo sale bien envía un correo al usuario; si falla, el administrador
 * recibe el detalle completo (con la hoja que produjo el error) y el usuario un
 * aviso genérico. Como las tres hojas se importan en una sola transacción, un
 * error significa que no se guardó nada.
 */
class ImportarMPPJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Como el archivo se llama MPP, el correo debe decir qué importa. */
    private const ENTIDAD = 'MPP (materiales, productos y proveedores)';

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
        ExcelMPPService $excel,
        ImportacionExcelNotificador $notificador,
        ExcelImportacionTracker $tracker,
    ): void {
        $ruta = Storage::disk('local')->path($this->rutaRelativa);

        try {
            // Defensa en profundidad: se repiten el escaneo y la validación de
            // las hojas antes de tocar la base de datos.
            $problemas = $excel->escanearSeguridad($ruta);
            if (!empty($problemas)) {
                throw new RuntimeException(implode(' | ', $problemas));
            }

            $excel->validarLibro($ruta);

            $resultado = $excel->importar($ruta, $this->usuarioId);

            $tracker->completar($this->importacionId, $resultado, $resultado['filas']);

            $notificador->exito($this->emailUsuario, self::ENTIDAD, $resultado);
            $this->notificarFinalizacion();

            Log::info('Importación MPP completada', [
                'importacion_id' => $this->importacionId,
                'archivo' => $this->nombreArchivo,
                'usuario_id' => $this->usuarioId,
                'desglose' => $resultado['desglose'],
            ]);
        } catch (Throwable $e) {
            $tracker->fallar($this->importacionId, $e->getMessage());

            $notificador->fallo(
                $this->emailUsuario,
                $this->nombreUsuario,
                $this->nombreArchivo,
                self::ENTIDAD,
                $e,
            );

            Log::error('Falló la importación MPP: ' . $e->getMessage(), [
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
     * La notificación no debe tumbar la importación si el paquete de avisos no
     * está disponible.
     */
    private function notificarFinalizacion(): void
    {
        try {
            Notificaciones::crear(
                'La lectura del archivo MPP (materiales, productos y proveedores) en el excel ha finalizado',
                'alto'
            );
        } catch (Throwable $e) {
            Log::warning('No se pudo crear la notificación de la importación MPP: ' . $e->getMessage());
        }
    }

    /**
     * También se cierra la métrica si el job muere por timeout o el worker lo
     * marca como fallido sin pasar por el catch de handle().
     */
    public function failed(?Throwable $e): void
    {
        app(ExcelImportacionTracker::class)->fallar(
            $this->importacionId,
            $e?->getMessage() ?? 'La importación MPP no pudo completarse.',
        );
    }
}
