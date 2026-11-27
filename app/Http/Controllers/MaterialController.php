<?php

namespace App\Http\Controllers;

use App\Models\FichaTecnica;
use App\Traits\ExcelDesdeDriveTrait;
use App\Models\Compromisomaterial;
use App\Models\KitProceso;
use App\Models\Material;
use App\Models\MaterialMovimiento;
use App\Models\OrdenProduccion;
use App\Models\Parametro;
use App\Models\Proveedor;
use App\Http\Requests\DestroyBulkMaterialRequest;
use App\Services\FichaTecnicaService;
use App\Services\ListaDesplegableService;
use App\Services\CostoMaterialOpService;
use App\Services\ColaMaterialesOpService;
use App\Services\CompromisoMaterialService;
use App\Services\ExcelMaterialesService;
use App\Services\FaltantesOpService;
use App\Services\MaterialBulkDeleteService;
use App\Services\MaterialCompraPredictorService;
use App\Services\PlantillasExcelService;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Traits\MaterialMovimientoTrait;

class MaterialController extends Controller {
    use ExcelDesdeDriveTrait;
    use MaterialMovimientoTrait;

    public array $thisAtributos;
    public string $FromController = 'Material';

    /**
     * Cuántos materiales viajan en la primera respuesta y de cuántos en cuántos
     * los pide la vista después. La tabla filtra, ordena y pagina en el
     * navegador, así que mandar los ~1.100 de golpe convierte una visita en
     * megas de JSON: con esto la pantalla sale ya y el resto llega detrás.
     */
    public const PRIMERA_CARGA = 10;
    public const TAMANO_LOTE = 100;


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct(
        protected ListaDesplegableService $listaDesplegableService,
        protected CostoMaterialOpService $costosMateriales,
        protected FichaTecnicaService $fichaTecnicaService,
        protected MaterialBulkDeleteService $bulkDelete,
        protected MaterialCompraPredictorService $compraPredictor,
        protected PlantillasExcelService $plantillas,
        protected ExcelMaterialesService $excelMateriales,
        protected ColaMaterialesOpService $colaMateriales,
        protected CompromisoMaterialService $compromisos,
        protected FaltantesOpService $faltantes
    ) {
//        $this->middleware('permission:create Material', ['only' => ['create', 'store']]);
//        $this->middleware('permission:read Material', ['only' => ['index', 'show']]);
//        $this->middleware('permission:update Material', ['only' => ['edit', 'update']]);
//        $this->middleware('permission:delete Material', ['only' => ['destroy', 'destroyBulk']]);
        $this->thisAtributos = (new Material())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' Materials '));

        // La vista solo necesita un puñado de materiales para pintarse; el resto
        // lo pide por bloques a lote(), y los dos leen el mismo cache, así que el
        // trabajo pesado se hace una vez por versión y no en cada petición.
        $Materiales = $this->listadoCacheado();
        // Opciones de los selectores (crear/editar y ficha). Se calcula una sola
        // vez: recorre los materiales completos con sus variantes y precios.
        $opcionesFicha = $this->fichaTecnicaService->opcionesMateriales();

        $listasDesplegables = $this->listaDesplegableService->getOptionsByCategoriaGrouped('material');
        // La talla de un material se elige entre las opciones de talla de Producto.
        $opcionesProducto = $this->listaDesplegableService->getOptionsByCategoriaGrouped('producto');
        $proveedores = Proveedor::all(['id', 'nombre'])
            ->map(function ($proveedor) {
                return [
                    'id'     => $proveedor->id,
                    'value'  => $proveedor->id,
                    'nombre' => $proveedor->nombre,
                    'label'  => $proveedor->nombre,
                    'name'   => $proveedor->nombre,
                    'title'  => $proveedor->nombre,
                ];
            });

        $materialesEnOp = $this->materialesEnOrden();
        $ordenesAbiertas = $this->ordenesAbiertas();
        // Solo se proyectan los materiales que participan en alguna OP (o que
        // aún tienen una reserva vigente); el resto equivale a su existencia.
        // Los genéricos quedan fuera: no almacenan inventario, su familia es la
        // que tiene existencia, compromisos y consumo.
        $materialesProyectables = array_values(array_unique(array_merge(
            $materialesEnOp,
            Compromisomaterial::query()
                ->activos()
                ->distinct()
                ->pluck('material_id')
                ->map(fn ($id) => (int) $id)
                ->all()
        )));
        $materialesProyectables = Material::query()
            ->whereIn('id', $materialesProyectables)
            ->where('generico', false)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return Inertia::render($this->FromController . '/Index', [
            'materiales' => $Materiales->take(self::PRIMERA_CARGA)->values(),
            'total' => $Materiales->count(),
            // El frontend arma su array con esto: `inicial` ya vino y `lote` es
            // de cuántos en cuántos pide lo que falta.
            'carga' => ['inicial' => self::PRIMERA_CARGA, 'lote' => self::TAMANO_LOTE],
            'prevision' => $this->prevision(),
            // Filtro por OP del listado: las abiertas y, por material, en cuáles.
            'ordenesAbiertas' => $ordenesAbiertas['ordenes'],
            'opPorMaterial' => $ordenesAbiertas['materiales'],
            // Inventario proyectado: físico + producción pendiente − compromisos.
            'proyeccionMateriales' => $this->compromisos->proyeccionPorMaterial($materialesProyectables),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'perPage' => 10,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => [
                'unidad_inventario'         => $listasDesplegables['unidad_inventario'] ?? [],
                'categoria'                 => $listasDesplegables['categoria'] ?? [],
                'modelo_diseno_acabado'     => $listasDesplegables['modelo_diseno_acabado'] ?? $listasDesplegables['variante_atributo_sugerido'] ?? $listasDesplegables['variante'] ?? [],
                'variante_atributo_sugerido' => $listasDesplegables['modelo_diseno_acabado'] ?? $listasDesplegables['variante_atributo_sugerido'] ?? $listasDesplegables['variante'] ?? [],
                // Color y talla son de selección única: cada uno sale de su lista.
                'color'                     => $listasDesplegables['color'] ?? [],
                'talla_tamano'              => $opcionesProducto['opciones'] ?? [],
                'proveedor_habitual'        => $proveedores,
                'materiales'                => $opcionesFicha['materiales'],
                'unidad_medida'             => $opcionesFicha['unidad_medida'],
            ],
	        'titulos'           => Material::getFillableWithTypes(),
            // La ayuda del modal de subida sale del mismo formato descargable.
            'columnasExcel'     => $this->plantillas->columnas('material'),

        ]);
    }

    /**
     * Listado completo del Index con los campos derivados que lee la tabla.
     * Vive en cache una vez por versión y de aquí salen tanto la primera
     * pantalla como los bloques de lote, así que el trabajo pesado (relaciones,
     * fichas y predicciones) no se repite en cada petición.
     *
     * @return Collection<int, Material>
     */
    private function listadoCacheado(): Collection
    {
        $claveListado = 'material_index_' . $this->versionCache();

        return Cache::remember($claveListado, now()->addMinutes(10), function () {
            $bloqueosFicha = $this->fichaTecnicaService->bloqueosPorMaterial();
            $prediccionesCompras = $this->compraPredictor->obtenerPrediccionesParaMateriales();
            // El nombre del proveedor se resuelve aquí en vez de cargar la
            // relación: serializada ocupa la clave `proveedor_habitual`, que el
            // listado usa como identificador para filtrar.
            $nombresProveedor = Proveedor::query()->pluck('nombre', 'id');

            return Material::query()
                ->with(['prices', 'variants', 'fichaTecnica.materiales.material'])
                // Sin el id de desempate, dos materiales tocados el mismo segundo
                // pueden cambiar de lugar entre la primera pantalla y los bloques.
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->each(function (Material $material) use ($bloqueosFicha, $prediccionesCompras, $nombresProveedor) {
                    $material->proveedor_nombre = $material->proveedor_habitual
                        ? ($nombresProveedor[$material->proveedor_habitual] ?? null)
                        : null;

                    // La relación ya viene ordenada por fecha, así que el último
                    // precio y los tres recientes se leen de ella; consultarla
                    // suelta costaba dos consultas por cada material.
                    $precios = $material->prices;
                    $material->last_purchase_price = $precios->first()?->precio ?? null;
                    $material->last_purchase_date = $precios->first()?->fecha ?? null;
                    // Gasto diario (unidades por día, persistido en BD)
                    $material->gasto_diario = $material->gasto_diario ?? null;
                    $material->gasto_diario_actualizado_en = $material->gasto_diario_actualizado_en ?? null;
                    $material->recent_prices = $precios->take(3)->map(fn ($price) => [
                        'precio' => $price->precio,
                        'fecha'  => $price->fecha,
                    ])->values()->all();

                    $ficha = $material->fichaTecnica->first();
                    $material->ficha_id = $ficha?->id;
                    $material->ficha_nombre = $ficha?->nombre;
                    $material->ficha_materiales = ($ficha?->materiales ?? collect())
                        ->map(fn ($fila) => [
                            'material_componente_id' => $fila->material_componente_id,
                            'consumo_estandar' => $fila->consumo_estandar,
                            'unidad_medida' => $fila->unidad_medida,
                            'costo_asociado_teorico' => $fila->costo_asociado_teorico,
                        ])
                        ->values()
                        ->all();
                    $bloqueos = $bloqueosFicha[$material->id] ?? [];
                    $material->ficha_bloqueada = !empty($bloqueos);
                    $material->ficha_bloqueo_detalle = collect($bloqueos)
                        ->map(fn ($estado, $numeroOp) => $numeroOp . ' (' . $estado . ')')
                        ->implode(', ');

                    $pred = $prediccionesCompras[$material->id] ?? null;
                    $material->ultima_compra_cantidad = $pred['cantidad'] ?? null;
                    $material->ultima_compra_fecha = $pred['fecha'] ?? null;
                    $material->ultima_compra_fecha_humana = $pred['fecha_humana'] ?? null;

                    // Ya no hacen falta en el JSON: la tabla lee los campos
                    // derivados de arriba. `variants` sí se queda (sus chips).
                    // Se ocultan con el nombre de la relación, no con la clave
                    // snake que sale en el JSON.
                    $material->makeHidden(['prices', 'fichaTecnica']);
                })
                ->values();
        });
    }

    /**
     * Bloque de materiales que la vista se trae en segundo plano. El corte es
     * sobre el mismo array cacheado de la primera pantalla, así que los bloques
     * nunca se pisan entre sí y al navegador solo le queda appendear.
     */
    public function lote(Request $request): JsonResponse
    {
        $desde = max(0, (int) $request->input('desde', 0));
        $limite = min(self::TAMANO_LOTE, max(1, (int) $request->input('limite', self::TAMANO_LOTE)));

        $Materiales = $this->listadoCacheado();

        return response()->json([
            'materiales' => $Materiales->slice($desde, $limite)->values(),
            'total' => $Materiales->count(),
        ]);
    }

    /**
     * Versión del cache del listado: al incrementarse, todas las claves
     * anteriores dejan de usarse y se reconstruyen con datos frescos. Las claves
     * viven en ExcelMaterialesService porque la carga por Excel en segundo
     * plano también las invalida, y no puede haber dos listas para el mismo
     * listado.
     */
    private function versionCache(): int {
        return $this->excelMateriales->versionCache();
    }

    private function invalidarCache(): void {
        $this->excelMateriales->invalidarCacheListados();
    }

    /**
     * Previsión de existencias: muestra los materiales que no alcanzan los días
     * configurados, en el orden en que se agotan.
     *
     * @return array{dias_prevision: int, ventana_dias: int, faltan: list<array<string, mixed>>}
     */
    private function prevision(): array {
        $diasMinimos = Parametro::entero(Parametro::DIAS_PREVISION_STOCK, 2);
        $ventana = 30;
        $desde = now()->subDays($ventana);

        $consumoTotal = MaterialMovimiento::query()
            ->where('tipo_movimiento', 'salida')
            ->where('motivo', 'like', 'Salida de materiales a taller%')
            ->where('created_at', '>=', $desde)
            ->groupBy('material_id')
            ->selectRaw('material_id, SUM(cantidad) AS total')
            ->pluck('total', 'material_id');

        $faltan = Material::query()
            ->where('estado', 'Activo')
            // Un genérico no tiene existencia ni consumo: la previsión es de los
            // materiales concretos que realmente se compran y se descuentan.
            ->where('generico', false)
            ->get(['id', 'codigo_material', 'nombre_base', 'nombre_material', 'existencia_actual', 'unidad_inventario'])
            ->map(function (Material $material) use ($consumoTotal, $ventana) {
                $total = round((float) ($consumoTotal[$material->id] ?? 0), 2);
                if ($total <= 0) {
                    return null;
                }

                $gastoDia = round($total / $ventana, 2);
                if ($gastoDia <= 0) {
                    return null;
                }

                $existencia = round((float) $material->existencia_actual, 2);

                return [
                    'material_id' => $material->id,
                    'codigo' => $material->codigo_material,
                    'nombre' => $material->nombre_mostrar,
                    'unidad' => $material->unidad_inventario,
                    'gasto_dia' => $gastoDia,
                    'total_ventana' => $total,
                    'existencia' => $existencia,
                    'dias_restantes' => (int) floor($existencia / $gastoDia),
                ];
            })
            ->filter()
            ->sortBy('dias_restantes')
            ->take(12)
            ->values()
            ->all();

        $ordenesPorMaterial = $this->ordenesQueProducenMateriales(array_column($faltan, 'material_id'));
        $faltan = array_map(function (array $material) use ($ordenesPorMaterial): array {
            $material['ordenes_resultado'] = $ordenesPorMaterial[$material['material_id']] ?? [];

            return $material;
        }, $faltan);

        return [
            'dias_prevision' => $diasMinimos,
            'ventana_dias' => $ventana,
            'faltan' => $faltan,
        ];
    }

    /**
     * OPs activas que tienen un material procesado como resultado. Se excluyen
     * los borradores y las OPs ya cerradas: en estos últimos casos el material
     * ya fue recibido o la orden todavía no está encargada.
     *
     * @param  list<int>  $materialIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function ordenesQueProducenMateriales(array $materialIds): array {
        if (empty($materialIds)) {
            return [];
        }

        $estadosExcluidos = ['Borrador', 'Cerrada', 'Terminada'];

        return OrdenProduccion::query()
            ->with([
                'estadoOp:id,nombre',
                'materiales' => fn ($query) => $query
                    ->whereIn('materials.id', $materialIds)
                    ->where('materials.material_procesado', true),
            ])
            ->whereHas('materiales', fn ($query) => $query
                ->whereIn('materials.id', $materialIds)
                ->where('materials.material_procesado', true))
            ->whereHas('estadoOp', fn ($query) => $query->whereNotIn('nombre', $estadosExcluidos))
            ->get(['id', 'numero_op'])
            ->flatMap(function (OrdenProduccion $op) use ($materialIds) {
                return $op->materiales
                    ->whereIn('id', $materialIds)
                    ->map(fn (Material $material): array => [
                        'material_id' => $material->id,
                        'numero_op' => $op->numero_op ?: ('OP #' . $op->id),
                        'estado' => $op->estadoOp?->nombre,
                        'cantidad' => round((float) ($material->pivot->cantidad ?? 0), 4),
                    ]);
            })
            ->groupBy('material_id')
            ->map(fn ($ordenes): array => $ordenes->values()->all())
            ->all();
    }

    /**
     * Materiales que participan en alguna orden de producción: por el kit de un
     * proceso, por el detalle de materiales de la OP o como origen de un
     * proceso externo. Solo se usan para saber qué materiales conviene proyectar.
     *
     * @return list<int>
     */
    private function materialesEnOrden(): array {
        $ids = KitProceso::query()->distinct()->pluck('material_id')
            ->merge(DB::table('orden_produccion_material')->distinct()->pluck('material_id'))
            ->merge(DB::table('proceso_externos')->distinct()->pluck('material_origen_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        sort($ids);

        return $ids;
    }

    /**
     * Las órdenes de producción que todavía no se cerraron y los materiales que
     * cada una exige, por los mismos tres caminos de materialesEnOrden().
     * Alimenta el filtro por OP del listado: saber que un material «está en una
     * OP» dice poco, lo útil es elegir en cuál.
     *
     * @return array{ordenes: list<array<string, mixed>>, materiales: array<string, list<int>>}
     */
    private function ordenesAbiertas(): array {
        $ordenes = OrdenProduccion::query()
            ->with([
                'estadoOp:id,nombre',
                'productoReferencia:id,Titulo_Shopify,Codigo_interno',
            ])
            ->get(['id', 'numero_op', 'producto_referencia_id', 'estado_op_id'])
            ->reject(fn (OrdenProduccion $op) => $op->estadoOp?->nombre === 'Cerrada')
            ->keyBy('id');

        if ($ordenes->isEmpty()) {
            return ['ordenes' => [], 'materiales' => []];
        }

        $pares = collect([
            KitProceso::query()
                ->whereIn('orden_produccion_id', $ordenes->keys())
                ->get(['orden_produccion_id', 'material_id']),
            DB::table('orden_produccion_material')
                ->whereIn('orden_produccion_id', $ordenes->keys())
                ->get(['orden_produccion_id', 'material_id']),
            DB::table('proceso_externos')
                ->whereIn('orden_produccion_id', $ordenes->keys())
                ->get(['orden_produccion_id', 'material_origen_id as material_id']),
        ])
            ->flatten()
            ->filter(fn ($par) => !is_null($par->material_id));

        // Sets por material y por OP: un material puede servir a varias OP y la
        // lista no puede repetir filas (kit y detalle suelen coincidir).
        $porMaterial = [];
        $porOp = [];
        foreach ($pares as $par) {
            $porMaterial[(int) $par->material_id][(int) $par->orden_produccion_id] = true;
            $porOp[(int) $par->orden_produccion_id][(int) $par->material_id] = true;
        }

        ksort($porMaterial);
        $porMaterial = array_map(function (array $ops): array {
            $ids = array_keys($ops);
            sort($ids);

            return $ids;
        }, $porMaterial);

        $ordenes = $ordenes->map(fn (OrdenProduccion $op): array => [
            'id' => (int) $op->id,
            'numero_op' => $op->numero_op ?: ('OP #' . $op->id),
            'estado' => $op->estadoOp?->nombre,
            'producto' => $op->productoReferencia?->Titulo_Shopify
                ?: $op->productoReferencia?->Codigo_interno,
            'materiales' => count($porOp[(int) $op->id] ?? []),
        ])
            ->sortBy(fn (array $op): int => (int) preg_replace('/\D/', '', (string) $op['numero_op']))
            ->values()
            ->all();

        return ['ordenes' => $ordenes, 'materiales' => $porMaterial];
    }

	public function losSelect(string $modelClass, string $label, string $displayField = 'nombre'): array {
		// Verifica si la clase del modelo existe
		if (!class_exists($modelClass)) {
			return []; // O podrías lanzar una excepción más informativa
		}
		
		// Intenta obtener todos los registros del modelo
		$modelCollection = call_user_func([$modelClass, 'all']);
		
		// Verifica si el resultado es una colección
		if (!$modelCollection instanceof Collection) {
			return []; // O podrías lanzar una excepción
		}
		
		
		return [
			$label => Myhelp::NEW_turnInSelectID($modelCollection, $label.' ', $displayField),
		];
	}
    //</editor-fold>

    /**
     * Registra la compra de uno o varios materiales desde la vista de
     * Materiales. La unidad no se recibe: se toma la del material.
     * El precio unitario se guarda como un cambio de precio del material
     * (material_prices), que es de donde salen los costos.
     */
    public function registrarCompra(Request $request): RedirectResponse {
        Myhelp::EscribirEnLog($this, ' Begin STORE:MaterialCompra');

        $datos = $request->validate([
            'materiales' => ['required', 'array', 'min:1'],
            'materiales.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'materiales.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'materiales.*.precio' => ['required', 'numeric', 'min:0.01'],
        ]);

        $compras = collect($datos['materiales'])
            ->filter(fn ($fila) => !empty($fila['material_id']) && (float) ($fila['cantidad'] ?? 0) > 0)
            ->groupBy(fn ($fila) => (int) $fila['material_id'])
            ->map(fn ($filas, $materialId) => [
                'material_id' => (int) $materialId,
                'cantidad' => round($filas->sum(fn ($fila) => (float) $fila['cantidad']), 2),
                // Si el mismo material se repite, manda el precio de la última fila.
                'precio' => round((float) $filas->last()['precio'], 2),
            ])
            ->sortBy('material_id')
            ->values()
            ->all();

        if (empty($compras)) {
            return back()->withErrors([
                'materiales' => 'Agrega al menos un material con cantidad.',
            ]);
        }

        // La base no guarda inventario: comprarle a ella dejaría la existencia en
        // cero y el consumo repartido. La compra es de uno de sus hijos.
        $genericos = Material::query()
            ->whereIn('id', array_column($compras, 'material_id'))
            ->where('generico', true)
            ->pluck('codigo_material')
            ->all();

        if (!empty($genericos)) {
            return back()->withErrors([
                'materiales' => 'Las bases no reciben compra: ' . implode(', ', $genericos)
                    . '. Compre el material concreto (con color o talla) que cuelga de esa base.',
            ]);
        }

        DB::beginTransaction();
        try {
            $registradas = 0;

            foreach ($compras as $compra) {
                $material = Material::whereKey($compra['material_id'])->lockForUpdate()->first();
                if (!$material) {
                    continue;
                }

                $cantidad = round((float) $compra['cantidad'], 2);
                if ($cantidad <= 0) {
                    continue;
                }

                $existenciaAnterior = round((float) ($material->existencia_actual ?? 0), 2);
                $existenciaNueva = round($existenciaAnterior + $cantidad, 2);

                $material->update(['existencia_actual' => $existenciaNueva]);
                $this->registrarMovimientoExistencia(
                    $material,
                    $existenciaAnterior,
                    $existenciaNueva,
                    'entrada',
                    'Compra de material'
                );

                // El precio de esta compra queda como cambio de precio del
                // material: alimenta el costo unitario y el promedio.
                $material->prices()->create([
                    'precio' => round((float) $compra['precio'], 2),
                    'fecha' => now()->toDateString(),
                ]);

                $registradas++;
            }

            // La compra puede completar el kit de las OP que esperan materiales:
            // salen de la cola en orden de llegada, reservando el stock entre ellas.
            $promovidas = $this->colaMateriales->promoverCola();

            DB::commit();
            $this->invalidarCache();
            Myhelp::EscribirEnLog($this, 'STORE:MaterialCompra EXITOSO', $registradas . ' material(es) actualizados | ' . count($promovidas) . ' OP promovidas a Lista para taller', false);

            $mensaje = 'Se registraron ' . $registradas . ' compra(s) de material. Las entradas y los precios quedaron en el histórico.';
            if (!empty($promovidas)) {
                $mensaje .= ' ' . count($promovidas) . ' orden(es) de producción pasaron a «Lista para taller»: '
                    . implode(', ', array_column($promovidas, 'numero_op')) . '.';
            }

            return back()->with('success', $mensaje);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function predecirCompra(int $id): JsonResponse
    {
        return response()->json($this->compraPredictor->predecirParaMaterial($id));
    }

    /**
     * Orden de compra: cuánto hace falta comprar de cada material para cubrir
     * las OPs que aún no entran a taller, con el precio vigente a la vista por
     * si hay que actualizarlo. Aquí no se registra ninguna compra.
     */
    public function ordenCompra(): Response {
        return Inertia::render('Material/OrdenCompra', [
            'orden' => $this->faltantes->ordenCompra(),
            'breadcrumbs' => [
                ['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')],
                ['label' => 'Orden de compra'],
            ],
            'title' => 'Orden de compra',
        ]);
    }

    /**
     * La misma orden en versión imprimible: sin barra lateral ni chrome de la
     * aplicación, agrupada por categoría y con casilleros para marcar en el
     * local. El precio solo aparece si quien imprime lo pide.
     */
    public function ordenCompraImprimible(): Response {
        return Inertia::render('Material/OrdenCompraImprimible', [
            'orden' => $this->faltantes->ordenCompra(),
        ]);
    }

    /**
     * Los precios escritos en la orden de compra. Cada precio entra como una
     * fila nueva de histórico con la fecha de hoy: el anterior se conserva.
     */
    public function guardarPreciosOrdenCompra(Request $request): RedirectResponse {
        Myhelp::EscribirEnLog($this, ' Begin STORE:MaterialPrecioOrdenCompra');

        $datos = $request->validate([
            'materiales' => ['required', 'array', 'min:1'],
            'materiales.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'materiales.*.precio' => ['required', 'numeric', 'min:0.01'],
        ]);

        $filas = collect($datos['materiales'])
            ->filter(fn ($fila) => (float) ($fila['precio'] ?? 0) > 0)
            ->unique(fn ($fila) => (int) $fila['material_id'])
            ->values();

        if ($filas->isEmpty()) {
            return back()->withErrors(['materiales' => 'Escribe un precio mayor que cero en al menos un material.']);
        }

        DB::beginTransaction();
        try {
            $hoy = now()->toDateString();
            $actualizados = 0;

            foreach ($filas as $fila) {
                $material = Material::whereKey((int) $fila['material_id'])->first();
                if (!$material) {
                    continue;
                }

                $precio = round((float) $fila['precio'], 2);

                // Un precio igual al vigente no es un cambio: no llena el histórico.
                if (abs($precio - $material->costoUnitarioActual()) < 0.005) {
                    continue;
                }

                $material->prices()->create(['precio' => $precio, 'fecha' => $hoy]);
                $actualizados++;
            }

            DB::commit();
            $this->invalidarCache();
            Myhelp::EscribirEnLog($this, 'STORE:MaterialPrecioOrdenCompra EXITOSO', $actualizados . ' precio(s) de material actualizados', false);

            if ($actualizados === 0) {
                return back()->with('info', 'Ningún precio cambió: todos coincidían con el vigente.');
            }

            return back()->with('success', 'Se actualizaron ' . $actualizados . ' precio(s) de material con la fecha de hoy. '
                . 'El total de la orden de compra quedó recalculado con esos precios.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:Materials');
        DB::beginTransaction();
        try {
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);

        // El formulario pregunta el rol del material: base (genérico), hijo de una
        // base o huérfano (sin base arriba). El precio de última compra es
        // obligatorio salvo en las bases y, si el material se marca como
        // procesado, se validan las líneas de su ficha técnica.
        $rol = $this->rolDelFormulario($request);
        $esBase = $rol === 'base';
        $precioObligatorio = $esBase ? 'nullable' : 'required';
        $request->validate([
            'last_purchase_price' => [$precioObligatorio, 'numeric', 'min:0'],
            'last_purchase_date' => [$precioObligatorio, 'date'],
            'codigo_categoria' => 'nullable|string|regex:/^[\p{L} ]*$/u',
            'codigo_base' => 'nullable|string|regex:/^[\p{L} ]*$/u',
            'material_base_id' => [$rol === 'hijo' ? 'required' : 'nullable', 'integer', 'exists:materials,id'],
            'materiales' => 'nullable|array',
            'materiales.*.material_componente_id' => 'nullable|integer|exists:materials,id',
            'materiales.*.consumo_estandar' => 'nullable|numeric|min:0',
            'materiales.*.unidad_medida' => 'nullable|string|max:255',
            'materiales.*.costo_asociado_teorico' => 'nullable|numeric|min:0',
            'colores' => 'nullable',
            'variantes' => 'nullable',
        ]);

        $datos = $request->except([
            'materiales',
            'last_purchase_price',
            'last_purchase_date',
            'colores',
            'variantes',
            'codigo_material',
            'rol',
            'material_base_id',
        ]);
        $datos['proveedor_habitual'] = $this->normalizarProveedorHabitual($datos['proveedor_habitual'] ?? null);
        $datos['generico'] = $esBase;

        $materiales = [];
        $base = null;

        if ($esBase) {
            $materiales[] = Material::create($datos);
        } elseif ($rol === 'hijo') {
            $base = $this->baseElegida((int) $request->input('material_base_id'));

            // La ficha vive en la base y el inventario en los hijos: un hijo no
            // puede declararse procesado ni llevar líneas de ficha propias.
            if ((int) $request->input('material_procesado') === 1 || !empty($request->input('materiales'))) {
                throw ValidationException::withMessages([
                    'material_procesado' => 'La ficha técnica se anota en la base ' . $base->codigo_material . ', no en sus hijos.',
                ]);
            }

            $materiales = $this->crearHijosDeBase($base, $datos, $request);
        } else {
            $this->rechazarVariantesMultiples($datos);
            $huerfano = Material::create($datos);
            $huerfano->sincronizarVariantes(
                Material::normalizarListaVariantes($datos['color'] ?? ''),
                Material::normalizarListaVariantes($datos['talla_tamano'] ?? ''),
            );
            $materiales[] = $huerfano;
        }

        foreach ($materiales as $material) {
            $this->registrarMovimientoCreacion($material);

            // El genérico no recibe precio del formulario: su precio lo aporta el
            // promedio de sus similares.
            if (!$esBase) {
                $material->prices()->create([
                    'precio' => $request->input('last_purchase_price'),
                    'fecha' => $request->input('last_purchase_date'),
                ]);
            }
        }

        $Material = $materiales[0];

        if (!$esBase && $rol !== 'hijo' && $Material->material_procesado) {
            $lineas = $this->lineasFichaDesdeRequest($request);
            if (!empty($lineas)) {
                $this->fichaTecnicaService->guardar([
                    'nombre' => $this->nombreFichaMaterial($Material),
                    'materiales_procesados' => [(int) $Material->id],
                    'materiales' => $lineas,
                ]);
            }
        }

        DB::commit();
        $this->invalidarCache();
        Myhelp::EscribirEnLog($this, 'STORE:Materials EXITOSO', 'Material id:' . $Material->id . ' | ' . $Material->nombre_mostrar, false);

        return back()->with('success', __('app.label.created_successfully', [
            'name' => count($materiales) > 1
                ? count($materiales) . ' materiales hijos de ' . $base->codigo_material
                : $Material->nombre_mostrar,
        ]));
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * El rol que pidió el formulario: base, hijo o huérfano. Sin el campo `rol`
     * manda la bandera `generico`, que era lo único que existía antes.
     */
    private function rolDelFormulario(Request $request): string {
        $rol = strtolower(trim((string) $request->input('rol', '')));

        return in_array($rol, ['base', 'hijo', 'huerfano'], true)
            ? $rol
            : ($request->boolean('generico') ? 'base' : 'huerfano');
    }

    /**
     * La base elegida para un hijo: tiene que ser un material marcado como base,
     * si no, el hijo quedaría huérfano igual (el vínculo es el prefijo del código).
     */
    private function baseElegida(int $baseId): Material {
        $base = Material::find($baseId);

        if (!$base || !$base->generico) {
            throw ValidationException::withMessages([
                'material_base_id' => 'El material base elegido no existe o no está marcado como base.',
            ]);
        }

        return $base;
    }

    /**
     * Un material por cada valor escrito en «Talla / tamaño» o en «Color»: S y M
     * son TEL-ALG-001-01 y TEL-ALG-001-02, no un material con dos tallas. Todos
     * cuelgan de la base y heredan su par de códigos, su categoría y su unidad; lo
     * único que cambia entre ellos es el valor que los identifica.
     *
     * @param  array<string, mixed>  $datos
     * @return list<Material>
     */
    private function crearHijosDeBase(Material $base, array $datos, Request $request): array {
        $valores = $this->valoresDeVariantes($request->input('talla_tamano'), $request->input('color'));

        $this->rechazarVariantesRepetidas($valores, $base);

        if (empty($valores)) {
            $valores = [['tipo' => null, 'nombre' => null]];
        }

        $materiales = [];

        foreach ($valores as $valor) {
            $hijo = Material::create($this->datosDelHijo($datos, $base, $valor, Material::siguienteCodigoHijo($base)));
            $hijo->sincronizarVariantes(
                $valor['tipo'] === 'color' ? [$valor['nombre']] : [],
                $valor['tipo'] === 'variante' ? [$valor['nombre']] : [],
            );

            $materiales[] = $hijo;
        }

        return $materiales;
    }

    /**
     * Los datos con los que nace un hijo: los del formulario, el par y la unidad
     * de su base y el valor que lo distingue. Empieza sin existencia: el stock se
     * le registra a él, no a la base.
     *
     * @param  array<string, mixed>  $datos
     * @param  array{tipo: string|null, nombre: string|null}  $valor
     * @return array<string, mixed>
     */
    private function datosDelHijo(array $datos, Material $base, array $valor, string $codigo): array {
        return array_merge($datos, $this->datosDesdeBase($base), [
            'codigo_material' => $codigo,
            'generico' => false,
            'material_procesado' => 0,
            'existencia_actual' => 0,
            'gasto_diario' => null,
            'color' => $valor['tipo'] === 'color' ? $valor['nombre'] : null,
            'talla_tamano' => $valor['tipo'] === 'variante' ? $valor['nombre'] : null,
            'maneja_color' => $valor['tipo'] === 'color',
            'maneja_talla' => $valor['tipo'] === 'variante',
            'modelo_diseno_acabado' => $base->modelo_diseno_acabado,
        ]);
    }

    /**
     * El par categoría/base, la categoría, el nombre base y la unidad vienen de la
     * base: un hijo no puede llamarse distinto que su familia.
     *
     * @return array<string, mixed>
     */
    private function datosDesdeBase(Material $base): array {
        return [
            'categoria' => $base->categoria,
            'codigo_categoria' => $base->codigo_categoria,
            'nombre_base' => $base->nombre_base,
            'codigo_base' => $base->codigo_base,
            'unidad_inventario' => $base->unidad_inventario,
        ];
    }

    /**
     * Cada talla es un material y cada color es otro: las dos listas van separadas
     * porque un hijo se distingue por un solo atributo.
     *
     * @return list<array{tipo: string, nombre: string}>
     */
    private function valoresDeVariantes(mixed $tallas, mixed $colores): array {
        $valores = array_map(
            fn (string $nombre): array => ['tipo' => 'variante', 'nombre' => $nombre],
            Material::normalizarListaVariantes($tallas)
        );

        return array_merge($valores, array_map(
            fn (string $nombre): array => ['tipo' => 'color', 'nombre' => $nombre],
            Material::normalizarListaVariantes($colores)
        ));
    }

    /**
     * Un huérfano es una sola presentación: su talla y su color no admiten lista,
     * porque no hay base que agrupe a sus hermanos. En un hijo tampoco se escribe
     * más de un valor: para sumar otra presentación existe «Agregar variantes»,
     * que crea un material por valor.
     *
     * @param  array<string, mixed>  $datos
     */
    private function rechazarVariantesMultiples(array $datos): void {
        $etiquetas = ['talla_tamano' => 'Talla / tamaño', 'color' => 'Color'];

        foreach ($etiquetas as $campo => $etiqueta) {
            if (count(Material::normalizarListaVariantes($datos[$campo] ?? '')) > 1) {
                throw ValidationException::withMessages([
                    $campo => 'Un material lleva un solo valor en ' . $etiqueta
                        . '. Para sumar otra presentación usa «Agregar variantes»: cada valor sale como material propio.',
                ]);
            }
        }
    }

    /**
     * Las variantes que ya tiene la familia, apuntadas al material que las lleva:
     * «variante|33» => TEL-ALG-001-02. Así el index no termina con dos hijos de la
     * misma base en la misma talla.
     *
     * @return array<string, string>
     */
    private function variantasTomadasDeLaBase(Material $base, ?int $exceptoId = null): array {
        $familia = Material::normalizarCodigo($base->codigo_material);

        return Material::query()
            ->where('codigo_material', 'like', $familia . '-%')
            ->when($exceptoId !== null, fn ($query) => $query->whereKeyNot($exceptoId))
            ->get(['codigo_material', 'color', 'talla_tamano'])
            ->reduce(function (array $tomadas, Material $hijo): array {
                foreach (['talla_tamano' => 'variante', 'color' => 'color'] as $columna => $tipo) {
                    foreach (Material::normalizarListaVariantes($hijo->$columna) as $nombre) {
                        $tomadas[$tipo . '|' . mb_strtolower($nombre)] ??= $hijo->codigo_material;
                    }
                }

                return $tomadas;
            }, []);
    }

    /**
     * Ninguna variante de la lista puede coincidir con la que ya tiene otro material
     * de la familia: el index terminaría con dos hijos de la misma base en la misma
     * talla. Los valores repetidos dentro de la celda los junta el modelo antes de
     * llegar acá.
     *
     * @param  list<array{tipo: string|null, nombre: string|null}>  $valores
     */
    private function rechazarVariantesRepetidas(array $valores, ?Material $base, ?int $exceptoId = null): void {
        if ($base === null) {
            return;
        }

        $etiquetas = ['variante' => 'La talla', 'color' => 'El color'];
        $campos = ['variante' => 'talla_tamano', 'color' => 'color'];
        $tomadas = $this->variantasTomadasDeLaBase($base, $exceptoId);

        foreach ($valores as $valor) {
            $tipo = $valor['tipo'] ?? null;
            $nombre = $valor['nombre'] ?? null;

            if (!isset($etiquetas[$tipo]) || $nombre === null || $nombre === '') {
                continue;
            }

            $dueno = $tomadas[$tipo . '|' . mb_strtolower($nombre)] ?? null;

            if ($dueno !== null) {
                throw ValidationException::withMessages([
                    $campos[$tipo] => $etiquetas[$tipo] . ' ' . $nombre . ' ya la tiene el material ' . $dueno
                        . '. Una variante de la familia va en un solo material.',
                ]);
            }
        }
    }

    /**
     * Hermanos nuevos creados desde «Editar material»: un material por cada valor
     * agregado, con los datos del material abierto y su propio color o talla.
     * Cuelgan de la misma base; si no tiene base, salen como otros huérfanos del
     * mismo par. Cada uno arranca sin existencia y con el precio vigente del
     * material editado, porque es el mismo artículo en otra presentación.
     *
     * @return list<Material>
     */
    private function crearHermanos(Material $Material, ?Material $base, Request $request): array {
        $valores = $this->valoresDeVariantes($request->input('nuevas_tallas'), $request->input('nuevos_colores'));

        // El material abierto ya gasta su variante, así que tampoco puede repetirla
        // en la lista de nuevas.
        $this->rechazarVariantesRepetidas($valores, $base ?? $Material->base());

        if (empty($valores)) {
            return [];
        }

        $ultimoPrecio = $Material->lastPrice()->first();
        $hermanos = [];

        foreach ($valores as $valor) {
            $datos = array_merge($Material->only($Material->getFillable()), [
                'codigo_material' => $base ? Material::siguienteCodigoHijo($base) : '',
                'generico' => false,
                'material_procesado' => 0,
                'existencia_actual' => 0,
                'gasto_diario' => null,
                'color' => $valor['tipo'] === 'color' ? $valor['nombre'] : null,
                'talla_tamano' => $valor['tipo'] === 'variante' ? $valor['nombre'] : null,
                'maneja_color' => $valor['tipo'] === 'color',
                'maneja_talla' => $valor['tipo'] === 'variante',
                'maneja_otra_variante' => false,
            ]);

            $hermano = Material::create($datos);
            $hermano->sincronizarVariantes(
                $valor['tipo'] === 'color' ? [$valor['nombre']] : [],
                $valor['tipo'] === 'variante' ? [$valor['nombre']] : [],
            );
            $this->registrarMovimientoCreacion($hermano);

            if ($ultimoPrecio) {
                $hermano->prices()->create([
                    'precio' => $ultimoPrecio->precio,
                    'fecha' => $ultimoPrecio->fecha,
                ]);
            }

            $hermanos[] = $hermano;
        }

        return $hermanos;
    }

    //! STORE - UPDATE - DELETE
    //! STORE functions

    public function create() {
    }

    //fin store functions

    public function show($id) {
    }

    public function edit($id) {
    }

    /**
     * Normaliza las líneas del editor de ficha que llegan del formulario.
     * Las filas vacías se ignoran.
     *
     * @return array<int, array{material_componente_id: int, consumo_estandar: float, unidad_medida: string|null, costo_asociado_teorico: float|null}>
     */
    private function lineasFichaDesdeRequest(Request $request): array
    {
        return collect($request->input('materiales', []))
            ->filter(fn ($fila) => is_array($fila) && !empty($fila['material_componente_id']))
            ->map(fn ($fila) => [
                'material_componente_id' => (int) $fila['material_componente_id'],
                'consumo_estandar' => (float) ($fila['consumo_estandar'] ?? 0),
                'unidad_medida' => $fila['unidad_medida'] ?? null,
                'costo_asociado_teorico' => isset($fila['costo_asociado_teorico'])
                    ? (float) $fila['costo_asociado_teorico']
                    : null,
            ])
            ->values()
            ->all();
    }

    private function nombreFichaMaterial(Material $material): string
    {
        $nombre = trim(($material->codigo_material ? $material->codigo_material . ' — ' : '') . $material->nombre_mostrar);

        return $nombre !== '' ? $nombre : 'Material #' . $material->id;
    }

    /**
     * Crea, actualiza o elimina la ficha del material según su marca de
     * procesado y las líneas enviadas por el formulario.
     */
    private function sincronizarFichaMaterial(Material $material, Request $request): void
    {
        $lineas = $this->lineasFichaDesdeRequest($request);
        $ficha = $material->fichaTecnica()->first();

        if (!$material->material_procesado || empty($lineas)) {
            if (!$ficha) {
                return;
            }

            $bloqueos = $this->fichaTecnicaService->operacionesQueBloqueanMaterial((int) $material->id);
            if (!empty($bloqueos)) {
                $detalle = collect($bloqueos)
                    ->map(fn ($estado, $numeroOp) => $numeroOp . ' (' . $estado . ')')
                    ->implode(', ');
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'materiales' => 'No se puede quitar la ficha del material: está en la OP ' . $detalle
                        . '. Solo se permite en Borrador, Preparación o Cerrada.',
                ]);
            }

            $ficha->materialesProcesados()->detach($material->id);
            $this->eliminarFichaSinObjetivos($ficha);
            $this->costosMateriales->recalcularPorMateriales([(int) $material->id]);

            return;
        }

        // Una OP activa bloquea la ficha. Si el usuario guarda el material sin
        // tocar la receta, se conserva tal cual en lugar de fallar.
        if ($ficha && $this->fichaTecnicaService->operacionesQueBloqueanMaterial((int) $material->id)) {
            $actuales = $ficha->materiales()
                ->get()
                ->map(fn ($fila) => [
                    'material_componente_id' => (int) $fila->material_componente_id,
                    'consumo_estandar' => round((float) $fila->consumo_estandar, 4),
                    'unidad_medida' => trim((string) $fila->unidad_medida),
                ])
                ->sortBy('material_componente_id')
                ->values()
                ->all();

            $nuevas = collect($lineas)
                ->map(fn ($fila) => [
                    'material_componente_id' => (int) $fila['material_componente_id'],
                    'consumo_estandar' => round((float) $fila['consumo_estandar'], 4),
                    'unidad_medida' => trim((string) ($fila['unidad_medida'] ?? '')),
                ])
                ->sortBy('material_componente_id')
                ->values()
                ->all();

            if ($actuales === $nuevas) {
                return;
            }
        }

        $this->fichaTecnicaService->guardar([
            'nombre' => $ficha?->nombre ?: $this->nombreFichaMaterial($material),
            'materiales_procesados' => [(int) $material->id],
            'materiales' => $lineas,
        ], $ficha);
    }

    private function eliminarFichaSinObjetivos(FichaTecnica $ficha): void
    {
        $ficha->loadCount(['productos', 'materialesProcesados']);

        if ((int) $ficha->productos_count > 0 || (int) $ficha->materiales_procesados_count > 0) {
            return;
        }

        $ficha->materiales()->delete();
        $ficha->delete();
    }

    /**
     * El frontend puede enviar el proveedor como objeto completo (select de Inertia)
     * o como id; la columna guarda solo el id.
     */
    private function normalizarProveedorHabitual(mixed $proveedor): ?int {
        if (is_array($proveedor)) {
            $proveedor = $proveedor['id'] ?? null;
        } elseif (is_object($proveedor)) {
            $proveedor = $proveedor->id ?? null;
        }
        if ($proveedor === null || $proveedor === '') {
            return null;
        }
        return is_numeric($proveedor) ? (int) $proveedor : null;
    }

    public function update(Request $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:Materials');
        DB::beginTransaction();
        try {

        // El rol del material (base, hijo de una base o huérfano) define lo que se
        // puede editar: un hijo hereda el par de su base y un huérfano no admite
        // listas de variantes.
        $rol = $this->rolDelFormulario($request);
        $esBase = $rol === 'base';
        $base = null;

        $request->validate([
            'codigo_categoria' => 'nullable|string|regex:/^[\p{L} ]*$/u',
            'codigo_base' => 'nullable|string|regex:/^[\p{L} ]*$/u',
            'material_base_id' => [$rol === 'hijo' ? 'required' : 'nullable', 'integer', 'exists:materials,id'],
            'materiales' => 'nullable|array',
            'materiales.*.material_componente_id' => 'nullable|integer|exists:materials,id',
            'materiales.*.consumo_estandar' => 'nullable|numeric|min:0',
            'materiales.*.unidad_medida' => 'nullable|string|max:255',
            'materiales.*.costo_asociado_teorico' => 'nullable|numeric|min:0',
            'colores' => 'nullable',
            'variantes' => 'nullable',
            'nuevas_tallas' => 'nullable',
            'nuevos_colores' => 'nullable',
        ]);

        if ($request->has('existencia_actual') && $request->input('existencia_actual') < 0) {
            DB::rollBack();
            return back()->with('error', 'La existencia no puede ser negativa.');
        }

        $Material = Material::findOrFail($id);
        $existenciaAnterior = (float) ($Material->existencia_actual ?? 0);
        $baseAnterior = $Material->base();
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $datos = $request->except([
            'codigo_material',
            'existencia_actual',
            'motivo',
            'alterar_existencia',
            'materiales',
            'last_purchase_price',
            'last_purchase_date',
            'colores',
            'variantes',
            'rol',
            'material_base_id',
            'nuevas_tallas',
            'nuevos_colores',
        ]);
        if (array_key_exists('proveedor_habitual', $datos)) {
            $datos['proveedor_habitual'] = $this->normalizarProveedorHabitual($datos['proveedor_habitual']);
        }
        if ($request->boolean('alterar_existencia') && $request->has('existencia_actual')) {
            $datos['existencia_actual'] = (float) $request->input('existencia_actual');
        }

        if ($esBase) {
            $datos['generico'] = true;
        } elseif ($rol === 'hijo') {
            $base = $this->baseElegida((int) $request->input('material_base_id'));
            $datos = array_merge($datos, $this->datosDesdeBase($base), [
                'generico' => false,
                // La ficha vive en la base: el hijo no se declara procesado, aunque
                // un registro antiguo lo tenga marcado.
                'material_procesado' => 0,
            ]);

            // El vínculo con la base es el prefijo del código: si cambia la base,
            // cambia el código. Las referencias del sistema van por id, así que la
            // OP que usa este material no se rompe.
            if (Material::familiaDeCodigo($Material->codigo_material) !== Material::normalizarCodigo($base->codigo_material)) {
                $datos['codigo_material'] = Material::siguienteCodigoHijo($base);
            }
        } else {
            $datos['generico'] = false;

            if ($baseAnterior) {
                // Deja de colgar de una base: el modelo le da el siguiente código
                // libre del par, que es lo que lo convierte en huérfano.
                $datos['codigo_material'] = '';
            }
        }

        // Ni hijo ni huérfano admiten listas en la ficha, y el valor que se escribe
        // no puede ser el de un hermano de la misma base: la talla y el color tienen
        // que quedar únicos en el listado.
        if (!$esBase) {
            $this->rechazarVariantesMultiples($datos);
            $this->rechazarVariantesRepetidas(
                $this->valoresDeVariantes($datos['talla_tamano'] ?? null, $datos['color'] ?? null),
                $base,
                (int) $Material->id,
            );
        }

        $Material->update($datos);

        // Las variantes se sincronizan cuando el formulario envía sus listas o sus
        // celdas de texto: así la tabla de variantes coincide con lo que muestra el
        // listado. Una edición rápida que no manda ninguna de las dos no las toca.
        if (!$esBase && (
            $request->has('colores') || $request->has('variantes')
            || $request->has('color') || $request->has('talla_tamano')
        )) {
            $Material->sincronizarVariantes(
                Material::normalizarListaVariantes($request->input('colores', $request->input('color', ''))),
                Material::normalizarListaVariantes($request->input('variantes', $request->input('talla_tamano', '')))
            );
        }

        $this->registrarMovimientoActualizacion($Material, $existenciaAnterior, $request->input('motivo'));

        // Hermanos nuevos: cada valor agregado en la edición crea su propio
        // material, que cuelga de la misma base y hereda los datos de este.
        $hermanos = $esBase ? [] : $this->crearHermanos($Material, $base, $request);

        // La ficha técnica solo se toca cuando el formulario envía sus líneas.
        // Así una edición rápida de existencia/precio no borra la receta.
        if ($request->has('materiales')) {
            $this->sincronizarFichaMaterial($Material, $request);
        }
        
        // El precio de última compra y su fecha NO se tocan aquí: solo los
        // define el flujo «Registrar compra» (registrarCompra).

        // El costo de las OPs abiertas que usan este material se recalcula con
        // el precio vigente tras la edición.
        $this->costosMateriales->recalcularPorMaterial((int) $Material->id);

        DB::commit();
        $this->invalidarCache();
        Myhelp::EscribirEnLog($this, 'UPDATE:Materials EXITOSO', 'Material id:' . $Material->id . ' | ' . $Material->nombre, false);

        $mensaje = __('app.label.updated_successfully2', ['nombre' => $Material->nombre]);

        if ($hermanos) {
            $mensaje .= ' Se agregaron ' . count($hermanos) . ' materiales de ' . $Material->nombre_base . ': '
                . implode(', ', array_map(fn (Material $hermano): string => $hermano->codigo_material, $hermanos)) . '.';
        }

        return back()->with('success', $mensaje);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($Materialid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:Materials');
        $Material = Material::find($Materialid);
        $elnombre = $Material->nombre;
        $Material->delete();
        $this->invalidarCache();
        Myhelp::EscribirEnLog($this, 'DELETE:Materials', 'Material id:' . $Material->id . ' | ' . $Material->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(DestroyBulkMaterialRequest $request): JsonResponse|RedirectResponse {
        $resultado = $this->bulkDelete->eliminarMasivo($request->ids(), $request->conDependientes());
        $this->invalidarCache();
        Myhelp::EscribirEnLog($this, 'DELETE:Materials', 'Borrado en masa: ' . $resultado['eliminados'] . ' materiales, ' . $resultado['ordenes_eliminadas'] . ' OPs, ' . $resultado['fichas_eliminadas'] . ' fichas', false);

        if ($request->wantsJson()) {
            return response()->json($resultado);
        }

        return back()->with('bulk_result', $resultado);
    }

    /** Dependencias (OPs y fichas técnicas) de los materiales marcados. */
    public function dependenciasBulk(DestroyBulkMaterialRequest $request): JsonResponse {
        return response()->json($this->bulkDelete->dependencias($request->ids()));
    }

    //FIN : STORE - UPDATE - DELETE

    // ─────────────────────────────────────────────────────────
    //  CARGA MASIVA DESDE EXCEL
    // ─────────────────────────────────────────────────────────
    /**
     * Plantilla descargable de materiales. La define PlantillasExcelService,
     * que es también de donde sale la tarjeta del Centro de Importación, para
     * que el formato y la ayuda nunca se contradigan.
     *
     * Las presentaciones van en "Color" o en "Talla / tamaño" (separadas por
     * coma) y alimentan la tabla material_variants; el orden en la lista es la
     * posición de la variante. "Unidad inventario" trae el nombre de la unidad
     * de medida (Unidades, Metros…), no la cantidad en stock.
     */
    public function descargarPlantillaExcel(): StreamedResponse
    {
        $writer = $this->plantillas->escribir('material');

        if ($writer === null) {
            abort(404, 'No hay plantilla de materiales.');
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $this->plantillas->nombreArchivo('material'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Carga masiva de materiales desde la hoja activa del Excel. Qué columnas
     * se leen y cómo se escriben en la BD lo decide ExcelMaterialesService, que
     * es el mismo servicio que consume la hoja "material" del archivo MPP.
     */
    public function uploadExcel(Request $request): RedirectResponse
    {
        Myhelp::EscribirEnLog($this, 'uploadExcel: inicio');

        // 1. Validaciones de seguridad del archivo (previas a cualquier lectura)
        $request->validate(self::reglasDeSubidaExcel(), self::mensajesDeSubidaExcel());

        $archivo = $this->excelDeLaSubida($request);

        // 2. Doble-verificación de extensión (defensa en profundidad)
        if (strtolower($archivo->getClientOriginalExtension()) !== 'xlsx') {
            return back()->with('error', 'Solo se permiten archivos con extensión .xlsx');
        }

        // 3. Verificación de firma de bytes (magic bytes del ZIP/OOXML: PK\x03\x04)
        $handle = fopen($archivo->getRealPath(), 'rb');
        $magic  = fread($handle, 4);
        fclose($handle);
        if ($magic !== "PK\x03\x04") {
            return back()->with('error', 'El archivo no tiene un formato XLSX válido.');
        }

        // 4. Leer la hoja activa: quién interpreta las columnas y escribe en la
        // BD es ExcelMaterialesService, el mismo servicio que usa la hoja
        // "material" del archivo MPP.
        try {
            $spreadsheet = IOFactory::load($archivo->getRealPath());
            $filas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable $e) {
            Myhelp::EscribirEnLog($this, 'uploadExcel ERROR lectura', $e->getMessage());
            return back()->with('error', 'No se pudo leer el archivo: ' . $e->getMessage());
        }

        try {
            $lectura = $this->excelMateriales->registrosDesdeFilas($filas);
            $resultado = $this->excelMateriales->importar($lectura['registros']);
        } catch (\Throwable $e) {
            Myhelp::EscribirEnLog($this, 'uploadExcel ERROR', $e->getMessage());
            return back()->with('error', $e->getMessage());
        }

        Myhelp::EscribirEnLog($this, 'uploadExcel OK', "Creados: {$resultado['creados']} | Actualizados: {$resultado['actualizados']}");

        $avisoIgnoradas = empty($lectura['ignoradas'])
            ? ''
            : ' Columnas ignoradas (encabezado no reconocido): ' . implode(', ', $lectura['ignoradas']) . '.';

        return back()->with(
            'success',
            "Importación completada: {$resultado['creados']} creado(s), {$resultado['actualizados']} actualizado(s).{$avisoIgnoradas}"
        );
    }

}
