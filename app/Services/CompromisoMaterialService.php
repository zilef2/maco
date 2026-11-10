<?php

namespace App\Services;

use App\Models\Compromisomaterial;
use App\Models\OrdenProduccion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inventario proyectado: existencia física + producción pendiente − compromisos.
 *
 * Una OP consumidora (Preparación, Esperando materiales o Lista para taller)
 * reserva primero la existencia física y solo lo que falta se compromete
 * contra la producción pendiente de otras OP. La prioridad es FIFO por id de
 * OP: las órdenes más antiguas se abastecen primero.
 */
class CompromisoMaterialService
{
    /** Estados en los que la OP todavía puede reservar material. */
    public const ESTADOS_CONSUMIDOR = ['Preparación', 'Esperando materiales', 'Lista para taller'];

    /** Estados cuya producción aún no ha entrado completa a inventario. */
    public const ESTADOS_PRODUCTOR = ['Preparación', 'Esperando materiales', 'Lista para taller', 'En taller', 'Recibiendo'];

    /** OPs que todavía no afectan la proyección de producción. */
    private const ESTADOS_SIN_PRODUCCION = ['Borrador', 'Cerrada'];

    /** OPs que ya consumieron (o están consumiendo) su kit. */
    private const ESTADOS_CON_KIT = ['Preparación', 'Esperando materiales', 'Lista para taller', 'En taller', 'Recibiendo'];

    private const TOLERANCIA = 0.0001;

    // ─────────────────────────────────────────────────────────
    //  SINCRONIZACIÓN
    // ─────────────────────────────────────────────────────────

    /**
     * Recalcula los compromisos de todos los materiales que toca la OP: los
     * que consume (kit) y los que produce (pivote de materiales).
     */
    public function sincronizar(OrdenProduccion $op): void
    {
        $this->sincronizarMateriales($this->materialesDeOrden($op));
    }

    public function materialesDeOrden(OrdenProduccion $op): array
    {
        return $this->normalizarIds(
            DB::table('kit_procesos')->where('orden_produccion_id', $op->id)->pluck('material_id')
                ->merge(DB::table('orden_produccion_material')->where('orden_produccion_id', $op->id)->pluck('material_id'))
                ->all()
        );
    }

    /**
     * Recalcula la proyección de los materiales indicados: primero ajusta lo
     * ya recibido de cada OP productora y después reasigna los compromisos de
     * las OP consumidoras en orden de llegada.
     *
     * @param  array<int, int>  $materialIds
     */
    public function sincronizarMateriales(array $materialIds): void
    {
        $ids = $this->normalizarIds($materialIds);
        if (empty($ids)) {
            return;
        }

        $contexto = $this->contextoMateriales($ids);
        // Existencia física ya reclamada por las OP consumidoras anteriores.
        $contexto['fisico_tomado'] = [];

        // Lo recibido se reparte FIFO entre los consumidores que reservaron.
        // Se incluyen también las OP que ya recibieron todo (aunque su
        // pendiente sea cero) para que su parte cumplida quede marcada.
        $productores = array_values(array_unique(array_merge(
            $contexto['produccion_ops'],
            $this->productoresConRecepcion($ids)
        )));

        foreach ($productores as $productorId) {
            $productor = OrdenProduccion::find($productorId);
            if ($productor) {
                $this->sincronizarCumplimiento($productor, $ids);
            }
        }

        foreach ($contexto['consumidores'] as $consumidor) {
            $this->sincronizarConsumidor($consumidor, $ids, $contexto);
        }

        $this->liberarConsumidoresInactivos($ids);
    }

