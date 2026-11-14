<?php

namespace App\Services;

use App\Models\EstadoOperacion;
use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\OrdenProduccionCostoCambio;
use App\Models\OrdenProduccionCostoMaterial;
use App\Models\Parametro;
use App\Models\Producto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * Costo de los materiales de una OP.
 *
 * El parámetro «costos_promedio» decide de dónde sale el precio unitario de
 * cada material:
 * 1 = promedio de todos los precios registrados del material (sus cambios).
 * 2 = el último precio digitado del material.
 *
 * El precio usado se guarda en la OP (orden_produccion_costos_materiales) y
 * cada vez que ese precio guardado cambia se anota el cambio en
 * orden_produccion_costos_cambios. Las OPs cerradas no se recalculan: su costo
 * queda congelado con el precio que tenían al cerrarse.
 */
class CostoMaterialOpService
{
    /** El precio de venta es automático: un 300% del coste de producción. */
    public const MULTIPLICADOR_PRECIO_VENTA = 3;

    /** El precio de venta se redondea siempre al millar superior. */
    public const REDONDEO_PRECIO_VENTA = 1000;

    /**
     * Precio de venta automático a partir del coste de producción. Es la misma
     * regla que aplican los formularios de Producto, para que lo que se ve en
     * pantalla sea lo que queda guardado.
     */
    public static function precioVentaAutomatico(float $costeProduccion): float
    {
        $precio = $costeProduccion * self::MULTIPLICADOR_PRECIO_VENTA;

        return (float) (ceil($precio / self::REDONDEO_PRECIO_VENTA) * self::REDONDEO_PRECIO_VENTA);
    }

    /** Método vigente según el parámetro costos_promedio. */
    public function metodo(): int
    {
        return Parametro::entero(Parametro::COSTOS_PROMEDIO, Parametro::COSTOS_PROMEDIO_PROMEDIO, [
            Parametro::COSTOS_PROMEDIO_PROMEDIO,
            Parametro::COSTOS_PROMEDIO_ULTIMO,
        ]);
    }

    public function labelMetodo(?int $metodo = null): string
    {
        return ($metodo ?? $this->metodo()) === Parametro::COSTOS_PROMEDIO_ULTIMO
            ? 'Último precio digitado del material'
            : 'Promedio de los cambios de precio del material';
    }

    /**
     * Precio unitario del material según el método. Acepta un material ya
     * cargado con su promedio (prices_avg_precio) y su último precio, para no
     * volver a consultar la base por cada línea.
     */
    public function precioUnitario(Material $material, int $metodo): float
    {
        if ($metodo === Parametro::COSTOS_PROMEDIO_ULTIMO) {
            return $material->costoUnitarioActual();
        }

        if ($material->getAttribute('prices_avg_precio') !== null) {
            return round((float) $material->getAttribute('prices_avg_precio'), 2);
        }

        return $material->costoUnitarioPromedio();
    }

    /**
     * Unidades de cada producto de la OP. La cantidad sale de la distribución
     * por tallas; las OPs antiguas sin distribución usan la cantidad
     * planificada total de su producto de referencia.
     *
     * @return array<int, float>
     */
    public function cantidadesPorProducto(OrdenProduccion $op): array
    {
        $cantidades = [];
        $sinProducto = 0.0;

        foreach ($op->distribucionesPorTallas as $distribucion) {
            // Las filas de un material procesado no son unidades de un producto.
            if (!empty($distribucion->material_id)) {
                continue;
            }

            $productoId = (int) ($distribucion->producto_id ?? 0);
            $cantidad = (float) ($distribucion->cantidad_planificada ?? 0);

            if ($productoId > 0) {
                $cantidades[$productoId] = ($cantidades[$productoId] ?? 0) + $cantidad;
            } else {
                $sinProducto += $cantidad;
            }
        }

        if ($sinProducto > 0) {
            $destino = (int) ($op->producto_referencia_id ?? $op->productos->first()?->id ?? 0);
            if ($destino > 0) {
                $cantidades[$destino] = ($cantidades[$destino] ?? 0) + $sinProducto;
            }
        }

        if (empty($cantidades)) {
            $destino = (int) ($op->producto_referencia_id ?? $op->productos->first()?->id ?? 0);
            if ($destino > 0) {
                $cantidades[$destino] = (float) $op->cantidad_planificada;
            }
        }

        return $cantidades;
    }

