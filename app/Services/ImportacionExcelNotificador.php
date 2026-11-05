<?php

namespace App\Services;

use App\Mail\ImportacionExcelMailable;
use App\Mail\ImportacionFalloMailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ImportacionExcelNotificador
{
    private const CORREO_ADMIN = 'ajelof2@gmail.com';

    public function __construct(
        private readonly ?GeminiErrorAnalyzerService $geminiService = null,
    ) {
    }

    public function exito(?string $emailUsuario, string $entidadPlural, array $resultado): void
    {
        if (empty($emailUsuario)) {
            return;
        }

        try {
            Mail::to($emailUsuario)->send(new ImportacionExcelMailable(
                "Importación de {$entidadPlural} completada",
                "La importación terminó correctamente: {$resultado['creados']} registro(s) de "
                . "{$entidadPlural} creado(s) y {$resultado['actualizados']} actualizado(s). "
                . 'En breve podrás ver la información actualizada en el aplicativo.',
            ));
        } catch (Throwable $e) {
            Log::error("No se pudo enviar el correo de éxito de la importación de {$entidadPlural}: " . $e->getMessage());
        }
    }

    public function fallo(
        ?string $emailUsuario,
        ?string $nombreUsuario,
        string $nombreArchivo,
        string $entidadPlural,
        Throwable $error,
    ): void {
        $resumenIa = null;
        try {
            $servicioIa = $this->geminiService ?? app(GeminiErrorAnalyzerService::class);
            $resumenIa = $servicioIa->analizarExcepcion($error->getMessage(), $error->getTraceAsString());
        } catch (Throwable $e) {
            Log::warning('No se pudo generar el diagnóstico con Gemini para el correo de error: ' . $e->getMessage());
        }

        try {
            Mail::to(self::CORREO_ADMIN)->send(new ImportacionFalloMailable(
                titulo: "Falló la importación de {$entidadPlural}",
                mensaje: 'La importación del archivo no se pudo completar. Este es el detalle técnico estructurado:',
                detalles: [
                    'Entidad' => $entidadPlural,
                    'Archivo' => $nombreArchivo,
                    'Usuario' => $nombreUsuario ?? 'No identificado',
                    'Correo del usuario' => $emailUsuario ?? 'No identificado',
                    'Fecha' => now()->format('Y-m-d H:i:s'),
                    'Origen' => $error->getFile() . ':' . $error->getLine(),
                ],
                errorMensaje: $error->getMessage(),
                resumenIa: $resumenIa,
                traza: $error->getTraceAsString(),
            ));
        } catch (Throwable $e) {
            Log::error("No se pudo enviar el correo de error al administrador ({$entidadPlural}): " . $e->getMessage());
        }

        if (empty($emailUsuario)) {
            return;
        }

        try {
            Mail::to($emailUsuario)->send(new ImportacionExcelMailable(
                "La importación de {$entidadPlural} no se pudo completar",
                'La operación falló. Nuestro equipo ya fue notificado y el problema será reparado '
                . 'a la brevedad. Por favor, espera antes de volver a intentarlo.',
            ));
        } catch (Throwable $e) {
            Log::error("No se pudo enviar el correo de error al usuario ({$entidadPlural}): " . $e->getMessage());
        }
    }
}