    /**
     * Ajusta los compromisos de una OP consumidora. Si dejó de consumir (pasó
     * a taller o se cerró) sus reservas se liberan; si sigue en preparación se
     * vuelven a repartir contra la producción pendiente disponible.
     *
     * @param  array<int, int>|null  $materialIds
     */
    public function sincronizarConsumidor(OrdenProduccion $op, ?array $materialIds = null, ?array &$contexto = null): void
    {
        $op->load('estadoOp');

        $ids = $materialIds === null ? $this->materialesDeOrden($op) : $this->normalizarIds($materialIds);
        if (empty($ids)) {
            $this->liberarConsumidor($op, $this->estadoLiberacion($op));
            return;
        }

        if (!$this->consumeDeProduccion($op)) {
            $this->liberarConsumidor($op, $this->estadoLiberacion($op), $ids);
            return;
        }

        $contexto ??= $this->contextoMateriales($ids);
        $contexto['fisico_tomado'] ??= [];

        $necesidades = DB::table('kit_procesos')
            ->where('orden_produccion_id', $op->id)
            ->whereIn('material_id', $ids)
            ->groupBy('material_id')
            ->selectRaw('material_id, SUM(cantidad_requerida) as requerida')
            ->get()
            ->mapWithKeys(fn ($fila) => [(int) $fila->material_id => (float) $fila->requerida]);

        foreach ($necesidades as $materialId => $requerida) {
            $this->sincronizarMaterial($op, (int) $materialId, (float) $requerida, $contexto);
        }

        // Reservas de materiales que ya no están en el kit de la OP.
        $sobrantes = Compromisomaterial::query()
            ->where('op_destino_id', $op->id)
            ->activos()
            ->whereIn('material_id', $ids)
            ->get()
            ->filter(fn (Compromisomaterial $fila) => !isset($necesidades[(int) $fila->material_id]));

        foreach ($sobrantes as $fila) {
            $fila->cantidad = 0;
            $fila->cantidad_cumplida = 0;
            $fila->estado = Compromisomaterial::ESTADO_LIBERADO;
            $fila->save();
        }
    }

    /**
     * Reparte lo recibido por una OP productora entre sus compromisos, en
     * orden de OP consumidora. No toca inventario: solo marca qué parte de la
     * reserva ya tiene material físico detrás.
     *
     * @param  array<int, int>|null  $materialIds
     */
    public function sincronizarCumplimiento(OrdenProduccion $productor, ?array $materialIds = null): void
    {
        $ids = $materialIds === null
            ? $this->normalizarIds(DB::table('orden_produccion_material')->where('orden_produccion_id', $productor->id)->pluck('material_id')->all())
            : $this->normalizarIds($materialIds);

        if (empty($ids)) {
            return;
        }

        $recibido = [];
        DB::table('distribucion_orden_produccion as dop')
            ->join('distribucion_por_tallas as d', 'd.id', '=', 'dop.distribucion_por_talla_id')
            ->where('dop.orden_produccion_id', $productor->id)
            ->whereIn('d.material_id', $ids)
            ->groupBy('d.material_id')
            ->selectRaw('d.material_id, SUM(COALESCE(d.cantidad_recibida_buena, 0)) as recibido')
            ->get()
            ->each(function ($fila) use (&$recibido) {
                $recibido[(int) $fila->material_id] = (float) $fila->recibido;
            });

        $filas = Compromisomaterial::query()
            ->where('op_origen_id', $productor->id)
            ->activos()
            ->whereIn('material_id', $ids)
            ->orderBy('op_destino_id')
            ->orderBy('id')
            ->get()
            ->groupBy('material_id');

        foreach ($filas as $materialId => $compromisos) {
            $restante = max(0, $recibido[(int) $materialId] ?? 0);

            foreach ($compromisos as $fila) {
                $cantidad = max(0, (float) $fila->cantidad);
                $cumplida = min($cantidad, $restante);

                $fila->cantidad_cumplida = round($cumplida, 4);
                $fila->estado = $cantidad > 0 && $cumplida + self::TOLERANCIA >= $cantidad
                    ? Compromisomaterial::ESTADO_CUMPLIDO
                    : Compromisomaterial::ESTADO_COMPROMETIDO;
                $fila->save();

                $restante = max(0, $restante - $cumplida);
            }
        }
    }

    /**
     * Libera (o marca como consumidas) todas las reservas de una OP destino.
     *
     * @param  array<int, int>|null  $materialIds
     */
    public function liberarConsumidor(OrdenProduccion $op, string $estado = Compromisomaterial::ESTADO_LIBERADO, ?array $materialIds = null): void
    {
        $query = Compromisomaterial::query()
            ->where('op_destino_id', $op->id)
            ->activos();

        if ($materialIds !== null) {
            $ids = $this->normalizarIds($materialIds);
            if (empty($ids)) {
                return;
            }
            $query->whereIn('material_id', $ids);
        }

        $query->update(['estado' => $estado]);
    }

    /**
     * Borra las reservas de una OP antes de eliminarla. Las llaves foráneas
     * también lo harían en cascada; se hace explícito para poder recalcular
     * los materiales que quedan sin esa producción.
     */
    public function eliminarParaOrden(int $opId): void
    {
        Compromisomaterial::query()
            ->where('op_origen_id', $opId)
            ->orWhere('op_destino_id', $opId)
            ->delete();
    }

