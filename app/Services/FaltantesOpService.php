<?php

namespace App\Services;

use App\Models\KitProceso;
use App\Models\Material;
use App\Models\OrdenProduccion;
use Illuminate\Database\Eloquent\Collection;

/**
 * Carencias de material frente a las órdenes de producción abiertas: compara
 * lo que exige el kit de cada OP contra la existencia actual. Las OP desde
 * «En taller» quedan fuera porque su stock ya fue descontado.
 */
class FaltantesOpService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        $estadosSurtidos = ['En taller', 'Recibiendo', 'Terminada', 'Cerrada'];

        $ordenes = OrdenProduccion::with(['estadoOp:id,nombre'])
            ->get()
            ->reject(fn (OrdenProduccion $op) => in_array($op->estadoOp?->nombre, $estadosSurtidos, true))
            ->mapWithKeys(fn (OrdenProduccion $op) => [$op->id => $op->numero_op ?: ('OP #' . $op->id)])
            ->all();

        if (empty($ordenes)) {
            return [];
        }

        $requerido = KitProceso::query()
            ->whereIn('orden_produccion_id', array_keys($ordenes))
            ->where('cantidad_requerida', '>', 0)
            ->get(['material_id', 'orden_produccion_id', 'unidad', 'cantidad_requerida'])
            ->groupBy('material_id')
            ->map(function (Collection $filas) use ($ordenes) {
                return [
                    'unidad' => $filas->first()->unidad,
                    'total' => round((float) $filas->sum('cantidad_requerida'), 4),
                    'ordenes' => $filas->groupBy('orden_produccion_id')
                        ->map(function (Collection $porOp) use ($ordenes) {
                            $opId = (int) $porOp->first()->orden_produccion_id;
                            return [
                                'numero_op' => $ordenes[$opId] ?? ('OP #' . $opId),
                                'requerido' => round((float) $porOp->sum('cantidad_requerida'), 4),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            });

        return Material::query()
            ->whereIn('id', $requerido->keys())
            ->get(['id', 'codigo_material', 'nombre_base', 'existencia_actual', 'unidad_inventario'])
            ->map(function (Material $material) use ($requerido) {
                $dato = $requerido[$material->id];
                $existencia = round((float) $material->existencia_actual, 4);
                $faltante = round(max(0, $dato['total'] - $existencia), 4);

                if ($faltante <= 0) {
                    return null;
                }

                return [
                    'material_id' => $material->id,
                    'codigo' => $material->codigo_material,
                    'nombre' => $material->nombre_base,
                    'unidad' => $material->unidad_inventario ?: $dato['unidad'],
                    'requerido' => $dato['total'],
                    'existencia' => $existencia,
                    'faltante' => $faltante,
                    'ordenes' => $dato['ordenes'],
                ];
            })
            ->filter()
            ->sortByDesc('faltante')
            ->values()
            ->all();
    }
}
