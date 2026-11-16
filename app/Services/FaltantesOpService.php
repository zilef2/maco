<?php

namespace App\Services;

use App\Models\KitProceso;
use App\Models\Material;
use App\Models\MaterialPrice;
use App\Models\OrdenProduccion;
use App\Models\Proveedor;
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
        return $this->porFaltante($this->carencias());
    }

    /**
     * Orden de compra: las carencias con el precio vigente y el importe de
     * cubrir cada una, que es lo que se necesita para saber cuánto gastar.
     *
     * @return array{filas: list<array<string, mixed>>, materiales: int, ordenes: int, total_estimado: float, sin_precio: int}
     */
    public function ordenCompra(): array
    {
        $filas = $this->porFaltante($this->carencias());

        if (empty($filas)) {
            return [
                'filas' => [],
                'materiales' => 0,
                'ordenes' => 0,
                'total_estimado' => 0.0,
                'sin_precio' => 0,
            ];
        }

        $ids = array_column($filas, 'material_id');

        // El precio vigente es la compra más reciente del material.
        $precios = MaterialPrice::query()
            ->whereIn('material_id', $ids)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['material_id', 'precio', 'fecha'])
            ->unique('material_id')
            ->keyBy('material_id');

        $proveedorIdPorMaterial = Material::query()
            ->whereIn('id', $ids)
            ->pluck('proveedor_habitual', 'id')
            ->filter()
            ->all();

        $nombres = Proveedor::query()
            ->whereIn('id', array_values($proveedorIdPorMaterial))
            ->pluck('nombre', 'id');

        $filas = array_map(function (array $fila) use ($precios, $nombres, $proveedorIdPorMaterial) {
            $precio = $precios[$fila['material_id']] ?? null;
            $valor = $precio === null ? null : round((float) $precio->precio, 2);

            $fila['precio'] = $valor;
            $fila['precio_fecha'] = $precio === null ? null : substr((string) $precio->fecha, 0, 10);
            $proveedorId = $proveedorIdPorMaterial[$fila['material_id']] ?? null;
            $fila['proveedor'] = $proveedorId === null ? null : ($nombres[$proveedorId] ?? null);
            $fila['importe'] = $valor === null ? null : round($fila['faltante'] * $valor, 2);

            return $fila;
        }, $filas);

        $ordenes = [];
        foreach ($filas as $fila) {
            foreach ($fila['ordenes'] as $op) {
                $ordenes[$op['numero_op']] = true;
            }
        }

        return [
            'filas' => $filas,
            'materiales' => count($filas),
            'ordenes' => count($ordenes),
            'total_estimado' => round(array_sum(array_filter(array_column($filas, 'importe'))), 2),
            'sin_precio' => count(array_filter($filas, fn (array $fila) => $fila['precio'] === null)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function porFaltante(array $filas): array
    {
        usort($filas, fn (array $a, array $b) => $b['faltante'] <=> $a['faltante']);

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function carencias(): array
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
            ->get(['id', 'codigo_material', 'codigo_categoria', 'categoria', 'nombre_base', 'existencia_actual', 'unidad_inventario'])
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
                    'categoria' => $material->categoria,
                    'codigo_categoria' => $material->codigo_categoria,
                    'unidad' => $material->unidad_inventario ?: $dato['unidad'],
                    'requerido' => $dato['total'],
                    'existencia' => $existencia,
                    'faltante' => $faltante,
                    'ordenes' => $dato['ordenes'],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
