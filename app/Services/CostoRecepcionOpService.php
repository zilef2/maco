<?php

namespace App\Services;

use App\Models\OrdenProduccion;

/**
 * Costo de la recepción de calidad de una OP.
 *
 * El costo se arma producto por producto: una unidad aprobada ya consume todos
 * los materiales de su ficha, así que vale su costo unitario; una unidad no
 * recuperable (pérdida total) vale igual que un aprobado; una unidad en
 * reparación todavía no se suma, porque puede regresar convertida en aprobado.
 *
 * Este servicio solo aporta el costo unitario de cada producto, tomado de los
 * precios que la OP tiene guardados por material (ver CostoMaterialOpService y
 * el parámetro costos_promedio). La suma por columnas se hace con las cantidades
 * que se van digitando en el formulario, en
 * resources/js/composables/costoRecepcionOp.ts, que aplica la misma regla.
 */
class CostoRecepcionOpService
{
    public function __construct(protected CostoMaterialOpService $costosMateriales) {}

    /**
     * Costo de materiales de una unidad de cada producto de la OP.
     *
     * @return array<int, float> producto_id => costo unitario
     */
    public function unitarios(OrdenProduccion $op): array
    {
        $precios = $this->preciosPorMaterial($op);

        $unitarios = [];
        foreach ($this->costosMateriales->productos($op) as $producto) {
            // La ficha vigente del producto define cuánto material entra en una unidad.
            $unitarios[(int) $producto->id] = $this->costoUnitarioFicha(
                $producto->fichaTecnica->first(),
                $precios
            );
        }

        return $unitarios;
    }

    /**
     * Costo de materiales de una unidad de cada material procesado que produce
     * la OP. Se usa en la recepción/cierre igual que el de un producto.
     *
     * @return array<int, float> material_id => costo unitario
     */
    public function unitariosMateriales(OrdenProduccion $op): array
    {
        $precios = $this->preciosPorMaterial($op);

        $unitarios = [];
        foreach ($this->costosMateriales->materialesObjetivo($op) as $material) {
            $unitarios[(int) $material->id] = $this->costoUnitarioFicha(
                $material->fichaTecnica->first(),
                $precios
            );
        }

        return $unitarios;
    }

    /**
     * @param  array<int, float>  $precios
     */
    private function costoUnitarioFicha(mixed $ficha, array $precios): float
    {
        $costo = 0.0;

        foreach ($ficha?->materiales ?? collect() as $fila) {
            $materialId = (int) $fila->material_componente_id;
            if ($materialId > 0) {
                $costo += (float) $fila->consumo_estandar * ($precios[$materialId] ?? 0.0);
            }
        }

        return round($costo, 2);
    }

    /**
     * Valores del cierre calculados desde la recepción, sin pedirlos al usuario:
     * las pérdidas son el costo de los pares no recuperables y el costo real
     * final es lo que valen los pares aprobados y no recuperables recibidos
     * hasta el momento. Al completar la recepción coincide con el costo que se
     * calculó al crear la OP.
     *
     * También devuelve las cantidades del formulario de «Cambiar estado»:
     * esperada, aprobada y no recuperable, con su diferencia (esperada − aprobada).
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{perdidas_mermas: float, costo_real_final: float, dato_esperado: int, dato_real: int, dato_no_recuperable: int, diferencia: int}
     */
    public function resumenCierre(OrdenProduccion $op, array $filas): array
    {
        $unitariosProductos = $this->unitarios($op);
        $unitariosMateriales = $this->unitariosMateriales($op);
        $perdidas = 0.0;
        $costo = 0.0;
        $esperado = 0;
        $aprobado = 0;
        $noRecuperable = 0;

        foreach ($filas as $fila) {
            $materialId = (int) ($fila['material_id'] ?? $fila['material_referencia_id'] ?? 0);
            if ($materialId > 0) {
                $unitario = $unitariosMateriales[$materialId] ?? 0.0;
            } else {
                $productoId = (int) ($fila['producto_id'] ?? $fila['producto_referencia_id'] ?? 0);
                $unitario = $unitariosProductos[$productoId] ?? 0.0;
            }

            $aprobadas = (int) ($fila['cantidad_aprobada'] ?? 0);
            $noRecuperables = (int) ($fila['cantidad_no_recuperable'] ?? 0);

            $esperado += (int) ($fila['cantidad_esperada'] ?? 0);
            $aprobado += $aprobadas;
            $noRecuperable += $noRecuperables;

            $perdidas += $noRecuperables * $unitario;
            $costo += ($aprobadas + $noRecuperables) * $unitario;
        }

        return [
            'perdidas_mermas' => round($perdidas, 2),
            'costo_real_final' => round($costo, 2),
            'dato_esperado' => $esperado,
            'dato_real' => $aprobado,
            'dato_no_recuperable' => $noRecuperable,
            'diferencia' => $esperado - $aprobado,
        ];
    }

    /**
     * Precio unitario guardado en la OP; si aún no tiene snapshot, el del
     * método vigente calculado en vivo.
     *
     * @return array<int, float> material_id => precio
     */
    private function preciosPorMaterial(OrdenProduccion $op): array
    {
        $guardados = $op->relationLoaded('costosMateriales')
            ? $op->costosMateriales
            : $op->costosMateriales()->get();

        if ($guardados->isNotEmpty()) {
            return $guardados
                ->mapWithKeys(fn ($fila) => [(int) $fila->material_id => (float) $fila->precio_unitario])
                ->all();
        }

        return collect($this->costosMateriales->lineas($op))
            ->mapWithKeys(fn ($linea) => [(int) $linea['material_id'] => (float) $linea['precio_unitario']])
            ->all();
    }
}
