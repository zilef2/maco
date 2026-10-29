<?php

namespace App\Http\Controllers;

use App\Models\FichaTecnica;
use App\Models\Material;
use App\Models\MaterialMovimiento;
use App\Models\Parametro;
use App\Models\Proveedor;
use App\Services\FichaTecnicaService;
use App\Services\ListaDesplegableService;
use App\Services\CostoMaterialOpService;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        protected FichaTecnicaService $fichaTecnicaService
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
        $Materiales->each(function (Material $material) use ($bloqueosFicha) {
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
     */
    public function registrarCompra(Request $request): RedirectResponse {
        Myhelp::EscribirEnLog($this, ' Begin STORE:MaterialCompra');

        $datos = $request->validate([
            'materiales' => ['required', 'array', 'min:1'],
            'materiales.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'materiales.*.cantidad' => ['required', 'numeric', 'min:0.01'],
        ]);

        $compras = collect($datos['materiales'])
            ->filter(fn ($fila) => !empty($fila['material_id']) && (float) ($fila['cantidad'] ?? 0) > 0)
            ->groupBy(fn ($fila) => (int) $fila['material_id'])
            ->map(fn ($filas, $materialId) => [
                'material_id' => (int) $materialId,
                'cantidad' => round($filas->sum(fn ($fila) => (float) $fila['cantidad']), 2),
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

                $registradas++;
            }

            DB::commit();
            $this->invalidarCache();
            Myhelp::EscribirEnLog($this, 'STORE:MaterialCompra EXITOSO', $registradas . ' material(es) actualizados', false);

            return back()->with(
                'success',
                'Se registraron ' . $registradas . ' compra(s) de material. Las entradas quedaron en el histórico.'
            );
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
        
        // Validar precio de última compra (obligatorio) y, si el material se
        // marca como procesado, las líneas de su ficha técnica.
        $request->validate([
            'last_purchase_price' => 'required|numeric|min:0',
            'last_purchase_date' => 'required|date',
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

        if (!empty($colores) && !empty($variantes)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'colores' => 'Un material no puede tener colores y otras variantes al mismo tiempo. Usa solo una de las dos listas.',
            ]);
        }

        $datos = $request->except(['materiales', 'last_purchase_price', 'last_purchase_date', 'colores', 'variantes']);
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

    public function destroyBulk(Request $request) {
        $Material = Material::whereIn('id', $request->id);
        $Material->delete();
        $this->invalidarCache();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

    // ─────────────────────────────────────────────────────────
    //  CARGA MASIVA DESDE EXCEL
    // ─────────────────────────────────────────────────────────
    /**
     * Plantilla del formato actual: las presentaciones van en una única
     * columna (Todas_las_variantes, separadas por coma) y alimentan la tabla
     * material_variants. Su tipo se deduce de "Maneja color": SI → colores,
     * NO → variantes; el orden es la posición en la lista (0, 1, 2…).
     * "Unidad inventario" trae la cantidad actual de stock (entero) y se
     * guarda en existencia_actual; el nombre de la unidad no se importa.
     */
    public function descargarPlantillaExcel(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Materiales');

        $encabezados = [
            'Codigo material',
            'Nombre base',
            'Categoria',
            'Unidad inventario',
            'Estado',
            'Maneja color (SI/NO)',
            'Maneja talla (SI/NO)',
            'Maneja otra variante (SI/NO)',
            'Variante atributo sugerido',
            'Proveedor habitual',
            'Observaciones',
            'Todas_las_variantes',
            'Es producido (SI/NO)',
        ];

        $hoja->fromArray($encabezados, null, 'A1');
        $hoja->fromArray([
            [
                'MAT-30', 'Botón forrado', 'Tapas', 240, 'Activo',
                'SI', 'NO', 'NO', 'Color', '', 'Ejemplo con colores',
                'Rojo, Azul, Negro', 'NO',
            ],
            [
                'MAT-31', 'Tela poliéster', 'Tela', 1500, 'Inactivo',
                'NO', 'SI', 'NO', 'Talla', '', 'Ejemplo con tallas',
                'S, M, L, XL', 'SI',
            ],
        ], null, 'A2');

        $hoja->getStyle('A1:M1')->getFont()->setBold(true);
        foreach (range('A', 'M') as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'plantilla-materiales.xlsx', [
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
        // "unidad_inventario" conserva el nombre interno de la columna, pero
        // en este formato trae la cantidad entera de stock (→ existencia_actual).
        $columnasRequeridas = [
            'codigo_material',
            'nombre_base',
            'categoria',
            'unidad_inventario',
            'estado',
            'todas_las_variantes',
        ];

        // Rótulos de sección del Excel (celdas decorativas): se ignoran en silencio.
        $rotulosDeSeccion = ['opcionales', 'requeridos', 'obligatorios'];

        $aliasColumnas = [
            'codigomaterial'          => 'codigo_material',
            'codigo'                  => 'codigo_material',
            'nombrebase'              => 'nombre_base',
            'nombre'                  => 'nombre_base',
            'categoria'               => 'categoria',
            'unidadinventario'        => 'unidad_inventario',
            'unidad'                  => 'unidad_inventario',
            'manejacolor'             => 'maneja_color',
            'manejatalla'             => 'maneja_talla',
            'manejaotravariante'      => 'maneja_otra_variante',
            'manejaotrovariante'      => 'maneja_otra_variante',
            'estado'                  => 'estado',
            'varianteatributosugerido'=> 'variante_atributo_sugerido',
            'proveedorhabitual'       => 'proveedor_habitual',
            'observaciones'           => 'observaciones',
            // Lista única de presentaciones separadas por coma; alimenta
            // material_variants (orden = posición, tipo = color o variante).
            'todaslasvariantes'       => 'todas_las_variantes',
            'esproducido'             => 'es_producido',
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

        // 6. Construir registros (sin tocar la BD aún: primero validar todo)
        $parsearBooleano = function ($valor): bool {
            $clave = preg_replace('/[^a-z]/', '', mb_strtolower(trim((string) $valor)));
            return in_array($clave, ['si', 's', 'yes', 'y', 'true', 't', '1'], true);
        };

        // El Excel puede traer el nombre del proveedor; la columna guarda el id.
        $proveedoresPorNombre = Proveedor::query()
            ->get(['id', 'nombre'])
            ->mapWithKeys(fn (Proveedor $proveedor) => [
                mb_strtolower(trim((string) $proveedor->nombre)) => (int) $proveedor->id,
            ]);

        $errores  = [];
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

            $codigo = mb_strtoupper(trim((string) ($registro['codigo_material'] ?? '')));
            $nombre = trim((string) ($registro['nombre_base'] ?? ''));

            if ($codigo === '' || $nombre === '') {
                $errores[] = "Fila {$filaExcel}: faltan datos obligatorios (Codigo material y Nombre base)";
                continue;
            }

            if (!preg_match('/^MAT-\d+$/', $codigo)) {
                $errores[] = "Fila {$filaExcel}: el código \"{$codigo}\" no tiene el formato MAT-30";
                continue;
            }

            // Unidad inventario: en este formato es la cantidad entera de stock
            // y se guarda en existencia_actual (no se generan movimientos).
            $cantidadTexto = trim((string) ($registro['unidad_inventario'] ?? ''));
            $existencia = filter_var($cantidadTexto, FILTER_VALIDATE_INT);
            if ($cantidadTexto === '' || $existencia === false || $existencia < 0) {
                $errores[] = "Fila {$filaExcel}: Unidad inventario debe ser una cantidad entera (0 o más) y llegó \"{$cantidadTexto}\"";
                continue;
            }

            $estadoBruto = (string) ($registro['estado'] ?? '');
            $estadoTexto = $normalizarTexto($estadoBruto);
            if (!in_array($estadoTexto, ['activo', 'inactivo'], true)) {
                $errores[] = "Fila {$filaExcel}: Estado debe ser Activo o Inactivo y llegó \"{$estadoBruto}\"";
                continue;
            }

            $manejaColor = $parsearBooleano($registro['maneja_color'] ?? '');
            $manejaTalla = $parsearBooleano($registro['maneja_talla'] ?? '');
            $manejaOtraVariante = $parsearBooleano($registro['maneja_otra_variante'] ?? '');

            if ($manejaColor && $manejaOtraVariante) {
                $errores[] = "Fila {$filaExcel}: un material no puede manejar color y otra variante al mismo tiempo.";
                continue;
            }

            // Todas_las_variantes alimenta material_variants: el orden es la
            // posición en la lista (0, 1, 2…) y el tipo se deduce de "Maneja
            // color": SI → colores, NO → variantes.
            $listaVariantes = Material::normalizarListaVariantes($registro['todas_las_variantes'] ?? null);
            $colores = $manejaColor ? $listaVariantes : [];
            $variantes = $manejaColor ? [] : $listaVariantes;

            $proveedorTexto = trim((string) ($registro['proveedor_habitual'] ?? ''));
            $proveedorId = null;

            if ($proveedorTexto !== '') {
                $proveedorId = $proveedoresPorNombre[mb_strtolower($proveedorTexto)] ?? null;
                if ($proveedorId === null && is_numeric($proveedorTexto)) {
                    $proveedorId = (int) $proveedorTexto;
                }
            }

            $atributoSugerido = trim(strip_tags((string) ($registro['variante_atributo_sugerido'] ?? '')));
            $observaciones = trim(strip_tags((string) ($registro['observaciones'] ?? '')));

            $registros[] = [
                'datos' => [
                    'codigo_material'            => $codigo,
                    'nombre_base'                => $nombre,
                    'categoria'                  => mb_substr(strip_tags((string) ($registro['categoria'] ?? '')), 0, 255),
                    'existencia_actual'          => $existencia,
                    'maneja_color'               => $manejaColor,
                    'maneja_talla'               => $manejaTalla,
                    'maneja_otra_variante'       => $manejaOtraVariante,
                    'estado'                     => ucfirst($estadoTexto),
                    'variante_atributo_sugerido' => $atributoSugerido !== '' ? mb_substr($atributoSugerido, 0, 255) : null,
                    'proveedor_habitual'         => $proveedorId,
                    'observaciones'              => $observaciones !== '' ? mb_substr($observaciones, 0, 255) : null,
                    'material_procesado'         => $parsearBooleano($registro['es_producido'] ?? '') ? 1 : 0,
                ],
                'colores' => $colores,
                'variantes' => $variantes,
                'fila' => $filaExcel,
            ];
        }

        if (!empty($errores)) {
            return back()->with('error', 'No se importó nada por los siguientes problemas: ' . implode(' | ', $errores));
        }
        if (empty($registros)) {
            return back()->with('error', 'El archivo no contiene filas de datos.');
        }

        // 7. Insertar/actualizar registros (upsert por codigo_material)
        $creados     = 0;
        $actualizados = 0;
        DB::beginTransaction();
        try {
            foreach ($registros as $registro) {
                $datos = $registro['datos'];
                $codigoMaterial = $datos['codigo_material'];
                $material = isset($existentes[$codigoMaterial])
                    ? Material::find($existentes[$codigoMaterial])
                    : null;

                if ($material) {
                    // El nuevo formato no trae el nombre de la unidad (la columna
                    // trae cantidad), así que se conserva la unidad ya registrada.
                    $material->update($datos);
                    $actualizados++;
                } else {
                    $material = Material::create([...$datos, 'unidad_inventario' => 'Unidades']);
                    $existentes[$codigoMaterial] = $material->id;
                    $creados++;
                }

                // Lista vacía borra las variantes anteriores; los indicadores
                // maneja_color/maneja_otra_variante se recalculan dentro.
                $material->sincronizarVariantes($registro['colores'], $registro['variantes']);
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
            : ' Columnas ignoradas (encabezado no reconocido): ' . implode(', ', $encabezadosIgnorados) . '.';
        return back()->with('success', "Importación completada: {$creados} creado(s), {$actualizados} actualizado(s).{$avisoIgnoradas}");
    }

}
