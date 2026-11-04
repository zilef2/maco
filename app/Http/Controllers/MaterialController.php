<?php

namespace App\Http\Controllers;

use App\Models\FichaTecnica;
use App\Models\Material;
use App\Models\MaterialMovimiento;
use App\Models\Parametro;
use App\Models\Proveedor;
use App\Http\Requests\DestroyBulkMaterialRequest;
use App\Services\FichaTecnicaService;
use App\Services\ListaDesplegableService;
use App\Services\CostoMaterialOpService;
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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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
        protected PlantillasExcelService $plantillas
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
     * anteriores dejan de usarse y se reconstruyen con datos frescos.
     */
    private function versionCache(): int {
        Cache::add('material_index_version', 1, now()->addDays(7));
        return (int) Cache::get('material_index_version', 1);
    }

    private function invalidarCache(): void {
        Cache::increment('material_index_version');
        Cache::increment('op_index_version');
        Cache::forget('op_selects');
    }

    /**
     * Previsión de existencias: con las salidas de materiales a taller que
     * generan las OPs (últimos 30 días) se calcula el gasto diario promedio de
     * cada material y cuántos días falta la existencia actual. El umbral de
     * días se configura en Parámetros (dias_prevision_stock, por defecto 2).
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

        // 4. Leer el archivo con PhpSpreadsheet
        try {
            $spreadsheet = IOFactory::load($archivo->getRealPath());
        } catch (\Throwable $e) {
            Myhelp::EscribirEnLog($this, 'uploadExcel ERROR lectura', $e->getMessage());
            return back()->with('error', 'No se pudo leer el archivo: ' . $e->getMessage());
        }

        $hoja  = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray(null, true, true, true);

        if (empty($filas)) {
            return back()->with('error', 'El archivo está vacío.');
        }

        // 5. Normalizar encabezados (acepta snake_case y nombres legibles)
        // "codigo_material" es la clave con la que se reconoce un material ya
        // cargado; "unidad_inventario" trae el nombre de la unidad de medida.
        $columnasRequeridas = [
            'codigo_material',
            'nombre_base',
            'estado',
            'procesado',
        ];

        // Rótulos de sección del Excel (celdas decorativas): se ignoran en silencio.
        $rotulosDeSeccion = ['opcionales', 'requeridos', 'obligatorios'];

        $aliasColumnas = [
            'codigomaterial'          => 'codigo_material',
            'codigo'                  => 'codigo_material',
            'nombrebase'              => 'nombre_base',
            'nombre'                  => 'nombre_base',
            'codigobase'              => 'codigo_base',
            'categoria'               => 'categoria',
            'codigocategoria'         => 'codigo_categoria',
            'unidadinventario'        => 'unidad_inventario',
            'unidad'                  => 'unidad_inventario',
            'modelodisenoabacado'     => 'variante_atributo_sugerido',
            'modelodisenoacabado'     => 'variante_atributo_sugerido',
            'modelodiseno'            => 'variante_atributo_sugerido',
            'modelo'                  => 'variante_atributo_sugerido',
            'color'                   => 'colores_lista',
            'tallatamano'             => 'tallas_lista',
            'talla'                   => 'tallas_lista',
            'manejacolor'             => 'maneja_color',
            'manejatalla'             => 'maneja_talla',
            'manejaotravariante'      => 'maneja_otra_variante',
            'manejaotrovariante'      => 'maneja_otra_variante',
            'estado'                  => 'estado',
            'varianteatributosugerido'=> 'variante_atributo_sugerido',
            'proveedorhabitual'       => 'proveedor_habitual',
            'proveedor'               => 'proveedor_habitual',
            'nitproveedor'            => 'nit_proveedor',
            'nitproveedorhabitual'    => 'nit_proveedor',
            'nit'                     => 'nit_proveedor',
            'observaciones'           => 'observaciones',
            'todaslasvariantes'       => 'todas_las_variantes',
            'costo'                   => 'costo_material',
            'costomaterial'           => 'costo_material',
            'procesado'               => 'procesado',
        ];

        $normalizarTexto = fn (string $texto): string => strtr(
            mb_strtolower(trim($texto)),
            ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']
        );

        $filaEncabezados = array_shift($filas);
        $encabezado = [];
        $encabezadosIgnorados = [];
        $clavesAlias = array_keys($aliasColumnas);
        usort($clavesAlias, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($filaEncabezados as $col => $titulo) {
            $clave = preg_replace('/[^a-z0-9]/', '', $normalizarTexto((string) $titulo));
            if ($clave === '') {
                continue;
            }
            if (in_array($clave, $rotulosDeSeccion, true)) {
                continue;
            }
            if (isset($aliasColumnas[$clave])) {
                $encabezado[$col] = $aliasColumnas[$clave];
                continue;
            }
            // Encabezado no exacto (ej: "Nombre base material"): aceptar el alias
            // mas largo que sea prefijo, para no ignorar columnas por variantes.
            $mapeada = null;
            foreach ($clavesAlias as $alias) {
                if (str_starts_with($clave, $alias)) {
                    $mapeada = $aliasColumnas[$alias];
                    break;
                }
            }
            if ($mapeada !== null) {
                $encabezado[$col] = $mapeada;
            } else {
                $encabezadosIgnorados[] = trim((string) $titulo);
            }
        }

        $faltantes = array_diff($columnasRequeridas, array_values($encabezado));
        if (!empty($faltantes)) {
            return back()->with(
                'error',
                'El archivo no tiene las columnas requeridas. Faltan: ' . implode(', ', $faltantes)
            );
        }

        // "Procesado" acompaña a "Costo": el archivo solo se acepta cuando la
        // columna viene en la celda siguiente, que es el orden de la plantilla.
        $posiciones = array_flip(array_keys($filaEncabezados));
        $columnaCosto = null;
        $columnaProcesado = null;
        foreach ($encabezado as $col => $claveCanonica) {
            if ($claveCanonica === 'costo_material') {
                $columnaCosto = $posiciones[$col];
            }
            if ($claveCanonica === 'procesado') {
                $columnaProcesado = $posiciones[$col];
            }
        }
        if ($columnaCosto === null || $columnaProcesado !== $columnaCosto + 1) {
            $esperada = $columnaCosto === null
                ? null
                : Coordinate::stringFromColumnIndex($columnaCosto + 2);
            return back()->with(
                'error',
                'La columna "Procesado" (si o no) debe ir justo después de "Costo"'
                . ($esperada !== null ? ", en la columna {$esperada} del archivo." : ', y falta "Costo" para ubicarla.')
            );
        }

        // 6. Construir registros (sin tocar la BD aún: primero validar todo)
        $parsearBooleano = function ($valor): bool {
            $clave = preg_replace('/[^a-z]/', '', mb_strtolower(trim((string) $valor)));
            return in_array($clave, ['si', 's', 'yes', 'y', 'true', 't', '1'], true);
        };

        // El Excel puede traer el NIT, el nombre o el código del proveedor; la
        // columna guarda el id. Se indexan todas las formas en que la app lo
        // identifica (sin mayúsculas, tildes ni puntuación). El NIT es la forma
        // fiable: "900347285-7" = "9003472857" = "900.347.285-7".
        $normalizarClave = fn (?string $texto): string => (string) preg_replace(
            '/[^a-z0-9]/',
            '',
            $normalizarTexto((string) $texto)
        );

        $proveedoresPorClave = [];
        $proveedoresPorNombre = [];

        foreach (Proveedor::query()->get(['id', 'codigo_proveedor', 'nombre_comercial', 'razon_social', 'nombre', 'nit']) as $proveedor) {
            $identificadores = [
                $proveedor->nombre_comercial,
                $proveedor->razon_social,
                $proveedor->nombre,
                $proveedor->codigo_proveedor,
                $proveedor->nit,
                // Código visual que usa el listado cuando no hay codigo_proveedor.
                'PRV-' . $proveedor->id,
                'PROV-' . $proveedor->id,
                (string) $proveedor->id,
            ];

            foreach ($identificadores as $identificador) {
                $clave = $normalizarClave($identificador);
                if ($clave !== '' && !isset($proveedoresPorClave[$clave])) {
                    $proveedoresPorClave[$clave] = (int) $proveedor->id;
                }
            }

            $nombres = [$proveedor->nombre_comercial, $proveedor->razon_social, $proveedor->nombre];

            foreach ($nombres as $nombre) {
                // "LA CASA DE LOS CORDONES" admite "Casa de los cordones": el
                // artículo inicial no es parte del nombre con que se le escribe.
                $sinArticulo = preg_replace('/^(la|el|los|las|un|una)\s+/u', '', $normalizarTexto((string) $nombre));
                foreach ([$nombre, $sinArticulo] as $variante) {
                    $clave = $normalizarClave($variante);
                    if ($clave === '') {
                        continue;
                    }
                    $proveedoresPorClave[$clave] ??= (int) $proveedor->id;
                    $proveedoresPorNombre[$clave] = (int) $proveedor->id;
                }
            }
        }

        $errores  = [];
        $proveedoresDesconocidos = [];
        $existentes = Material::pluck('id', 'codigo_material');
        $registros  = [];

        foreach ($filas as $numFila => $fila) {
            $filaExcel = $numFila + 1; // fila real en el Excel (el encabezado es la 1)

            $registro = [];
            foreach ($encabezado as $col => $claveCanonica) {
                $registro[$claveCanonica] = $fila[$col] ?? null;
            }

            // Omitir filas completamente vacías
            if (!array_filter($registro, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }

            $nombre = trim(strip_tags((string) ($registro['nombre_base'] ?? '')));
            $categoriaTexto = trim(strip_tags((string) ($registro['categoria'] ?? '')));

            if ($nombre === '') {
                $errores[] = "Fila {$filaExcel}: falta el Nombre base, que es obligatorio";
                continue;
            }

            // El código puede venir vacío: el material toma el siguiente número
            // libre del par. Si viene, se acepta CAT-BASE-N y se le quitan los
            // ceros a la izquierda (TEX-LFL-005 es el mismo TEX-LFL-5).
            $codigoBruto = trim((string) ($registro['codigo_material'] ?? ''));
            $codigo = Material::normalizarCodigo($codigoBruto);

            if ($codigoBruto !== '' && ($codigo === null || !preg_match(Material::PATRON_CODIGO, $codigo))) {
                $errores[] = "Fila {$filaExcel}: el código \"{$codigoBruto}\" no tiene el formato CAT-BASE-N (ej: TEX-LFI-4)";
                continue;
            }

            // El par forma el prefijo del código: primero lo que digita el
            // archivo, luego el propio prefijo del código de la fila y por
            // último la abreviatura del nombre de la categoría y del material.
            $partesCodigo = $codigo !== null ? explode('-', $codigo) : [];
            $categoriaCodigo = Material::normalizarFragmento($registro['codigo_categoria'] ?? null)
                ?: ($partesCodigo[0] ?? '');
            $baseCodigo = Material::normalizarFragmento($registro['codigo_base'] ?? null)
                ?: ($partesCodigo[1] ?? '');
            $prefijo = ($categoriaCodigo ?: Material::abreviar($categoriaTexto))
                . '-' . ($baseCodigo ?: Material::abreviar($nombre));

            // Unidad inventario: en este formato trae el nombre de la unidad (ej: "Metros", "Unidades").
            $unidadInventario = trim(strip_tags((string) ($registro['unidad_inventario'] ?? '')));

            $estadoBruto = (string) ($registro['estado'] ?? '');
            $estadoTexto = $normalizarTexto($estadoBruto);
            if (!in_array($estadoTexto, ['activo', 'inactivo'], true)) {
                $errores[] = "Fila {$filaExcel}: Estado debe ser Activo o Inactivo y llegó \"{$estadoBruto}\"";
                continue;
            }

            // Procesado solo admite si o no; el vacío se lee como no.
            $procesadoBruto = trim(strip_tags((string) ($registro['procesado'] ?? '')));
            $procesadoTexto = preg_replace('/[^a-z]/', '', $normalizarTexto($procesadoBruto));
            if (!in_array($procesadoTexto, ['si', 'no', ''], true)) {
                $errores[] = "Fila {$filaExcel}: Procesado debe ser si o no y llegó \"{$procesadoBruto}\"";
                continue;
            }
            $procesado = $procesadoTexto === 'si' ? 1 : 0;

            $manejaColor = $parsearBooleano($registro['maneja_color'] ?? '');
            $manejaTalla = $parsearBooleano($registro['maneja_talla'] ?? '');
            $manejaOtraVariante = $parsearBooleano($registro['maneja_otra_variante'] ?? '');

            // Columnas "Color" y "Talla / tamaño" alimentan material_variants y
            // pueden llenarse las dos: un material sí admite colores y otras
            // variantes a la vez. La columna legacy "todas_las_variantes" solo se
            // usa cuando la fila no trajo ninguna de las dos, para no registrar
            // lo mismo repetido.
            $colores      = Material::normalizarListaVariantes($registro['colores_lista'] ?? null);
            $tallasLista  = Material::normalizarListaVariantes($registro['tallas_lista'] ?? null);
            $variantes    = !empty($tallasLista)
                ? $tallasLista
                : (empty($colores) ? Material::normalizarListaVariantes($registro['todas_las_variantes'] ?? null) : []);

            if (!empty($colores)) {
                $manejaColor = true;
            }
            if (!empty($tallasLista)) {
                $manejaTalla = true;
            }
            if (!empty($variantes)) {
                $manejaOtraVariante = true;
            }

            // Primero el NIT, si el archivo lo trae; si no, el nombre o código
            // de la columna «Proveedor habitual».
            $nitTexto = trim(strip_tags((string) ($registro['nit_proveedor'] ?? '')));
            $proveedorTexto = trim(strip_tags((string) ($registro['proveedor_habitual'] ?? '')));
            $proveedorId = null;
            $claveProveedor = '';

            foreach ([$nitTexto, $proveedorTexto] as $textoProveedor) {
                if ($textoProveedor === '') {
                    continue;
                }
                $clave = $normalizarClave($textoProveedor);
                $claveProveedor = $claveProveedor !== '' ? $claveProveedor : $clave;
                $proveedorId = $this->buscarProveedor($clave, $proveedoresPorClave, $proveedoresPorNombre);
                if ($proveedorId !== null) {
                    $claveProveedor = $clave;
                    break;
                }
            }

            if ($proveedorId === null && $claveProveedor !== '') {
                // Se lista una sola vez aunque se repita escrito de otra forma.
                $proveedoresDesconocidos[$claveProveedor] ??= ($nitTexto !== '' ? $nitTexto : $proveedorTexto);
            }

            $atributoSugerido = trim(strip_tags((string) ($registro['variante_atributo_sugerido'] ?? '')));
            $observaciones = trim(strip_tags((string) ($registro['observaciones'] ?? '')));

            $costoRaw = $registro['costo_material'] ?? null;
            $costo = ($costoRaw !== null && $costoRaw !== '')
                ? (float) str_replace(',', '.', (string) $costoRaw)
                : null;

            $fragmentos = explode('-', $prefijo);
            $existe = $codigo !== null && isset($existentes[$codigo]);

            if (!$existe && !preg_match(Material::PATRON_CODIGO, $prefijo . '-1')) {
                $errores[] = "Fila {$filaExcel}: no se puede formar el código material; escriba Código categoría y Código base (o Categoría y Nombre base)";
                continue;
            }

            $datos = [
                'nombre_base'                => $nombre,
                'unidad_inventario'          => $unidadInventario !== '' ? mb_substr($unidadInventario, 0, 255) : 'Unidades',
                'maneja_color'               => $manejaColor,
                'maneja_talla'               => $manejaTalla,
                'maneja_otra_variante'       => $manejaOtraVariante,
                'estado'                     => ucfirst($estadoTexto),
                'variante_atributo_sugerido' => $atributoSugerido !== '' ? mb_substr($atributoSugerido, 0, 255) : null,
                'proveedor_habitual'         => $proveedorId,
                'observaciones'              => $observaciones !== '' ? mb_substr($observaciones, 0, 255) : null,
                'material_procesado'         => $procesado,
                'costo_material'             => $costo,
            ];

            // El par forma el prefijo del código y manda siempre el modelo, que
            // respeta el número de la fila cuando está libre y autogenera lo
            // contrario. Así volver a subir el mismo archivo no duplica nada.
            if ($codigo !== null) {
                $datos['codigo_material'] = $codigo;
            }

            // El par solo se cambia cuando el archivo lo trae escrito: en blanco
            // conserva el del material existente.
            if (!$existe || $categoriaCodigo !== '') {
                $datos['codigo_categoria'] = $existe ? $categoriaCodigo : $fragmentos[0];
            }
            if (!$existe || $baseCodigo !== '') {
                $datos['codigo_base'] = $existe ? $baseCodigo : $fragmentos[1];
            }
            if ($categoriaTexto !== '') {
                $datos['categoria'] = mb_substr($categoriaTexto, 0, 255);
            } elseif (!$existe) {
                $datos['categoria'] = '';
            }

            $registros[] = [
                'datos'     => $datos,
                'codigo'    => $codigo,
                'colores'   => $colores,
                'variantes' => $variantes,
                'fila'      => $filaExcel,
            ];
        }

        // Todo proveedor de la lista debe existir en la app antes de importar.
        if (!empty($proveedoresDesconocidos)) {
            return back()->with(
                'error',
                'No se encontró este proveedor, escriba su NIT (ej: 900347285-7), su nombre comercial o su código: '
                . $this->acortarAvisos(array_values($proveedoresDesconocidos), ', ')
            );
        }
        if (!empty($errores)) {
            return back()->with('error', 'No se importó nada por los siguientes problemas: ' . $this->acortarAvisos($errores));
        }
        if (empty($registros)) {
            return back()->with('error', 'El archivo no contiene filas de datos.');
        }

        // 7. Insertar/actualizar registros (se empareja por codigo_material)
        $creados     = 0;
        $actualizados = 0;
        DB::beginTransaction();
        try {
            foreach ($registros as $registro) {
                $fila = $registro['fila'];

                try {
                    $codigoMaterial = $registro['codigo'];
                    $material = $codigoMaterial !== null && isset($existentes[$codigoMaterial])
                        ? Material::find($existentes[$codigoMaterial])
                        : null;

                    if ($material) {
                        $material->update($registro['datos']);
                        $actualizados++;
                    } else {
                        // El modelo arma el código con el par, así que se registra
                        // tanto el código generado como el que traía la fila.
                        $material = Material::create($registro['datos']);
                        $existentes[$material->codigo_material] ??= $material->id;
                        if ($codigoMaterial !== null) {
                            $existentes[$codigoMaterial] ??= $material->id;
                        }
                        $creados++;
                    }

                    // Lista vacía borra las variantes anteriores; los indicadores
                    // maneja_color/maneja_otra_variante se recalculan dentro.
                    $material->sincronizarVariantes($registro['colores'], $registro['variantes']);
                } catch (\Throwable $e) {
                    // El aviso tiene que decir en qué fila del Excel nació el
                    // problema; si no, el usuario no sabe qué corregir. Las
                    // validaciones del modelo no traen mensaje útil en
                    // getMessage(), hay que leer su lista de errores.
                    $mensaje = $e instanceof \Illuminate\Validation\ValidationException
                        ? (string) collect($e->errors())->flatten()->first()
                        : $e->getMessage();

                    throw new \RuntimeException("Fila {$fila}: " . $mensaje, 0, $e);
                }
            }
            DB::commit();
            $this->invalidarCache();
        } catch (\Throwable $e) {
            DB::rollBack();
            Myhelp::EscribirEnLog($this, 'uploadExcel ERROR inserción', $e->getMessage());
            return back()->with('error', 'Error al guardar los registros: ' . $e->getMessage());
        }

        Myhelp::EscribirEnLog($this, 'uploadExcel OK', "Creados: {$creados} | Actualizados: {$actualizados}");
        $avisoIgnoradas = empty($encabezadosIgnorados)
            ? ''
            : ' Columnas ignoradas (encabezado no reconocido): ' . $this->acortarAvisos($encabezadosIgnorados, ', ') . '.';
        return back()->with('success', "Importación completada: {$creados} creado(s), {$actualizados} actualizado(s).{$avisoIgnoradas}");
    }

    /**
     * Un aviso de importación no puede ocupar toda la pantalla: se muestran
     * como máximo 7 y el resto se resume.
     *
     * @param array<int, string> $avisos
     */
    private function acortarAvisos(array $avisos, string $separador = ' | ', int $maximo = 7): string
    {
        $mostrados = array_slice(array_values($avisos), 0, $maximo);
        $restantes = count($avisos) - count($mostrados);

        if ($restantes > 0) {
            $mostrados[] = "y {$restantes} más";
        }

        return implode($separador, $mostrados);
    }

    /**
     * Id del proveedor que corresponde a lo que escribió el Excel: NIT, nombre
     * comercial, razón social o código, ya normalizados. Si nada coincide de
     * forma exacta, se acepta el único proveedor cuyo nombre contenga lo
     * escrito (o al revés), así «Casa de los cordones» sirve para registrar
     * «LA CASA DE LOS CORDONES». Con dos candidatos se prefiere avisar antes
     * que asignar el proveedor equivocado.
     *
     * @param array<string, int> $porClave
     * @param array<string, int> $porNombre
     */
    private function buscarProveedor(string $clave, array $porClave, array $porNombre): ?int
    {
        if ($clave === '') {
            return null;
        }
        if (isset($porClave[$clave])) {
            return $porClave[$clave];
        }
        if (mb_strlen($clave) < 5) {
            return null;
        }

        $candidatos = [];

        foreach ($porNombre as $claveProveedor => $idProveedor) {
            if (str_contains($claveProveedor, $clave) || str_contains($clave, $claveProveedor)) {
                $candidatos[$idProveedor] = true;
            }
        }

        return count($candidatos) === 1 ? (int) array_key_first($candidatos) : null;
    }

}
