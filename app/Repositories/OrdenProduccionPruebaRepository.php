<?php

namespace App\Repositories;

use App\Models\Distribucion_por_tallas;
use App\Models\KitProceso;
use App\Models\OrdenProduccion;
use App\Models\OrdenProduccionMaterialConcreto;
use App\Models\Producto;
use App\Models\Tercero;
use App\Models\User;
use App\Services\CompromisoMaterialService;
use App\Services\CostoMaterialOpService;
use App\Services\MaterialConcretoOpService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrdenProduccionPruebaRepository
{
    /** Unidades que lleva cada OP de prueba cuando no hay tallas definidas. */
    private const CANTIDAD_PRUEBA = 10;

    public function __construct(
        private MaterialConcretoOpService $concretos,
        private CostoMaterialOpService $costos,
        private CompromisoMaterialService $compromisos,
    ) {}

    public function vaciarOrdenesProduccion(): void
    {
        Schema::disableForeignKeyConstraints();

        $tablasDependientes = [
            'cierre_orden_produccions',
            'compromisomaterials',
            'compromisos_reparacion',
            'compromisos_reparacions',
            'ficha_ensambles',
            'kit_procesos',
            'kit_tallers',
            'mermas',
            'orden_produccion_costos_cambios',
            'orden_produccion_costos_materiales',
            'orden_produccion_material',
            'orden_produccion_material_concreto',
            'orden_produccion_producto',
            'pendientes_produccions',
            'proceso_externos',
            'procesos_externos',
            'producto_movimientos',
            'recepcion_calidads',
            'distribucion_orden_produccion',
            'orden_produccions',
        ];

        foreach ($tablasDependientes as $tabla) {
            if (Schema::hasTable($tabla)) {
                DB::table($tabla)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Crea las OPs de prueba con todo lo que una OP real necesita: producto,
     * distribución por tallas, kit de materiales de su ficha técnica y costo
     * de proceso. Sin esas piezas la OP aparece vacía y no sirve para probar
     * el módulo.
     */
    public function generarOrdenes(int $estadoOpId, int $cantidad = 2): array
    {
        $productos = $this->productosDePrueba();
        $tallerId = Tercero::where('tipo', 'Taller')->value('id');
        $userId = User::value('id');

        return DB::transaction(function () use ($productos, $estadoOpId, $cantidad, $tallerId, $userId) {
            $creadas = [];

            for ($i = 0; $i < $cantidad; $i++) {
                $producto = $productos->isNotEmpty()
                    ? $productos[$i % $productos->count()]
                    : null;

                $op = OrdenProduccion::create([
                    'numero_op' => OrdenProduccion::siguienteNumeroOp(),
                    'producto_referencia_id' => $producto?->id,
                    'cantidad_planificada' => self::CANTIDAD_PRUEBA,
                    'costo_proceso' => 0,
                    'costo_total' => 0,
                    'fecha_prevista_envio_taller' => now()->toDateString(),
                    'taller_id' => $tallerId,
                    'tipo' => 'taller',
                    'empresa_externa_id' => null,
                    'id_shopify_producto' => $producto?->ID_producto_Shopify,
                    'estado_op_id' => $estadoOpId,
                    'user_id' => $userId,
                ]);

                if ($producto) {
                    $op->productos()->sync([$producto->id]);

                    $cantidadPlanificada = $this->crearDistribucionTallas($op, $producto);
                    $op->forceFill(['cantidad_planificada' => $cantidadPlanificada])->saveQuietly();

                    // Material concreto (color o talla) de cada línea genérica de
                    // la ficha, igual que lo elige el usuario en el formulario.
                    $concretos = $this->resolverConcretos($producto);
                    $this->concretos->guardar((int) $op->id, $concretos);

                    $this->crearKitProceso($op, $producto, $cantidadPlanificada, $concretos);
                }

                $this->calcularCostos($op);

                $creadas[] = $op->fresh() ?? $op;
            }

            return $creadas;
        });
    }

    /**
     * Productos que sirven para generar una OP completa. Se prefieren los que
     * tienen ficha técnica y procesos, que son los que permiten calcular
     * materiales y costo de proceso.
     *
     * @return Collection<int, Producto>
     */
    private function productosDePrueba(): Collection
    {
        $conRelaciones = fn () => Producto::query()->with([
            'fichaTecnica.materiales.material',
            'distribucionesPorTallas',
            'procesos',
        ]);

        $aptos = $conRelaciones()
            ->whereHas('fichaTecnica.materiales')
            ->whereHas('procesos')
            ->orderBy('id')
            ->get();

        if ($aptos->isNotEmpty()) {
            return $aptos;
        }

        $conFicha = $conRelaciones()
            ->whereHas('fichaTecnica.materiales')
            ->orderBy('id')
            ->get();

        if ($conFicha->isNotEmpty()) {
            return $conFicha;
        }

        $conProceso = $conRelaciones()
            ->whereHas('procesos')
            ->orderBy('id')
            ->get();

        if ($conProceso->isNotEmpty()) {
            return $conProceso;
        }

        return $conRelaciones()->orderBy('id')->limit(2)->get();
    }

    /**
     * Usa la distribución por tallas del producto; si el producto no tiene,
     * crea la talla única. Devuelve la cantidad total planificada de la OP.
     */
    private function crearDistribucionTallas(OrdenProduccion $op, Producto $producto): int
    {
        $distribuciones = $producto->distribucionesPorTallas
            ->filter(fn (Distribucion_por_tallas $distribucion) => filled($distribucion->talla))
            ->unique(fn (Distribucion_por_tallas $distribucion) => mb_strtolower(trim((string) $distribucion->talla)))
            ->values();

        if ($distribuciones->isEmpty()) {
            $distribucion = Distribucion_por_tallas::create([
                'producto_id' => $producto->id,
                'talla' => 'Única',
                'cantidad_planificada' => self::CANTIDAD_PRUEBA,
            ]);
            $distribucion->productos()->syncWithoutDetaching([$producto->id]);
            $op->distribucionesPorTallas()->sync([$distribucion->id]);

            return self::CANTIDAD_PRUEBA;
        }

        $totalActual = (int) $distribuciones->sum(fn (Distribucion_por_tallas $distribucion) => (int) $distribucion->cantidad_planificada);

        // Una plantilla sin cantidades se reparte como una OP real: 10 unidades
        // entre sus tallas.
        if ($totalActual <= 0) {
            $base = intdiv(self::CANTIDAD_PRUEBA, $distribuciones->count());
            $resto = self::CANTIDAD_PRUEBA % $distribuciones->count();

            foreach ($distribuciones as $indice => $distribucion) {
                $distribucion->update([
                    'cantidad_planificada' => $base + ($indice < $resto ? 1 : 0),
                ]);
            }
        }

        $op->distribucionesPorTallas()->sync($distribuciones->pluck('id')->all());

        return (int) $distribuciones->sum(fn (Distribucion_por_tallas $distribucion) => (int) $distribucion->cantidad_planificada);
    }

    /**
     * Elige el material concreto de cada línea genérica de la ficha. Se toma
     * el que tiene más existencia dentro de la familia del genérico.
     *
     * @return array<string, array<int, array<int, int>>>
     */
    private function resolverConcretos(Producto $producto): array
    {
        $ficha = $producto->fichaTecnica->first();

        if (! $ficha) {
            return [];
        }

        $familias = [];

        foreach ($ficha->materiales as $linea) {
            $material = $linea->material;
            if (! $material?->generico) {
                continue;
            }

            $familia = MaterialConcretoOpService::familiaDe($material);
            if ($familia !== '' && $familia !== '-') {
                $familias[$familia] = true;
            }
        }

        if (empty($familias)) {
            return [];
        }

        $opciones = $this->concretos->concretosDeFamilias(array_keys($familias));
        $mapa = [];

        foreach ($ficha->materiales as $linea) {
            $material = $linea->material;
            if (! $material?->generico) {
                continue;
            }

            $concreto = $opciones
                ->get(MaterialConcretoOpService::familiaDe($material))
                ?->sortByDesc('existencia_actual')
                ->first();

            if (! $concreto) {
                continue;
            }

            $mapa[OrdenProduccionMaterialConcreto::OBJETIVO_PRODUCTO][$producto->id][$material->id]
                = (int) $concreto->id;
        }

        return $mapa;
    }

    /**
     * Kit de materiales: una fila por componente de la ficha, con el consumo
     * estándar multiplicado por las unidades de la OP y la existencia actual
     * como disponible.
     *
     * @param  array<string, array<int, array<int, int>>>  $concretos
     */
    private function crearKitProceso(OrdenProduccion $op, Producto $producto, int $cantidad, array $concretos): void
    {
        $ficha = $producto->fichaTecnica->first();

        if (! $ficha) {
            return;
        }

        $materialesConcretos = $this->concretos->materialesDelMapa($concretos);
        $necesidad = [];

        foreach ($ficha->materiales as $linea) {
            $materialDeFicha = $linea->material;
            if (! $materialDeFicha) {
                continue;
            }

            $material = $this->concretos->materialResuelto(
                $concretos,
                $materialesConcretos,
                OrdenProduccionMaterialConcreto::OBJETIVO_PRODUCTO,
                (int) $producto->id,
                $materialDeFicha
            );

            $materialId = (int) $material->id;
            if (! isset($necesidad[$materialId])) {
                $necesidad[$materialId] = [
                    'unidad' => $linea->unidad_medida ?: ($material->unidad_inventario ?: 'u'),
                    'requerida' => 0.0,
                    'disponible' => (float) ($material->existencia_actual ?? 0),
                ];
            }

            $necesidad[$materialId]['requerida'] += (float) $linea->consumo_estandar * $cantidad;
        }

        foreach ($necesidad as $materialId => $fila) {
            KitProceso::create([
                'orden_produccion_id' => $op->id,
                'material_id' => $materialId,
                'proveedor_id' => null,
                'estado_requerido' => null,
                'unidad' => $fila['unidad'],
                'cantidad_requerida' => round($fila['requerida'], 4),
                'cantidad_disponible' => $fila['disponible'],
                'cantidad_preparada' => null,
            ]);
        }
    }

    /**
     * Guarda el costo de los materiales, el costo de proceso (derivado de los
     * procesos del producto) y deja la proyección de compromisos al día.
     */
    private function calcularCostos(OrdenProduccion $op): void
    {
        $recargada = $op->fresh([
            'productos.fichaTecnica.materiales',
            'productoReferencia.fichaTecnica.materiales',
            'materiales.fichaTecnica.materiales',
            'productos.procesos',
            'productoReferencia.procesos',
            'distribucionesPorTallas',
            'costosMateriales',
            'concretosMateriales',
            'estadoOp',
        ]);

        if (! $recargada) {
            return;
        }

        $this->costos->recalcular($recargada);
        $this->compromisos->sincronizar($recargada);
    }
}