    /**
     * Productos de la OP: los vinculados más el producto de referencia.
     *
     * @return Collection<int, Producto>
     */
    public function productos(OrdenProduccion $op): Collection
    {
        $productos = $op->productos;

        if ($op->productoReferencia && !$productos->contains('id', $op->productoReferencia->id)) {
            $productos = $productos->concat([$op->productoReferencia]);
        }

        return $productos->values();
    }

    /**
     * Unidades de cada material procesado que produce la OP. La cantidad vive
     * en el pivote orden_produccion_material; si la OP es antigua y aún no la
     * tiene, se reconstruye desde su distribución.
     *
     * @return array<int, float>
     */
    public function cantidadesPorMaterial(OrdenProduccion $op): array
    {
        $cantidades = [];

        foreach ($op->materiales as $material) {
            $cantidad = (float) ($material->pivot->cantidad ?? 0);
            if ($cantidad > 0) {
                $cantidades[(int) $material->id] = $cantidad;
            }
        }

        if (!empty($cantidades)) {
            return $cantidades;
        }

        foreach ($op->distribucionesPorTallas as $distribucion) {
            $materialId = (int) ($distribucion->material_id ?? 0);
            if ($materialId > 0) {
                $cantidades[$materialId] = ($cantidades[$materialId] ?? 0)
                    + (float) ($distribucion->cantidad_planificada ?? 0);
            }
        }

        return $cantidades;
    }

    /**
     * Materiales procesados que produce la OP.
     *
     * @return Collection<int, Material>
     */
    public function materialesObjetivo(OrdenProduccion $op): Collection
    {
        return $op->materiales->values();
    }

