<?php

namespace App\Jobs;

use Alejo\Notificaciones\Facades\NotificacionesFacade as Notificaciones;
use App\Services\ExcelImportacionTracker;
use App\Services\ExcelUsuariosService;
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
 * Procesa en segundo plano el archivo de usuarios previamente validado.
 *
 * Si todo sale bien envía un correo al usuario, crea una notificación global
 * y deja el detalle en el log; si falla, el administrador recibe el detalle
 * completo y el usuario un aviso genérico.
 */
class ImportarUsuariosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Sin reintentos: evita correos y notificaciones duplicadas. */
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
        ExcelUsuariosService $excel,
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

            $notificador->exito($this->emailUsuario, 'usuarios', $resultado);
            $this->notificarFinalizacion();

            Log::info('Importación de usuarios completada', [
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
                'usuarios',
                $e,
            );

            Log::error('Falló la importación de usuarios: ' . $e->getMessage(), [
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
     * La notificación no debe tumbar la importación si el paquete de avisos
     * no está disponible.
     */
    private function notificarFinalizacion(): void
    {
        try {
            Notificaciones::crear(
                'La lectura de los usuarios en el archivo de excel ha finalizado',
                'alto'
            );
        } catch (Throwable $e) {
            Log::warning('No se pudo crear la notificación de la importación de usuarios: ' . $e->getMessage());
        }
    }

    /**
     * Cierra la métrica si el worker marca el job como fallido.
     */
    public function failed(?Throwable $e): void
    {
        app(ExcelImportacionTracker::class)->fallar(
            $this->importacionId,
            $e?->getMessage() ?? 'La importación de usuarios no pudo completarse.',
        );
    }
}