    private function estadoLiberacion(OrdenProduccion $op): string
    {
        return ($op->estadoOp?->nombre === 'Borrador')
            ? Compromisomaterial::ESTADO_LIBERADO
            : Compromisomaterial::ESTADO_CONSUMIDO;
    }

    private function liberarConsumidoresInactivos(array $materialIds): void
    {
        $activos = Compromisomaterial::query()
            ->whereIn('material_id', $materialIds)
            ->activos()
            ->get();

        if ($activos->isEmpty()) {
            return;
        }

        $estados = DB::table('orden_produccions as op')
            ->leftJoin('estado_operacions as e', 'e.id', '=', 'op.estado_op_id')
            ->whereIn('op.id', $activos->pluck('op_destino_id')->map(fn ($id) => (int) $id)->unique()->all())
            ->pluck('e.nombre', 'op.id');

        foreach ($activos as $fila) {
            $estado = $estados[(int) $fila->op_destino_id] ?? null;
            if (in_array($estado, self::ESTADOS_CONSUMIDOR, true)) {
                continue;
            }

            $fila->estado = in_array($estado, ['En taller', 'Recibiendo', 'Cerrada'], true)
                ? Compromisomaterial::ESTADO_CONSUMIDO
                : Compromisomaterial::ESTADO_LIBERADO;
            $fila->save();
        }
    }

    // ─────────────────────────────────────────────────────────
    //  NÚCLEO DE LA ASIGNACIÓN
    // ─────────────────────────────────────────────────────────

