<?php

namespace App\Services;

use App\Models\Merma;
use App\Models\OrdenProduccion;

/**
 * Mermas desde la recepción de calidad.
 *
 * La única forma de que exista una merma es que en el formulario «Cambiar
 * estado», con la OP en «Recibiendo», se anoten pares en «No recuperable». Cada
 * talla con pérdida deja un registro, y si la recepción se vuelve a guardar con
 * otra cantidad, el registro se ajusta; si la talla deja de perder pares, se
 * borra.
 */
class MermaRecepcionService
{
    public function __construct(protected CostoRecepcionOpService $costosRecepcion) {}

    /**
     * Alinea las mermas de la OP con sus recepciones vigentes.
     *
     * @return int pares perdidos registrados en la OP
     */
    public function sincronizar(OrdenProduccion $op, ?int $responsableId = null): int
    {
        $unitariosProductos = $this->costosRecepcion->unitarios($op);
        $unitariosMateriales = $this->costosRecepcion->unitariosMateriales($op);

        $perdidos = 0;
        $vigentes = [];

        foreach ($op->recepcionesCalidad()->get() as $recepcion) {
            $cantidad = (int) $recepcion->cantidad_no_recuperable;
            if ($cantidad <= 0) {
                continue;
            }

            $materialId = $recepcion->material_referencia_id ? (int) $recepcion->material_referencia_id : null;
            $unitario = $materialId
                ? ($unitariosMateriales[$materialId] ?? 0.0)
                : ($unitariosProductos[(int) $recepcion->producto_referencia_id] ?? 0.0);

            Merma::updateOrCreate(
                ['recepcion_calidad_id' => $recepcion->id],
                [
                    'orden_produccion_id'     => $op->id,
                    'producto_referencia_id'  => $recepcion->producto_referencia_id,
                    'material_referencia_id'  => $recepcion->material_referencia_id,
                    'talla'                   => $recepcion->talla,
                    'cantidad'                => $cantidad,
                    'fecha_registro'          => $recepcion->fecha_recepcion ?: now(),
                    'costo_unitario'          => $unitario,
                    'costo_total_perdida'     => round($cantidad * $unitario, 2),
                    'responsable_id'          => $responsableId,
                    'observaciones'           => $recepcion->observaciones,
                ]
            );

            $vigentes[] = (int) $recepcion->id;
            $perdidos += $cantidad;
        }

        Merma::where('orden_produccion_id', $op->id)
            ->whereNotIn('recepcion_calidad_id', $vigentes ?: [0])
            ->delete();

        return $perdidos;
    }
}
