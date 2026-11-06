<?php

namespace App\Services;

use App\Models\EstadoOperacion;
use App\Models\Material;
use App\Models\OrdenProduccion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * «Esperando materiales» es una cola FIFO: la OP que entró primero sale primero.
 * El kit se compara siempre contra la existencia actual del material, nunca
 * contra la foto que quedó en kit_procesos.cantidad_disponible.
 */
class ColaMaterialesOpService
{
    public const ESTADO_ESPERA = 'Esperando materiales';
    public const ESTADO_LISTA = 'Lista para taller';

    /**
     * Materiales del kit cuya necesidad supera la existencia actual. Vacío
     * significa que la OP puede pasar a «Lista para taller».
     *
     * @return list<array{material_id: int, nombre: string, unidad: string, requerida: float, disponible: float, faltante: float}>
     */
    public function faltantes(OrdenProduccion $op): array
    {
        return $op->kitProcesos()->with('material')->get()
            ->map(function ($kit) {
                $requerida = round((float) ($kit->cantidad_requerida ?? 0), 4);
                $disponible = round((float) ($kit->material?->existencia_actual ?? 0), 4);
                $faltante = round(max(0, $requerida - $disponible), 4);
                if ($faltante <= 0) {
                    return null;
                }

                $nombre = trim(
                    ($kit->material?->codigo_material ? $kit->material->codigo_material . ' — ' : '')
                    . ($kit->material?->nombre_base ?? '')
                );

                return [
                    'material_id' => (int) $kit->material_id,
                    'nombre'      => $nombre !== '' ? $nombre : ('Material #' . $kit->material_id),
                    'unidad'      => $kit->unidad ?: ($kit->material?->unidad_inventario ?: 'u'),
                    'requerida'   => $requerida,
                    'disponible'  => $disponible,
                    'faltante'    => $faltante,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function kitCompleto(OrdenProduccion $op): bool
    {
        return empty($this->faltantes($op));
    }

    /**
     * La cola en orden de llegada. Las OP sin fecha registrada (anteriores a la
     * cola) van al final, desempatadas por id.
     *
     * @return Collection<int, OrdenProduccion>
     */
    public function cola(bool $bloquear = false): Collection
    {
        $estadoId = EstadoOperacion::where('nombre', self::ESTADO_ESPERA)->value('id');
        if (!$estadoId) {
            return new Collection();
        }

        $query = OrdenProduccion::query()
            ->where('estado_op_id', $estadoId)
            ->orderByRaw('esperando_materiales_desde IS NULL')
            ->orderBy('esperando_materiales_desde')
            ->orderBy('id');

        if ($bloquear) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Turno de cada OP en la cola (1 = la próxima en salir), para pintarlo en
     * el listado sin consultar la cola una vez por fila.
     *
     * @return array<int, int>
     */
    public function posiciones(): array
    {
        return $this->cola()->values()
            ->mapWithKeys(fn (OrdenProduccion $op, $indice) => [(int) $op->id => $indice + 1])
            ->all();
    }

    /**
     * Recorre la cola en orden y pasa a «Lista para taller» cada OP cuyo kit
     * cabe en el stock que queda libre. Lo que consume una OP se reserva
     * nocionalmente, así que dos OP no quedan «listas» peleando por el mismo
     * material: el descuento real ocurre al entrar a «En taller».
     *
     * Devuelve las OP promovidas para poder contarlas en el mensaje de éxito.
     *
     * @return list<array{id: int, numero_op: string}>
     */
    public function promoverCola(): array
    {
        $listaId = EstadoOperacion::where('nombre', self::ESTADO_LISTA)->value('id');
        if (!$listaId) {
            return [];
        }

        $cola = $this->cola(bloquear: true);
        if ($cola->isEmpty()) {
            return [];
        }

        $kits = $cola->mapWithKeys(fn (OrdenProduccion $op) => [
            $op->id => $op->kitProcesos()->get(['material_id', 'cantidad_requerida']),
        ]);

        $materialIds = $kits->flatten()->pluck('material_id')->unique()->filter()->values()->all();
        $stock = Material::whereIn('id', $materialIds)
            ->lockForUpdate()
            ->pluck('existencia_actual', 'id')
            ->map(fn ($existencia) => round((float) $existencia, 4))
            ->all();

        $promovidas = [];
        foreach ($cola as $op) {
            $requeridos = $kits[$op->id];

            $alcanza = true;
            foreach ($requeridos as $kit) {
                $materialId = (int) $kit->material_id;
                $requerida = round((float) ($kit->cantidad_requerida ?? 0), 4);
                if ($requerida <= 0) {
                    continue;
                }
                if (!array_key_exists($materialId, $stock) || $stock[$materialId] < $requerida) {
                    $alcanza = false;
                    break;
                }
            }

            if (!$alcanza) {
                continue;
            }

            foreach ($requeridos as $kit) {
                $materialId = (int) $kit->material_id;
                $requerida = round((float) ($kit->cantidad_requerida ?? 0), 4);
                if ($requerida > 0 && isset($stock[$materialId])) {
                    $stock[$materialId] = round($stock[$materialId] - $requerida, 4);
                }
            }

            $op->forceFill([
                'estado_op_id' => $listaId,
                'esperando_materiales_desde' => null,
            ])->save();

            $promovidas[] = [
                'id' => (int) $op->id,
                'numero_op' => $op->numero_op ?: ('OP #' . $op->id),
            ];
        }

        if (!empty($promovidas)) {
            Cache::increment('op_index_version');
            Cache::forget('op_selects');
        }

        return $promovidas;
    }
}