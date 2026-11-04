<?php

namespace App\Http\Controllers;

use App\Models\ActividadRegistro;
use App\Services\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Aviso del navegador cuando el usuario deja la pestaña.
 *
 * `navigator.sendBeacon` llega incluso mientras la pestaña se cierra, que es
 * justo el momento que faltaba: sin él, las horas entre la última petición y la
 * visita siguiente parecían trabajo lento del usuario en vez de ausencia.
 */
class SesionEstadoController extends Controller
{
    private const MOTIVOS = [
        ActividadRegistro::ACCION_CIERRE_NAVEGADOR,
        ActividadRegistro::ACCION_PESTANA_OCULTA,
        ActividadRegistro::ACCION_PESTANA_VISIBLE,
    ];

    public function store(Request $request): Response
    {
        $motivo = (string) $request->input('motivo');

        if (! in_array($motivo, self::MOTIVOS, true)) {
            return response()->noContent();
        }

        $pagina = trim((string) $request->input('ruta', ''));

        Bitacora::accion('Sesion', $motivo, $pagina === '' ? null : mb_substr($pagina, 0, 191));

        return response()->noContent();
    }
}
