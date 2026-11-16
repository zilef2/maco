<?php

namespace App\Http\Controllers;

use App\Models\Merma;
use App\Models\RecepcionCalidad;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Mermas de producción: solo lectura.
 *
 * No hay alta ni edición: cada merma es el «No recuperable» que se anotó en la
 * recepción de calidad de una OP (estado «Recibiendo»), así que la página se
 * limita a totalizarlos por periodo.
 */
class MermaController extends Controller
{
    public string $FromController = 'Merma';

    /**
     * El listado completo llega una sola vez: buscar, filtrar por periodo,
     * ordenar y paginar se hace en el cliente.
     */
    public function index()
    {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' Mermas '));

        return Inertia::render($this->FromController . '/Index', [
            'mermas' => $this->mermas(),
            'produccion' => $this->produccion(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'numberPermissions' => $numberPermissions,
        ]);
    }

    private function mermas(): array
    {
        // Totales de la OP completa (todas sus tallas), para que el detalle no
        // confunda la cifra de una sola talla con lo que produjo la OP entera.
        $totalesPorOp = RecepcionCalidad::query()
            ->selectRaw('orden_produccion_id')
            ->selectRaw('SUM(cantidad_esperada) AS esperada')
            ->selectRaw('SUM(cantidad_aprobada) AS aprobada')
            ->selectRaw('SUM(cantidad_en_reparacion) AS en_reparacion')
            ->selectRaw('SUM(cantidad_no_recuperable) AS no_recuperable')
            ->groupBy('orden_produccion_id')
            ->get()
            ->keyBy('orden_produccion_id');

        return Merma::with([
            'ordenProduccion.estadoOp:id,nombre',
            'ordenProduccion.taller:id,nombre,nombre_comercial',
            'ordenProduccion.empresaExterna:id,nombre,nombre_comercial',
            'recepcionCalidad',
            'producto:id,Codigo_interno,Titulo_Shopify',
            'material:id,codigo_material,nombre_base',
            'responsable:id,name',
        ])
            ->orderByDesc('fecha_registro')
            ->get()
            ->map(function (Merma $merma) use ($totalesPorOp) {
                $recepcion = $merma->recepcionCalidad;
                $fecha = $merma->fecha_registro;
                $totalesOp = $totalesPorOp->get($merma->orden_produccion_id);

                $orden = $merma->ordenProduccion;
                $taller = $orden?->taller;
                $empresa = $orden?->empresaExterna;
                $tallerNombre = $taller ? ($taller->nombre_comercial ?: $taller->nombre) : null;
                $empresaNombre = $empresa ? ($empresa->nombre_comercial ?: $empresa->nombre) : null;

                return [
                    'id' => $merma->id,
                    'numero_op' => $orden?->numero_op ?? ('OP #' . $merma->orden_produccion_id),
                    'estado_op' => $orden?->estadoOp?->nombre,
                    'objetivo' => $merma->objetivoNombre(),
                    'objetivo_tipo' => $merma->material_referencia_id ? 'Material' : 'Producto',
                    'taller_id' => $orden?->taller_id,
                    'taller_nombre' => $tallerNombre,
                    'empresa_externa_id' => $orden?->empresa_externa_id,
                    'empresa_externa_nombre' => $empresaNombre,
                    'destino_nombre' => $empresaNombre ?: $tallerNombre,
                    'destino_tipo' => $empresa ? 'Empresa externa' : ($taller ? 'Taller' : null),
                    'talla' => $merma->talla,
                    'cantidad' => (int) $merma->cantidad,
                    'costo_unitario' => (float) $merma->costo_unitario,
                    'costo_total_perdida' => (float) $merma->costo_total_perdida,
                    'fecha_registro' => $fecha?->toIso8601String(),
                    'mes' => $fecha?->format('Y-m'),
                    'responsable' => $merma->responsable?->name,
                    'observaciones' => $merma->observaciones,
                    'esperada' => $recepcion ? (int) $recepcion->cantidad_esperada : null,
                    'aprobada' => $recepcion ? (int) $recepcion->cantidad_aprobada : null,
                    'en_reparacion' => $recepcion ? (int) $recepcion->cantidad_en_reparacion : null,
                    'esperada_op' => $totalesOp ? (int) $totalesOp->esperada : null,
                    'aprobada_op' => $totalesOp ? (int) $totalesOp->aprobada : null,
                    'en_reparacion_op' => $totalesOp ? (int) $totalesOp->en_reparacion : null,
                    'no_recuperable_op' => $totalesOp ? (int) $totalesOp->no_recuperable : null,
                ];
            })
            ->all();
    }

    /**
     * Lo recibido en calidad, talla por talla: aprobado y no recuperable.
     *
     * Sale de las recepciones y no de las mermas porque las tallas sin pérdida
     * nunca generan merma, y sin ellas no habría contra qué comparar lo perdido.
     */
    private function produccion(): array
    {
        return RecepcionCalidad::query()
            ->get(['cantidad_aprobada', 'cantidad_no_recuperable', 'fecha_recepcion', 'created_at'])
            ->map(function (RecepcionCalidad $recepcion) {
                $fecha = $recepcion->fecha_recepcion
                    ? Carbon::parse($recepcion->fecha_recepcion)
                    : $recepcion->created_at;

                return [
                    'aprobada' => (int) $recepcion->cantidad_aprobada,
                    'no_recuperable' => (int) $recepcion->cantidad_no_recuperable,
                    'fecha' => $fecha?->toIso8601String(),
                    'mes' => $fecha?->format('Y-m'),
                ];
            })
            ->all();
    }
}