    /**
     * Reparte la necesidad de un material entre la existencia física libre y
     * la producción pendiente de las OP que todavía no la han comprometido.
     */
    private function sincronizarMaterial(OrdenProduccion $op, int $materialId, float $necesidad, array &$contexto): void
    {
        $necesidad = max(0, $necesidad);
        $fisico = (float) ($contexto['fisico'][$materialId] ?? 0);
        $pendientes = $contexto['pendiente'][$materialId] ?? [];
        $miPrioridad = $contexto['prioridad'][$op->id] ?? PHP_INT_MAX;

        $activos = Compromisomaterial::query()
            ->where('material_id', $materialId)
            ->activos()
            ->get();

        $propios = $activos->filter(fn (Compromisomaterial $fila) => (int) $fila->op_destino_id === (int) $op->id)->values();

        // Solo las OPs con prioridad (id menor) ya reservaron antes que esta.
        $otros = $activos->filter(function (Compromisomaterial $fila) use ($op, $contexto, $miPrioridad) {
            if ((int) $fila->op_destino_id === (int) $op->id) {
                return false;
            }

            $prioridad = $contexto['prioridad'][(int) $fila->op_destino_id] ?? null;

            return $prioridad !== null && $prioridad < $miPrioridad;
        });

        $otrasPendientes = [];
        foreach ($otros as $fila) {
            $origen = (int) $fila->op_origen_id;
            $pendiente = max(0, (float) $fila->cantidad - (float) $fila->cantidad_cumplida);
            $otrasPendientes[$origen] = ($otrasPendientes[$origen] ?? 0) + $pendiente;
        }

        // La existencia se reparte FIFO: lo que las OP anteriores ya cubrieron
        // con físico no está disponible para esta.
        $fisicoLibre = max(0, $fisico - (float) ($contexto['fisico_tomado'][$materialId] ?? 0));
        $coberturaFisica = min($necesidad, $fisicoLibre);
        $contexto['fisico_tomado'][$materialId] = (float) ($contexto['fisico_tomado'][$materialId] ?? 0)
            + $coberturaFisica;

        // Lo ya cumplido no puede superar lo que la OP necesita ni el físico libre.
        $cumplidoTotal = $propios->sum(fn (Compromisomaterial $fila) => max(0, (float) $fila->cantidad_cumplida));
        $cumplidoObjetivo = min($cumplidoTotal, $necesidad, $coberturaFisica);
        $reducir = $cumplidoTotal - $cumplidoObjetivo;

        foreach ($propios->sortBy('id') as $fila) {
            if ($reducir <= self::TOLERANCIA) {
                break;
            }

            $actual = max(0, (float) $fila->cantidad_cumplida);
            $quitar = min($actual, $reducir);
            $fila->cantidad_cumplida = round($actual - $quitar, 4);
            $reducir -= $quitar;
        }

        $necesidadPendiente = max(0, $necesidad - $coberturaFisica);

        // Capacidad real de cada OP productora: lo pendiente menos lo que ya
        // reservaron las OP consumidoras con prioridad.
        $capacidad = [];
        foreach ($pendientes as $origenId => $pendiente) {
            $origenId = (int) $origenId;
            if ($origenId === (int) $op->id) {
                continue;
            }

            $capacidad[$origenId] = max(0, (float) $pendiente - ($otrasPendientes[$origenId] ?? 0));
        }

        $objetivo = min($necesidadPendiente, array_sum($capacidad));
        $asignado = 0.0;
        $porFila = [];

        foreach ($propios->sortBy('id') as $fila) {
            $origen = (int) $fila->op_origen_id;
            $disponible = $capacidad[$origen] ?? 0;

            if ($objetivo <= self::TOLERANCIA || $asignado >= $objetivo - self::TOLERANCIA || $disponible <= self::TOLERANCIA) {
                $porFila[$fila->id] = 0.0;
                continue;
            }

            $tomar = min($disponible, $objetivo - $asignado);
            $porFila[$fila->id] = $tomar;
            $capacidad[$origen] = $disponible - $tomar;
            $asignado += $tomar;
        }

        // Lo que falta se reserva en OP productoras nuevas, por fecha de envío
        // a taller y luego por antigüedad de la OP.
        if ($asignado < $objetivo - self::TOLERANCIA) {
            $origenes = array_keys($capacidad);
            usort($origenes, fn ($a, $b) => $this->ordenarProductores($a, $b, $contexto['productores']));

            foreach ($origenes as $origen) {
                if ($asignado >= $objetivo - self::TOLERANCIA) {
                    break;
                }

                $disponible = $capacidad[$origen] ?? 0;
                if ($disponible <= self::TOLERANCIA) {
                    continue;
                }

                $tomar = min($disponible, $objetivo - $asignado);
                $asignado += $tomar;

                $fila = Compromisomaterial::firstOrNew([
                    'material_id' => $materialId,
                    'op_origen_id' => $origen,
                    'op_destino_id' => $op->id,
                ]);

                $activa = $fila->exists && in_array($fila->estado, Compromisomaterial::ESTADOS_ACTIVOS, true);
                if (!$activa) {
                    $fila->cantidad_cumplida = 0;
                    $fila->cantidad = round($tomar, 4);
                } else {
                    $fila->cantidad = round(max((float) $fila->cantidad_cumplida, (float) $fila->cantidad_cumplida + $tomar), 4);
                }

                $fila->estado = $tomar > self::TOLERANCIA
                    ? Compromisomaterial::ESTADO_COMPROMETIDO
                    : Compromisomaterial::ESTADO_CUMPLIDO;
                $fila->save();
            }
        }

        foreach ($propios as $fila) {
            $cumplida = max(0, (float) $fila->cantidad_cumplida);
            $pendiente = $porFila[$fila->id] ?? 0.0;

            if ($pendiente <= self::TOLERANCIA && $cumplida <= self::TOLERANCIA) {
                $fila->cantidad = 0;
                $fila->estado = Compromisomaterial::ESTADO_LIBERADO;
                $fila->save();
                continue;
            }

            $fila->cantidad = round($cumplida + $pendiente, 4);
            $fila->estado = $pendiente > self::TOLERANCIA
                ? Compromisomaterial::ESTADO_COMPROMETIDO
                : Compromisomaterial::ESTADO_CUMPLIDO;
            $fila->save();
        }
    }

    // ─────────────────────────────────────────────────────────
    //  PROYECCIÓN PARA LAS VISTAS
    // ─────────────────────────────────────────────────────────

