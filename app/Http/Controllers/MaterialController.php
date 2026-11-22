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

        // Listado completo: la tabla filtra, ordena y pagina en el frontend.
        // La clave solo depende de la versión, que se incrementa al escribir.
        $claveListado = 'material_index_' . $this->versionCache();
        $Materiales = Cache::remember($claveListado, now()->addMinutes(10), function () {
            return Material::query()
                ->with(['proveedorHabitual', 'prices', 'variants', 'fichaTecnica.materiales.material'])
                ->orderByDesc('updated_at')
                ->get()
                ->map(function ($material) {
                    $material->proveedor_nombre = $material->proveedorHabitual?->nombre ?? null;
                    // Obtener último precio
                    $lastPrice = $material->prices()->first();
                    $material->last_purchase_price = $lastPrice?->precio ?? null;
                    $material->last_purchase_date = $lastPrice?->fecha ?? null;
                    // Gasto diario (unidades por día, persistido en BD)
                    $material->gasto_diario = $material->gasto_diario ?? null;
                    $material->gasto_diario_actualizado_en = $material->gasto_diario_actualizado_en ?? null;
                    // Obtener los últimos 3 precios
                    $material->recent_prices = $material->prices()->limit(3)->get()->map(function($price) {
                        return [
                            'precio' => $price->precio,
                            'fecha' => $price->fecha
                        ];
                    })->toArray();
                    return $material;
                })
                ->values();
        });

        $bloqueosFicha = $this->fichaTecnicaService->bloqueosPorMaterial();
        $prediccionesCompras = $this->compraPredictor->obtenerPrediccionesParaMateriales();
        $Materiales->each(function (Material $material) use ($bloqueosFicha, $prediccionesCompras) {
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
        });

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
            'materiales' => $Materiales,
            'total' => $Materiales->count(),
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
                'materiales'                => $this->fichaTecnicaService->opcionesMateriales()['materiales'],
                'unidad_medida'             => $this->fichaTecnicaService->opcionesMateriales()['unidad_medida'],
            ],
	        'titulos'           => Material::getFillableWithTypes(),
            // La ayuda del modal de subida sale del mismo formato descargable.
            'columnasExcel'     => $this->plantillas->columnas('material'),

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
            ->get(['id', 'codigo_material', 'nombre_base', 'existencia_actual', 'unidad_inventario'])
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
                    'nombre' => $material->nombre_base,
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

        // El genérico no guarda inventario: comprarle a él dejaría la existencia
        // en cero y el consumo repartido. La compra es del material concreto.
        $genericos = Material::query()
            ->whereIn('id', array_column($compras, 'material_id'))
            ->where('generico', true)
            ->pluck('codigo_material')
            ->all();

        if (!empty($genericos)) {
            return back()->withErrors([
                'materiales' => 'Los genéricos no reciben compra: ' . implode(', ', $genericos)
                    . '. Compre el material concreto (con color o talla) de esa familia.',
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
        
        // Validar precio de última compra (obligatorio salvo en genéricos) y, si
        // el material se marca como procesado, las líneas de su ficha técnica.
        // El código del material no se digita: lo arma el modelo con el par
        // codigo_categoria + codigo_base (TEX-LFI-4).
        $precioObligatorio = $request->boolean('generico') ? 'nullable' : 'required';
        $request->validate([
            'last_purchase_price' => [$precioObligatorio, 'numeric', 'min:0'],
            'last_purchase_date' => [$precioObligatorio, 'date'],
            'codigo_categoria' => 'nullable|string|regex:/^[\p{L} ]*$/u',
            'codigo_base' => 'nullable|string|regex:/^[\p{L} ]*$/u',
            'materiales' => 'nullable|array',
            'materiales.*.material_componente_id' => 'nullable|integer|exists:materials,id',
            'materiales.*.consumo_estandar' => 'nullable|numeric|min:0',
            'materiales.*.unidad_medida' => 'nullable|string|max:255',
            'materiales.*.costo_asociado_teorico' => 'nullable|numeric|min:0',
            'colores' => 'nullable',
            'variantes' => 'nullable',
        ]);

        $colores = Material::normalizarListaVariantes($request->input('colores', []));
        $variantes = Material::normalizarListaVariantes($request->input('variantes', []));

        $datos = $request->except(['materiales', 'last_purchase_price', 'last_purchase_date', 'colores', 'variantes', 'codigo_material']);
        $datos['proveedor_habitual'] = $this->normalizarProveedorHabitual($datos['proveedor_habitual'] ?? null);
        $Material = Material::create($datos);
        $Material->sincronizarVariantes($colores, $variantes);
        $this->registrarMovimientoCreacion($Material);
        
        // Guardar precio de última compra. El genérico no lo recibe del
        // formulario: su precio lo aporta el promedio de sus similares.
        if (!$Material->generico) {
            $price = $request->input('last_purchase_price');
            $date = $request->input('last_purchase_date');

            $Material->prices()->create([
                'precio' => $price,
                'fecha' => $date
            ]);
        }

        if ($Material->material_procesado) {
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
        Myhelp::EscribirEnLog($this, 'STORE:Materials EXITOSO', 'Material id:' . $Material->id . ' | ' . $Material->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $Material->nombre]));
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
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
        $nombre = trim(($material->codigo_material ? $material->codigo_material . ' — ' : '') . $material->nombre_base);

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

        $request->validate([
            'codigo_categoria' => 'nullable|string|regex:/^[\p{L} ]*$/u',
            'codigo_base' => 'nullable|string|regex:/^[\p{L} ]*$/u',
            'materiales' => 'nullable|array',
            'materiales.*.material_componente_id' => 'nullable|integer|exists:materials,id',
            'materiales.*.consumo_estandar' => 'nullable|numeric|min:0',
            'materiales.*.unidad_medida' => 'nullable|string|max:255',
            'materiales.*.costo_asociado_teorico' => 'nullable|numeric|min:0',
            'colores' => 'nullable',
            'variantes' => 'nullable',
        ]);

        if ($request->has('existencia_actual') && $request->input('existencia_actual') < 0) {
            DB::rollBack();
            return back()->with('error', 'La existencia no puede ser negativa.');
        }

        $Material = Material::findOrFail($id);
        $existenciaAnterior = (float) ($Material->existencia_actual ?? 0);
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
        ]);
        if (array_key_exists('proveedor_habitual', $datos)) {
            $datos['proveedor_habitual'] = $this->normalizarProveedorHabitual($datos['proveedor_habitual']);
        }
        if ($request->boolean('alterar_existencia') && $request->has('existencia_actual')) {
            $datos['existencia_actual'] = (float) $request->input('existencia_actual');
        }
        $Material->update($datos);

        // Las variantes se sincronizan cuando el formulario envía sus listas.
        // Así una edición rápida (existencia, precio) no las borra.
        if ($request->has('colores') || $request->has('variantes')) {
            $Material->sincronizarVariantes(
                Material::normalizarListaVariantes($request->input('colores', [])),
                Material::normalizarListaVariantes($request->input('variantes', []))
            );
        }

        $this->registrarMovimientoActualizacion($Material, $existenciaAnterior, $request->input('motivo'));

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
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $Material->nombre]));
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