    /**
     * Materiales que requieren los productos de la OP, con el precio del método
     * vigente y su costo.
     *
     * @return array<int, array{material_id:int, nombre:string, unidad:string, cantidad:float, precio_unitario:float, costo_total:float}>
     */
    public function lineas(OrdenProduccion $op, ?int $metodo = null): array
    {
        $metodo ??= $this->metodo();
        $cantidades = $this->cantidadesPorProducto($op);
        $cantidadesMateriales = $this->cantidadesPorMaterial($op);
        $unidades = $this->unidadesPorMaterial($op);

        // Consumo agregado por material: consumo_estandar de la ficha × unidades
        // del producto o material procesado que produce la OP.
        $necesidad = [];
        foreach ($this->productos($op) as $producto) {
            $cantidad = (float) ($cantidades[$producto->id] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $ficha = $producto->fichaTecnica->first();
            if (!$ficha) {
                continue;
            }

            foreach ($ficha->materiales as $fila) {
                $materialId = (int) $fila->material_componente_id;
                if ($materialId <= 0) {
                    continue;
                }

                $necesidad[$materialId] = ($necesidad[$materialId] ?? 0)
                    + (float) $fila->consumo_estandar * $cantidad;
            }
        }

        foreach ($this->materialesObjetivo($op) as $materialObjetivo) {
            $cantidad = (float) ($cantidadesMateriales[$materialObjetivo->id] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $ficha = $materialObjetivo->fichaTecnica->first();
            if (!$ficha) {
                continue;
            }

            foreach ($ficha->materiales as $fila) {
                $materialId = (int) $fila->material_componente_id;
                if ($materialId <= 0) {
                    continue;
                }

                $necesidad[$materialId] = ($necesidad[$materialId] ?? 0)
                    + (float) $fila->consumo_estandar * $cantidad;
            }
        }

        if (empty($necesidad)) {
            return [];
        }

        $materiales = $this->materialesConPrecios(array_keys($necesidad));

        $lineas = [];
        foreach ($necesidad as $materialId => $cantidad) {
            $material = $materiales->get($materialId);
            $precio = $material ? $this->precioUnitario($material, $metodo) : 0.0;

            $lineas[$materialId] = [
                'material_id' => $materialId,
                'nombre' => $this->nombreMaterial($material, $materialId),
                'unidad' => $unidades[$materialId] ?? 'u',
                'cantidad' => round($cantidad, 4),
                'precio_unitario' => $precio,
                'costo_total' => round($precio * $cantidad, 2),
            ];
        }

        return $lineas;
    }

    /**
     * Unidad declarada en la ficha técnica para cada material de la OP.
     *
     * @return array<int, string>
     */
    private function unidadesPorMaterial(OrdenProduccion $op): array
    {
        $unidades = [];

        foreach ($this->productos($op) as $producto) {
            foreach ($producto->fichaTecnica as $ficha) {
                foreach ($ficha->materiales as $fila) {
                    $materialId = (int) $fila->material_componente_id;
                    if ($materialId > 0 && !isset($unidades[$materialId])) {
                        $unidades[$materialId] = $fila->unidad_medida ?: 'u';
                    }
                }
            }
        }

        foreach ($this->materialesObjetivo($op) as $materialObjetivo) {
            foreach ($materialObjetivo->fichaTecnica as $ficha) {
                foreach ($ficha->materiales as $fila) {
                    $materialId = (int) $fila->material_componente_id;
                    if ($materialId > 0 && !isset($unidades[$materialId])) {
                        $unidades[$materialId] = $fila->unidad_medida ?: 'u';
                    }
                }
            }
        }

        return $unidades;
    }

    /**
     * Materiales con su último precio y su promedio ya cargados.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Material>
     */
    public function materialesConPrecios(array $ids): Collection
    {
        return Material::query()
            ->with('lastPrice')
            ->withAvg('prices', 'precio')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * Coste de producción unitario del producto: los materiales de su ficha
     * (precio del método vigente × consumo estándar) más la suma de sus procesos.
     *
     * @param  Collection<int, Material>|null  $materiales  Materiales con precios ya cargados, keyBy id.
     */
    public function costeProduccionUnitario(Producto $producto, ?Collection $materiales = null, ?int $metodo = null): float
    {
        $metodo ??= $this->metodo();

        $materiales ??= $this->materialesConPrecios($this->idsMaterialesFicha($producto));

        $costeMateriales = 0.0;
        foreach ($producto->fichaTecnica as $ficha) {
            foreach ($ficha->materiales as $fila) {
                $materialId = (int) $fila->material_componente_id;
                if ($materialId <= 0) {
                    continue;
                }

                $material = $materiales->get($materialId);
                if (!$material) {
                    continue;
                }

                $costeMateriales += $this->precioUnitario($material, $metodo) * (float) $fila->consumo_estandar;
            }
        }

        return round($costeMateriales + (float) $producto->procesos->sum(fn ($proceso) => (float) $proceso->pivot->coste_proceso), 2);
    }

    /**
     * Materiales que aparecen en la ficha técnica del producto.
     *
     * @return list<int>
     */
    public function idsMaterialesFicha(Producto $producto): array
    {
        return $producto->fichaTecnica
            ->flatMap(fn ($ficha) => $ficha->materiales->pluck('material_componente_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Coste de producción unitario con los dos métodos de precio a la vez: el
     * promedio de los precios registrados del material y su último precio. Los
     * dos quedan guardados en el producto para poder compararlos en pantalla,
     * sin depender de cuál esté vigente en el parámetro costos_promedio.
     *
     * @param  Collection<int, Material>|null  $materiales  Materiales con precios ya cargados, keyBy id.
     * @return array{promedio: float, ultimo: float}
     */
    public function costeProduccionPorMetodo(Producto $producto, ?Collection $materiales = null): array
    {
        $materiales ??= $this->materialesConPrecios($this->idsMaterialesFicha($producto));

        return [
            'promedio' => $this->costeProduccionUnitario($producto, $materiales, Parametro::COSTOS_PROMEDIO_PROMEDIO),
            'ultimo' => $this->costeProduccionUnitario($producto, $materiales, Parametro::COSTOS_PROMEDIO_ULTIMO),
        ];
    }

    /**
     * Guarda en cada producto de la OP su coste de producción unitario y su
     * ganancia. Se usa al crear/editar la OP y también al cerrarla (con los
     * precios de material congelados de esa OP).
     *
     * @return int Cantidad de productos actualizados.
     */
    public function actualizarCosteProduccion(OrdenProduccion $op): int
    {
        $metodo = $this->metodo();
        $preciosCongelados = $op->costosMateriales()->get()->keyBy('material_id');

        $productos = $this->productos($op);
        $ids = $productos
            ->flatMap(fn (Producto $producto) => $this->idsMaterialesFicha($producto))
            ->unique()
            ->values()
            ->all();
        $materiales = $this->materialesConPrecios($ids);

        foreach ($productos as $producto) {
            $costeMateriales = 0.0;
            foreach ($producto->fichaTecnica as $ficha) {
                foreach ($ficha->materiales as $fila) {
                    $materialId = (int) $fila->material_componente_id;
                    if ($materialId <= 0) {
                        continue;
                    }

                    $congelado = $preciosCongelados->get($materialId);
                    if ($congelado) {
                        $precio = (float) $congelado->precio_unitario;
                    } else {
                        $material = $materiales->get($materialId);
                        $precio = $material ? $this->precioUnitario($material, $metodo) : 0.0;
                    }

                    $costeMateriales += $precio * (float) $fila->consumo_estandar;
                }
            }

            $costeProduccion = round($costeMateriales + (float) $producto->procesos->sum(fn ($proceso) => (float) $proceso->pivot->coste_proceso), 2);
            // El precio de venta sigue al coste: siempre un 300% del coste de
            // producción, también cuando la OP lo recalcula.
            $precioVenta = self::precioVentaAutomatico($costeProduccion);

            // Los dos costes por método salen de los precios actuales del
            // material, no del snapshot de la OP: son la referencia del producto.
            $porMetodo = $this->costeProduccionPorMetodo($producto, $materiales);

            $producto->forceFill([
                'coste_produccion' => $costeProduccion,
                'coste_produccion_promedio' => $porMetodo['promedio'],
                'coste_produccion_ultimo' => $porMetodo['ultimo'],
                'precio_venta_producto' => $precioVenta,
                'ganancia_producto' => round($precioVenta - $costeProduccion, 2),
            ])->saveQuietly();
        }

        return $productos->count();
    }

    private function nombreMaterial(?Material $material, int $materialId): string
    {
        if (!$material) {
            return 'Material #' . $materialId;
        }

        $nombre = trim(
            ($material->codigo_material ? $material->codigo_material . ' — ' : '')
            . ($material->nombre_base ?? '')
        );

        return $nombre !== '' ? $nombre : ('Material #' . $materialId);
    }

    /**
     * Recalcula y guarda en la OP el precio de cada material, anotando en el
     * historial los precios que cambiaron desde la última vez.
     *
     * @return float Costo total guardado.
     */
    public function recalcular(OrdenProduccion $op, ?int $metodo = null): float
    {
        $metodo ??= $this->metodo();
        $lineas = $this->lineas($op, $metodo);
        $guardados = $op->costosMateriales()->get()->keyBy('material_id');

        $total = 0.0;
        $materialesVigentes = [];

        foreach ($lineas as $materialId => $linea) {
            $materialesVigentes[] = $materialId;
            $fila = $guardados->get($materialId);

            $datos = [
                'metodo_costo' => $metodo,
                'precio_unitario' => $linea['precio_unitario'],
                'cantidad' => $linea['cantidad'],
                'costo_total' => $linea['costo_total'],
                'user_id' => auth()->id(),
            ];

            if ($fila) {
                // Solo se guarda un cambio cuando el precio unitario es otro;
                // la cantidad puede moverse sin que el precio haya cambiado.
                if (round((float) $fila->precio_unitario, 2) !== $linea['precio_unitario']) {
                    $this->registrarCambio($op, $materialId, $metodo, (float) $fila->precio_unitario, $linea['precio_unitario']);
                }

                $fila->fill($datos)->save();
            } else {
                OrdenProduccionCostoMaterial::create($datos + [
                    'orden_produccion_id' => $op->id,
                    'material_id' => $materialId,
                ]);

                $this->registrarCambio($op, $materialId, $metodo, null, $linea['precio_unitario']);
            }

            $total += $linea['costo_total'];
        }

        // Materiales que la OP dejó de requerir: su precio guardado se retira.
        if ($materialesVigentes) {
            $op->costosMateriales()->whereNotIn('material_id', $materialesVigentes)->delete();
        } else {
            $op->costosMateriales()->delete();
        }

        $total = round($total, 2);

        // El costo de proceso de la OP ya no se digita: se deriva de los
        // procesos de sus productos antes de cerrar el costo total.
        $costoProceso = $this->costoProceso($op);
        if (round((float) ($op->costo_proceso ?? 0), 2) !== $costoProceso) {
            $op->forceFill(['costo_proceso' => $costoProceso])->saveQuietly();
        }

        $op->actualizarCostoTotal($total);

        return $total;
    }

    private function registrarCambio(
        OrdenProduccion $op,
        int $materialId,
        int $metodo,
        ?float $precioAnterior,
        float $precioNuevo
    ): void {
        OrdenProduccionCostoCambio::create([
            'orden_produccion_id' => $op->id,
            'material_id' => $materialId,
            'metodo_costo' => $metodo,
            'precio_anterior' => $precioAnterior,
            'precio_nuevo' => $precioNuevo,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Costo de los materiales de la OP: el precio guardado si la OP ya tiene su
     * snapshot; si no, se calcula en vivo sin escribir en la base.
     */
    public function costo(OrdenProduccion $op): float
    {
        $guardados = $op->costosMateriales;

        if ($guardados->isNotEmpty()) {
            return round((float) $guardados->sum('costo_total'), 2);
        }

        return round(collect($this->lineas($op))->sum('costo_total'), 2);
    }

    /**
     * Renueva el precio guardado de las OPs abiertas que usan un material, para
     * que el cambio de precio del material quede registrado en cada una.
     *
     * @return int Cantidad de OPs recalculadas.
     */
    public function recalcularPorMaterial(int $materialId): int
    {
        $ids = OrdenProduccionCostoMaterial::query()
            ->where('material_id', $materialId)
            ->distinct()
            ->pluck('orden_produccion_id');

        return $this->recalcularLista($ids);
    }

    /**
     * Renueva el precio guardado de todas las OPs abiertas. Se usa cuando cambia
     * el parámetro costos_promedio y el método de cálculo es otro.
     *
     * @return int Cantidad de OPs recalculadas.
     */
    public function recalcularAbiertas(): int
    {
        return $this->recalcularLista(
            OrdenProduccionCostoMaterial::query()->distinct()->pluck('orden_produccion_id')
        );
    }

    /**
     * Renueva las OPs abiertas que usan alguno de estos productos: se usa
     * cuando la ficha técnica cambia y el consumo (o los materiales) de la OP
     * deja de ser el que se costeó la última vez.
     *
     * @param  list<int>  $productoIds
     * @return int Cantidad de OPs recalculadas.
     */
    public function recalcularPorProductos(array $productoIds): int
    {
        if (empty($productoIds)) {
            return 0;
        }

        $cerradaId = EstadoOperacion::where('nombre', 'Cerrada')->value('id');

        $ids = OrdenProduccion::query()
            ->where(function ($consulta) use ($productoIds, $cerradaId) {
                $consulta->whereNull('estado_op_id')
                    ->orWhere('estado_op_id', '!=', $cerradaId);
            })
            ->where(function ($consulta) use ($productoIds) {
                $consulta->whereIn('producto_referencia_id', $productoIds)
                    ->orWhereHas('productos', function ($pivot) use ($productoIds) {
                        $pivot->whereIn('productos.id', $productoIds);
                    });
            })
            ->pluck('id');

        return $this->recalcularLista($ids);
    }

    /**
     * Renueva las OPs abiertas que producen alguno de estos materiales
     * procesados: su ficha técnica cambió y con ella el costo de la OP.
     *
     * @param  list<int>  $materialIds
     * @return int Cantidad de OPs recalculadas.
     */
    public function recalcularPorMateriales(array $materialIds): int
    {
        if (empty($materialIds)) {
            return 0;
        }

        $cerradaId = EstadoOperacion::where('nombre', 'Cerrada')->value('id');

        $ids = OrdenProduccion::query()
            ->where(function ($consulta) use ($cerradaId) {
                $consulta->whereNull('estado_op_id')
                    ->orWhere('estado_op_id', '!=', $cerradaId);
            })
            ->whereHas('materiales', function ($pivot) use ($materialIds) {
                $pivot->whereIn('materials.id', $materialIds);
            })
            ->pluck('id');

        return $this->recalcularLista($ids);
    }

    /**
     * Costo de proceso de la OP: la suma de coste_proceso de los procesos de
     * cada producto, multiplicada por las unidades de ese producto en la OP.
     * Ya no se digita a mano: el campo de la OP siempre sale de aquí.
     */
    public function costoProceso(OrdenProduccion $op): float
    {
        $cantidades = $this->cantidadesPorProducto($op);

        $total = 0.0;
        foreach ($this->productos($op) as $producto) {
            $cantidad = (float) ($cantidades[$producto->id] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $costeProcesos = (float) $producto->procesos->sum(fn ($proceso) => (float) $proceso->pivot->coste_proceso);
            $total += $costeProcesos * $cantidad;
        }

        return round($total, 2);
    }

    /**
     * Renueva el costo de proceso guardado en la OP y, con él, el costo total.
     */
    public function guardarCostoProceso(OrdenProduccion $op): float
    {
        $costoProceso = $this->costoProceso($op);

        if (round((float) ($op->costo_proceso ?? 0), 2) !== $costoProceso) {
            $op->forceFill(['costo_proceso' => $costoProceso])->saveQuietly();
        }

        $op->actualizarCostoTotal();

        return $costoProceso;
    }

    /**
     * Renueva las OPs abiertas que usan alguno de estos productos: los procesos
     * del producto cambiaron y con ellos el costo de proceso de la OP.
     *
     * @param  list<int>  $productoIds
     * @return int Cantidad de OPs recalculadas.
     */
    public function recalcularCostoProcesoPorProductos(array $productoIds): int
    {
        if (empty($productoIds)) {
            return 0;
        }

        $cerradaId = EstadoOperacion::where('nombre', 'Cerrada')->value('id');

        $ops = OrdenProduccion::query()
            ->with(['productos.procesos', 'productoReferencia.procesos', 'distribucionesPorTallas', 'costosMateriales'])
            ->where(function ($consulta) use ($productoIds, $cerradaId) {
                $consulta->whereNull('estado_op_id')
                    ->orWhere('estado_op_id', '!=', $cerradaId);

                $consulta->where(function ($sub) use ($productoIds) {
                    $sub->whereIn('producto_referencia_id', $productoIds)
                        ->orWhereHas('productos', function ($pivot) use ($productoIds) {
                            $pivot->whereIn('productos.id', $productoIds);
                        });
                });
            })
            ->get();

        foreach ($ops as $op) {
            $this->guardarCostoProceso($op);
        }

        if ($ops->isNotEmpty()) {
            Cache::increment('op_index_version');
            Cache::forget('op_selects');
        }

        return $ops->count();
    }

    private function recalcularLista(Collection $opIds): int
    {
        $cerradaId = EstadoOperacion::where('nombre', 'Cerrada')->value('id');

        $ops = OrdenProduccion::query()
            ->with([
                'productos.fichaTecnica.materiales',
                'productoReferencia.fichaTecnica.materiales',
                'materiales.fichaTecnica.materiales',
                'productos.procesos',
                'productoReferencia.procesos',
                'distribucionesPorTallas',
                'costosMateriales',
            ])
            ->whereIn('id', $opIds)
            ->where(function ($consulta) use ($cerradaId) {
                $consulta->whereNull('estado_op_id')
                    ->orWhere('estado_op_id', '!=', $cerradaId);
            })
            ->get();

        foreach ($ops as $op) {
            $this->recalcular($op);
        }

        if ($ops->isNotEmpty()) {
            // El listado de OPs se sirve en caché: sin esto el nuevo precio no
            // se vería hasta que otra escritura invalide la versión.
            Cache::increment('op_index_version');
            Cache::forget('op_selects');
        }

        return $ops->count();
    }
}
