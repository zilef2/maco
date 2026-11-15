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

        $mensaje = "La importación terminó correctamente: {$resultado['creados']} registro(s) de "
            . "{$entidadPlural} creado(s) y {$resultado['actualizados']} actualizado(s). "
            . 'En breve podrás ver la información actualizada en el aplicativo.';

        // Avisos que no cancelaron la carga (consumos que llegaron vacíos…).
        if (!empty($resultado['avisos'])) {
            $mensaje .= ' ' . implode(' ', (array) $resultado['avisos']);
        }

        try {
            Mail::to($emailUsuario)->send(new ImportacionExcelMailable(
                "Importación de {$entidadPlural} completada",
                $mensaje,
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

        $estructurado = $this->estructurarError($error->getMessage());

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
                errorTitulo: $estructurado['titulo'],
                errorDetalle: $estructurado['detalle'],
                errorLista: $estructurado['lista'],
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

    /**
     * Divide el mensaje de la excepción en título, detalle y lista para que el
     * correo lo muestre de forma estructurada.
     *
     * El texto previo a los dos puntos se toma como título; dentro del resto se
     * detecta una enumeración (tres o más valores separados por comas) que pasa
     * a ser la lista; el texto sobrante queda como detalle.
     */
    private function estructurarError(string $mensaje): array
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '') {
            return ['titulo' => null, 'detalle' => null, 'lista' => []];
        }

        $titulo = null;
        $detalle = $mensaje;

        $pos = mb_strpos($mensaje, ':');
        if ($pos !== false) {
            $candidato = trim(mb_substr($mensaje, 0, $pos));
            if ($candidato !== '' && mb_strlen($candidato) <= 120) {
                $titulo = $candidato;
                $detalle = trim(mb_substr($mensaje, $pos + 1));
            }
        }

        $lista = [];
        if (preg_match('/([^,\s.;:()]+(?:\s*,\s*[^,\s.;:()]+){2,})/u', $detalle, $coincidencia)) {
            $items = array_values(array_filter(
                array_map('trim', explode(',', $coincidencia[1])),
                fn (string $valor): bool => $valor !== '',
            ));

            if (count($items) >= 3) {
                $lista = $items;
                $detalle = str_replace($coincidencia[1], '', $detalle);
                $detalle = preg_replace('/\s*[,;:]\s*(?=[.,;:]|$)/u', '', $detalle) ?? $detalle;
                $detalle = preg_replace('/\s*:\s*(?=[.,;])/u', '', $detalle) ?? $detalle;
                $detalle = preg_replace('/\s{2,}/u', ' ', $detalle) ?? $detalle;
                $detalle = trim($detalle);
            }
        }

        return [
            'titulo' => $titulo,
            'detalle' => $detalle !== '' ? $detalle : null,
            'lista' => $lista,
        ];
    }
}
