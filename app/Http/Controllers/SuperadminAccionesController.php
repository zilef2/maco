<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurgarDatosPruebaRequest;
use App\Http\Requests\PurgarMaterialesRequest;
use App\Http\Requests\ReiniciarOrdenesProduccionRequest;
use App\Services\Bitacora;
use App\Services\ExistenciasMaterialesService;
use App\Services\MaterialesGenericosService;
use App\Services\PurgarDatosPruebaService;
use App\Services\PurgarMaterialesService;
use App\Services\ReiniciarOrdenesProduccionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SuperadminAccionesController extends Controller
{
    public function __construct(
        protected PurgarDatosPruebaService $purgarService,
        protected PurgarMaterialesService $purgarMaterialesService,
        protected ExistenciasMaterialesService $existenciasService,
        protected MaterialesGenericosService $genericosService,
        protected ReiniciarOrdenesProduccionService $reiniciarOpService
    ) {}

    public function purgarDatosPrueba(PurgarDatosPruebaRequest $request): RedirectResponse
    {
        $this->purgarService->ejecutar();

        return back()->with('success', 'Registros de prueba eliminados correctamente.');
    }

    public function purgarMateriales(PurgarMaterialesRequest $request): RedirectResponse
    {
        $conteo = $this->purgarMaterialesService->ejecutar();

        Bitacora::accion(
            'Superadmin',
            'purgarMateriales',
            "{$conteo['materiales']} materiales, {$conteo['variantes']} variantes, {$conteo['precios']} precios, {$conteo['vinculos']} vínculos"
        );

        return back()->with('success', "Se borraron {$conteo['materiales']} materiales y todo lo que dependía de ellos.");
    }

    /** Poner en cero las existencias de todos los materiales. */
    public function ceroExistencias(Request $request): RedirectResponse
    {
        $afectados = $this->existenciasService->ponerEnCero();

        Bitacora::accion('Superadmin', 'ceroExistencias', "{$afectados} materiales con existencia 0");

        return back()->with('success', "Se pusieron en cero las existencias de {$afectados} materiales.");
    }

    /** Da existencia y precio al azar a los materiales que nunca tuvieron. */
    public function rellenarMateriales(Request $request): RedirectResponse
    {
        $conteo = $this->existenciasService->rellenarAleatorio();

        Bitacora::accion(
            'Superadmin',
            'rellenarMateriales',
            "{$conteo['existencias']} existencias y {$conteo['precios']} precios al azar"
        );

        return back()->with('success', "Existencia al azar en {$conteo['existencias']} materiales y precio al azar en {$conteo['precios']}.");
    }

    /** Crea los genéricos faltantes con el código CAT-BAS-0 y refresca su precio promedio. */
    public function materialesGenericos(Request $request): RedirectResponse
    {
        $conteo = $this->genericosService->sincronizar();

        Bitacora::accion(
            'Superadmin',
            'materialesGenericos',
            "{$conteo['creados']} genéricos creados, {$conteo['precios']} precios promediados, {$conteo['renumerados']} renumerados a CAT-BAS-0"
        );

        $detalle = $conteo['precios'] > 0
            ? " y precio promedio en {$conteo['precios']}"
            : '';

        $renumerados = $conteo['renumerados'] > 0
            ? ". {$conteo['renumerados']} genéricos pasaron al código CAT-BAS-0"
            : '';

        return back()->with('success', "Se crearon {$conteo['creados']} materiales genéricos{$detalle}{$renumerados}.");
    }

    public function reiniciarOrdenesProduccion(ReiniciarOrdenesProduccionRequest $request): RedirectResponse
    {
        $this->reiniciarOpService->ejecutar((int) $request->validated('estado_op_id'));

        Bitacora::accion('Superadmin', 'reiniciarOrdenesProduccion', 'Se borraron todas las OP y se generaron 2 nuevas con producto, materiales y procesos.');

        return back()->with('success', 'Se borraron todas las OP y se crearon 2 nuevas con producto, materiales y procesos en el estado seleccionado.');
    }
}