    /**
     * Proyección por material. Devuelve un mapa material_id => detalle para
     * pintar la columna Proyectado y el modal de detalle.
     *
     * @param  array<int, int>|null  $materialIds  null = todos los materiales
     * @return array<int, array<string, mixed>>
     */
    public function proyeccionPorMaterial(?array $materialIds = null): array
    {
        $consulta = DB::table('materials')
            ->select('id', 'codigo_material', 'nombre_base', 'unidad_inventario', 'existencia_actual');

        if ($materialIds !== null) {
            $ids = $this->normalizarIds($materialIds);
            if (empty($ids)) {
                return [];
            }
            $consulta->whereIn('id', $ids);
        }

        $materiales = $consulta->orderBy('nombre_base')->get();
        if ($materiales->isEmpty()) {
            return [];
        }

        $ids = $materiales->pluck('id')->map(fn ($id) => (int) $id)->all();
        $detalleProduccion = $this->detalleProduccion($ids);
        $consumidores = $this->ordenesConKit($ids, self::ESTADOS_CON_KIT);

        $activos = Compromisomaterial::query()
            ->whereIn('material_id', $ids)
            ->activos()
            ->get()
            ->groupBy('material_id');

        // Solo se detallan las OP que consumen hoy o que tienen una reserva
        // vigente; los borradores y las cerradas no ensucian el detalle.
        $opsConsumidoras = $consumidores->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($activos as $filas) {
            foreach ($filas as $fila) {
                $opsConsumidoras[] = (int) $fila->op_destino_id;
            }
        }
        $necesidades = $this->necesidadesPorMaterial($ids, array_values(array_unique($opsConsumidoras)));

        $opsReferenciadas = [];
        foreach ($detalleProduccion as $filas) {
            foreach (array_keys($filas) as $opId) {
                $opsReferenciadas[$opId] = true;
            }
        }
        foreach ($activos as $filas) {
            foreach ($filas as $fila) {
                $opsReferenciadas[(int) $fila->op_origen_id] = true;
                $opsReferenciadas[(int) $fila->op_destino_id] = true;
            }
        }
        foreach ($necesidades as $filas) {
            foreach (array_keys($filas) as $opId) {
                $opsReferenciadas[$opId] = true;
            }
        }

        $metaOps = $this->metaOperaciones(array_keys($opsReferenciadas));

        $resultado = [];

        foreach ($materiales as $material) {
            $materialId = (int) $material->id;
            $fisico = (float) $material->existencia_actual;
            $filasActivas = $activos[$materialId] ?? collect();
            $comprometido = $filasActivas->sum(fn (Compromisomaterial $fila) => max(0, (float) $fila->cantidad));

            $produccion = $detalleProduccion[$materialId] ?? [];
            $pendiente = 0.0;
            foreach ($produccion as $fila) {
                $pendiente += (float) $fila['pendiente'];
            }

            $porOrigen = $filasActivas
                ->groupBy(fn (Compromisomaterial $fila) => (int) $fila->op_origen_id)
                ->map(fn ($filas) => $filas->sum(fn (Compromisomaterial $fila) => max(0, (float) $fila->cantidad)));

            $productores = [];
            foreach ($produccion as $opId => $fila) {
                $reservado = (float) ($porOrigen[$opId] ?? 0);
                if ((float) $fila['pendiente'] <= self::TOLERANCIA && $reservado <= self::TOLERANCIA) {
                    continue;
                }

                $productores[] = [
                    'op_id' => (int) $opId,
                    'numero_op' => $fila['numero_op'],
                    'estado' => $fila['estado'],
                    'fecha' => $fila['fecha'],
                    'planificado' => round((float) $fila['planificado'], 4),
                    'recibido' => round((float) $fila['recibido'], 4),
                    'pendiente' => round((float) $fila['pendiente'], 4),
                    'comprometido' => round($reservado, 4),
                    'libre' => round(max(0, (float) $fila['pendiente'] - $reservado), 4),
                ];
            }
            usort($productores, fn ($a, $b) => $this->ordenarProductores($a['op_id'], $b['op_id'], $metaOps));

            $porDestino = $filasActivas
                ->groupBy(fn (Compromisomaterial $fila) => (int) $fila->op_destino_id)
                ->map(fn ($filas) => $filas->sum(fn (Compromisomaterial $fila) => max(0, (float) $fila->cantidad)));

            $consumidoresDetalle = [];
            $opsConsumidoras = [];
            foreach ($consumidores as $consumidor) {
                $opsConsumidoras[(int) $consumidor->id] = $consumidor;
            }
            foreach ($necesidades[$materialId] ?? [] as $opId => $requerida) {
                if (!isset($opsConsumidoras[$opId])) {
                    $opsConsumidoras[$opId] = null;
                }
            }

            ksort($opsConsumidoras);
            $fisicoTomado = 0.0;

            foreach ($opsConsumidoras as $opId => $consumidor) {
                $requerida = (float) ($necesidades[$materialId][$opId] ?? 0);
                $reservado = (float) ($porDestino[$opId] ?? 0);
                if ($requerida <= self::TOLERANCIA && $reservado <= self::TOLERANCIA) {
                    continue;
                }

                $meta = $metaOps[$opId] ?? null;
                $estado = $consumidor?->estadoOp?->nombre ?? ($meta['estado'] ?? null);
                $activa = in_array($estado, self::ESTADOS_CONSUMIDOR, true);

                $compromisos = $filasActivas
                    ->where('op_destino_id', $opId)
                    ->sortBy('id')
                    ->map(function (Compromisomaterial $fila) use ($metaOps) {
                        $origen = $metaOps[(int) $fila->op_origen_id] ?? null;

                        return [
                            'op_id' => (int) $fila->op_origen_id,
                            'numero_op' => $origen['numero_op'] ?? ('OP #' . $fila->op_origen_id),
                            'estado' => $origen['estado'] ?? null,
                            'fecha' => $origen['fecha'] ?? null,
                            'cantidad' => round(max(0, (float) $fila->cantidad), 4),
                            'cumplida' => round(max(0, (float) $fila->cantidad_cumplida), 4),
                            'pendiente' => round(max(0, (float) $fila->cantidad - (float) $fila->cantidad_cumplida), 4),
                        ];
                    })
                    ->values()
                    ->all();

                $cumplidaPropia = collect($compromisos)->sum('cumplida');
                $otras = max(0, $comprometido - $reservado);

                if ($activa) {
                    // La existencia se reparte FIFO entre las OP que siguen en
                    // preparación; lo reservado cubre lo que el físico no alcanza.
                    $fisicoLibre = max(0, $fisico - $fisicoTomado);
                    $cobertura = min($requerida, $fisicoLibre);
                    $fisicoTomado += $cobertura;
                    $disponible = $cobertura + max(0, $reservado - $cumplidaPropia);
                } else {
                    // Ya entró a taller o está recibiendo: su kit ya se cubrió.
                    $disponible = $requerida;
                }

                $consumidoresDetalle[] = [
                    'op_id' => (int) $opId,
                    'numero_op' => $consumidor?->numero_op ?? ($meta['numero_op'] ?? ('OP #' . $opId)),
                    'estado' => $estado,
                    'fecha' => $consumidor?->fecha_prevista_envio_taller
                        ? (string) $consumidor->fecha_prevista_envio_taller
                        : ($meta['fecha'] ?? null),
                    'requerida' => round($requerida, 4),
                    'comprometido' => round($reservado, 4),
                    'comprometido_otras' => round($otras, 4),
                    'disponible_proyectado' => round($disponible, 4),
                    'faltante_proyectado' => $activa ? round(max(0, $requerida - $disponible), 4) : 0.0,
                    'compromisos' => $compromisos,
                ];
            }

            $resultado[$materialId] = [
                'material_id' => $materialId,
                'codigo' => $material->codigo_material,
                'nombre' => trim(($material->codigo_material ? $material->codigo_material . ' — ' : '') . $material->nombre_base),
                'unidad' => $material->unidad_inventario ?: 'u',
                'fisico' => round($fisico, 4),
                'pendiente' => round($pendiente, 4),
                'comprometido' => round($comprometido, 4),
                'proyectado' => round($fisico + $pendiente - $comprometido, 4),
                'productores' => $productores,
                'consumidores' => $consumidoresDetalle,
            ];
        }

        return $resultado;
    }

