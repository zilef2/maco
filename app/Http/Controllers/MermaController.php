<?php

namespace App\Http\Controllers;

use App\Models\Merma;
use App\helpers\Myhelp;
use App\helpers\MyModels;
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
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'numberPermissions' => $numberPermissions,
        ]);
    }

    private function mermas(): array
    {
        return Merma::with([
            'ordenProduccion.estadoOp:id,nombre',
            'recepcionCalidad',
            'producto:id,Codigo_interno,Titulo_Shopify',
            'material:id,codigo_material,nombre_base',
            'responsable:id,name',
        ])
            ->orderByDesc('fecha_registro')
            ->get()
            ->map(function (Merma $merma) {
                $recepcion = $merma->recepcionCalidad;
                $fecha = $merma->fecha_registro;

                return [
                    'id' => $merma->id,
                    'numero_op' => $merma->ordenProduccion?->numero_op ?? ('OP #' . $merma->orden_produccion_id),
                    'estado_op' => $merma->ordenProduccion?->estadoOp?->nombre,
                    'objetivo' => $merma->objetivoNombre(),
                    'objetivo_tipo' => $merma->material_referencia_id ? 'Material' : 'Producto',
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
                ];
            })
            ->all();
    }
}
