<?php

namespace App\Http\Controllers;

use App\Models\FichaTecnica;
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
use App\Services\ExcelMaterialesService;
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
        protected ExcelMaterialesService $excelMateriales
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

        return Inertia::render($this->FromController . '/Index', [
            'materiales' => $Materiales,
            'total' => $Materiales->count(),
            'prevision' => $this->prevision(),
            'faltantesOp' => $this->faltantesPorOp(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'perPage' => 10,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => [
                'unidad_inventario'         => $listasDesplegables['unidad_inventario'] ?? [],
                'categoria'                 => $listasDesplegables['categoria'] ?? [],
                'variante_atributo_sugerido' => $listasDesplegables['variante_atributo_sugerido'] ?? $listasDesplegables['variante'] ?? [],
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

        return [
            'dias_prevision' => $diasMinimos,
            'ventana_dias' => $ventana,
            'faltan' => $faltan,
        ];
    }

    /**
     * Materiales que hacen falta en las órdenes de producción abiertas: compara
     * lo que exige el kit de cada OP contra la existencia actual. Las OP desde
     * "En taller" quedan fuera porque su stock ya fue descontado.
     *
     * @return list<array<string, mixed>>
     */
    private function faltantesPorOp(): array {
        $estadosSurtidos = ['En taller', 'Recibiendo', 'Terminada', 'Cerrada'];

        $ordenes = OrdenProduccion::with(['estadoOp:id,nombre', 'estadoOpSecundario:id,nombre'])
            ->get()
            ->reject(function (OrdenProduccion $op) use ($estadosSurtidos) {
                return (bool) array_intersect(
                    [$op->estadoOp?->nombre, $op->estadoOpSecundario?->nombre],
                    $estadosSurtidos
                );
            })
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

            DB::commit();
            $this->invalidarCache();
            Myhelp::EscribirEnLog($this, 'STORE:MaterialCompra EXITOSO', $registradas . ' material(es) actualizados', false);

            return back()->with(
                'success',
                'Se registraron ' . $registradas . ' compra(s) de material. Las entradas y los precios quedaron en el histórico.'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function predecirCompra(int $id): JsonResponse
    {
        return response()->json($this->compraPredictor->predecirParaMaterial($id));
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:Materials');
        DB::beginTransaction();
        try {
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        
        // Validar precio de última compra (obligatorio) y, si el material se
        // marca como procesado, las líneas de su ficha técnica.
        // El código del material no se digita: lo arma el modelo con el par
        // codigo_categoria + codigo_base (TEX-LFI-4).
        $request->validate([
            'last_purchase_price' => 'required|numeric|min:0',
            'last_purchase_date' => 'required|date',
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
        
        // Guardar precio de última compra
        $price = $request->input('last_purchase_price');
        $date = $request->input('last_purchase_date');
        
        $Material->prices()->create([
            'precio' => $price,
            'fecha' => $date
        ]);

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
        
        // Actualizar precio de última compra si se proporciona
        if ($request->has('last_purchase_price') && $request->filled('last_purchase_price')) {
            $price = $request->input('last_purchase_price');
            $date = $request->input('last_purchase_date', now()->toDateString());
            
            // Si ya existe un precio hoy, actualizarlo; de lo contrario, crear uno nuevo
            $existingPrice = $Material->prices()->whereDate('fecha', $date)->first();
            if ($existingPrice) {
                $existingPrice->update(['precio' => $price]);
            } else {
                $Material->prices()->create([
                    'precio' => $price,
                    'fecha' => $date
                ]);
            }
        }

        // El nuevo precio también afecta el costo de las OPs abiertas que usan
        // este material: se renueva el precio guardado y se anota el cambio.
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
        $request->validate([
            'archivo' => [
                'required',
                'file',
                'max:10240',    // máx 10 MB
                'mimes:xlsx',   // valida por extensión del cliente (suficiente como primera capa)
            ],
        ]);

        $archivo = $request->file('archivo');

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
