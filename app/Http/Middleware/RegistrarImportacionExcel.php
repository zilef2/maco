<?php

namespace App\Http\Middleware;

use App\Models\ImportacionExcel;
use App\Services\ExcelImportacionTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Mide las subidas de Excel sin ensuciar cada controlador.
 *
 * Al entrar crea el registro de la importación y, al responder, guarda el
 * tiempo de la petición. Las entidades sincrónicas se cierran aquí; las que
 * usan cola quedan en "procesando" y el job las cierra al terminar.
 */
class RegistrarImportacionExcel
{
    public function __construct(private ExcelImportacionTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $entidad = ExcelImportacionTracker::entidadDeRuta($request->route()?->getName());

        if ($entidad === null) {
            return $next($request);
        }

        $inicio = microtime(true);
        $archivo = $request->file('archivo');
        $registro = $this->tracker->iniciar(
            $entidad,
            $archivo?->getClientOriginalName(),
            $request->user()?->id,
        );

        // Los controladores de las entidades asíncronas lo pasan al job para
        // que la importación se cierre en el mismo registro.
        if ($registro !== null) {
            $request->attributes->set('importacion_excel_id', $registro->id);
        }

        try {
            $respuesta = $next($request);
        } catch (Throwable $e) {
            $this->tracker->fallar($registro, $e->getMessage());

            throw $e;
        }

        $this->finalizar($respuesta, $registro, $inicio, $entidad);

        return $respuesta;
    }

    private function finalizar(
        Response $respuesta,
        ?ImportacionExcel $registro,
        float $inicio,
        string $entidad,
    ): void {
        try {
            $error = session('error');
            $bolsaErrores = session('errors');
            $huboError = $respuesta->getStatusCode() >= 400
                || ! empty($error)
                || ($bolsaErrores !== null && $bolsaErrores->isNotEmpty());

            $this->tracker->marcarSubida($registro, $inicio);

            if ($huboError) {
                $detalle = $error
                    ?: ($bolsaErrores?->first() ?: 'La carga del Excel no se completó.');

                $this->tracker->fallar($registro, (string) $detalle);

                return;
            }

            if (! ExcelImportacionTracker::esAsincrona($entidad)) {
                $this->tracker->completar($registro?->id);
            }
        } catch (Throwable) {
            // Las métricas nunca deben romper la respuesta al usuario.
        }
    }
}