    public function proyeccionDeMaterial(int $materialId): ?array
    {
        return $this->proyeccionPorMaterial([$materialId])[$materialId] ?? null;
    }

    /**
     * Fila de proyección para cada material del kit de una OP consumidora.
     *
     * @param  array<int, int>|null  $materialIds
     * @return array<int, array<string, mixed>>
     */
    public function resumenParaConsumidor(OrdenProduccion $op, ?array $materialIds = null): array
    {
        $ids = $materialIds === null
            ? $this->normalizarIds(DB::table('kit_procesos')->where('orden_produccion_id', $op->id)->pluck('material_id')->all())
            : $this->normalizarIds($materialIds);

        if (empty($ids)) {
            return [];
        }

        $proyeccion = $this->proyeccionPorMaterial($ids);
        $necesidades = $this->necesidadesPorMaterial($ids, [(int) $op->id]);

        $resultado = [];
        foreach ($ids as $materialId) {
            $fila = $proyeccion[$materialId] ?? null;
            if (!$fila) {
                continue;
            }

            $requerida = (float) ($necesidades[$materialId][(int) $op->id] ?? 0);
            $consumidor = collect($fila['consumidores'])->firstWhere('op_id', (int) $op->id);

            $comprometido = (float) ($consumidor['comprometido'] ?? 0);
            $otras = max(0, (float) $fila['comprometido'] - $comprometido);
            $disponible = $consumidor
                ? (float) $consumidor['disponible_proyectado']
                : max(0, (float) $fila['proyectado']);

            $resultado[] = [
                'material_id' => $materialId,
                'codigo' => $fila['codigo'],
                'nombre' => $fila['nombre'],
                'unidad' => $fila['unidad'],
                'requerida' => round($requerida, 4),
                'fisico' => round((float) $fila['fisico'], 4),
                'pendiente' => round((float) $fila['pendiente'], 4),
                'comprometido' => round($comprometido, 4),
                'comprometido_otras' => round($otras, 4),
                'disponible_proyectado' => round($disponible, 4),
                'faltante_proyectado' => round(max(0, $requerida - $disponible), 4),
                'origenes' => $consumidor['compromisos'] ?? [],
            ];
        }

        return $resultado;
    }

    // ─────────────────────────────────────────────────────────
    //  CARGA DE DATOS
    // ─────────────────────────────────────────────────────────

    /**
     * OPs con material ya ingresado de estos materiales. Aunque su producción
     * esté completa (o la OP ya cerrada), su parte recibida sigue siendo la
     * que respalda físicamente los compromisos.
     *
     * @param  array<int, int>  $materialIds
     * @return array<int, int>
     */
    private function productoresConRecepcion(array $materialIds): array
    {
        return DB::table('distribucion_orden_produccion as dop')
            ->join('distribucion_por_tallas as d', 'd.id', '=', 'dop.distribucion_por_talla_id')
            ->whereIn('d.material_id', $materialIds)
            ->where('d.cantidad_recibida_buena', '>', 0)
            ->distinct()
            ->pluck('dop.orden_produccion_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $materialIds
     * @return array<string, mixed>
     */
    private function contextoMateriales(array $materialIds): array
    {
        $detalle = $this->detalleProduccion($materialIds);
        $pendiente = [];
        $productores = [];
        $produccionOps = [];

        foreach ($detalle as $materialId => $filas) {
            foreach ($filas as $opId => $fila) {
                $produccionOps[$opId] = true;
                $productores[$opId] = true;

                if ((float) $fila['pendiente'] > self::TOLERANCIA) {
                    $pendiente[$materialId][$opId] = (float) $fila['pendiente'];
                }
            }
        }

        $fisico = DB::table('materials')
            ->whereIn('id', $materialIds)
            ->pluck('existencia_actual', 'id')
            ->map(fn ($valor) => (float) $valor)
            ->all();

        $consumidores = $this->ordenesConKit($materialIds, self::ESTADOS_CONSUMIDOR);
        $prioridad = [];
        foreach ($consumidores as $posicion => $consumidor) {
            $prioridad[(int) $consumidor->id] = $posicion;
        }

        return [
            'fisico' => $fisico,
            'pendiente' => $pendiente,
            'productores' => $this->metaOperaciones(array_keys($productores)),
            'produccion_ops' => array_keys($produccionOps),
            'consumidores' => $consumidores,
            'prioridad' => $prioridad,
        ];
    }

    /**
     * Planificado, recibido y pendiente de cada OP productora por material.
     *
     * @param  array<int, int>  $materialIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function detalleProduccion(array $materialIds): array
    {
        $planificados = DB::table('orden_produccion_material as opm')
            ->join('orden_produccions as op', 'op.id', '=', 'opm.orden_produccion_id')
            ->leftJoin('estado_operacions as e', 'e.id', '=', 'op.estado_op_id')
            ->whereIn('opm.material_id', $materialIds)
            ->where(function ($query) {
                $query->whereNull('e.nombre')->orWhereNotIn('e.nombre', self::ESTADOS_SIN_PRODUCCION);
            })
            ->groupBy('opm.material_id', 'opm.orden_produccion_id')
            ->selectRaw('opm.material_id, opm.orden_produccion_id as op_id, SUM(opm.cantidad) as planificado')
            ->get();

        $recibidos = [];
        DB::table('distribucion_orden_produccion as dop')
            ->join('distribucion_por_tallas as d', 'd.id', '=', 'dop.distribucion_por_talla_id')
            ->whereIn('d.material_id', $materialIds)
            ->groupBy('dop.orden_produccion_id', 'd.material_id')
            ->selectRaw('dop.orden_produccion_id as op_id, d.material_id, SUM(COALESCE(d.cantidad_recibida_buena, 0)) as recibido')
            ->get()
            ->each(function ($fila) use (&$recibidos) {
                $recibidos[(int) $fila->material_id][(int) $fila->op_id] = (float) $fila->recibido;
            });

        $meta = $this->metaOperaciones($planificados->pluck('op_id')->map(fn ($id) => (int) $id)->unique()->all());
        $detalle = [];

        foreach ($planificados as $fila) {
            $materialId = (int) $fila->material_id;
            $opId = (int) $fila->op_id;
            $planificado = (float) $fila->planificado;
            $recibido = (float) ($recibidos[$materialId][$opId] ?? 0);

            $detalle[$materialId][$opId] = [
                'op_id' => $opId,
                'numero_op' => $meta[$opId]['numero_op'] ?? ('OP #' . $opId),
                'estado' => $meta[$opId]['estado'] ?? null,
                'fecha' => $meta[$opId]['fecha'] ?? null,
                'planificado' => $planificado,
                'recibido' => $recibido,
                'pendiente' => max(0, $planificado - $recibido),
            ];
        }

        return $detalle;
    }

    /**
     * @param  array<int, int>  $materialIds
     * @param  array<int, string>|null  $estados
     * @return Collection<int, OrdenProduccion>
     */
    private function ordenesConKit(array $materialIds, ?array $estados = null): Collection
    {
        return OrdenProduccion::query()
            ->with('estadoOp')
            ->whereHas('kitProcesos', fn ($query) => $query->whereIn('material_id', $materialIds))
            ->when($estados, fn ($query) => $query->whereHas('estadoOp', fn ($estado) => $estado->whereIn('nombre', $estados)))
            ->orderBy('id')
            ->get(['id', 'numero_op', 'estado_op_id', 'fecha_prevista_envio_taller']);
    }

    /**
     * Necesidad de cada OP por material, según su kit.
     *
     * @param  array<int, int>  $materialIds
     * @param  array<int, int>|null  $opIds
     * @return array<int, array<int, float>>
     */
    private function necesidadesPorMaterial(array $materialIds, ?array $opIds = null): array
    {
        $consulta = DB::table('kit_procesos')
            ->whereIn('material_id', $materialIds)
            ->groupBy('orden_produccion_id', 'material_id')
            ->selectRaw('orden_produccion_id as op_id, material_id, SUM(cantidad_requerida) as requerida');

        if ($opIds !== null) {
            $consulta->whereIn('orden_produccion_id', $opIds);
        }

        $resultado = [];
        foreach ($consulta->get() as $fila) {
            $resultado[(int) $fila->material_id][(int) $fila->op_id] = (float) $fila->requerida;
        }

        return $resultado;
    }

    /**
     * @param  array<int, int>  $opIds
     * @return array<int, array<string, mixed>>
     */
    private function metaOperaciones(array $opIds): array
    {
        if (empty($opIds)) {
            return [];
        }

        return DB::table('orden_produccions as op')
            ->leftJoin('estado_operacions as e', 'e.id', '=', 'op.estado_op_id')
            ->whereIn('op.id', $opIds)
            ->select('op.id as id', 'op.numero_op', 'op.fecha_prevista_envio_taller', 'e.nombre as estado')
            ->get()
            ->mapWithKeys(fn ($fila) => [
                (int) $fila->id => [
                    'op_id' => (int) $fila->id,
                    'numero_op' => $fila->numero_op ?: ('OP #' . $fila->id),
                    'estado' => $fila->estado,
                    'fecha' => $fila->fecha_prevista_envio_taller
                        ? (string) $fila->fecha_prevista_envio_taller
                        : null,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private function normalizarIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function ordenarProductores(int $a, int $b, array $meta): int
    {
        $fechaA = $meta[$a]['fecha'] ?? null;
        $fechaB = $meta[$b]['fecha'] ?? null;

        if ($fechaA !== $fechaB) {
            if ($fechaA === null) {
                return 1;
            }
            if ($fechaB === null) {
                return -1;
            }

            return strcmp((string) $fechaA, (string) $fechaB);
        }

        return $a <=> $b;
    }

    public function consumeDeProduccion(OrdenProduccion $op): bool
    {
        $op->load('estadoOp');

        return in_array($op->estadoOp?->nombre, self::ESTADOS_CONSUMIDOR, true);
    }
}
