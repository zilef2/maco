<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyBulkProductoRequest;
use App\Models\Producto;
use App\Models\Distribucion_por_tallas;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use App\Services\FichaTecnicaService;
use App\Services\ProductoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductoController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'Producto';

    /** Controles de variante que no usan opciones ni tallas. */
    private const CONTROLES_SIN_TALLA = ['Sin talla', 'Sin talla / sin color', 'Sin color'];


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct(
        protected \App\Services\ListaDesplegableService $listaDesplegableService,
        protected FichaTecnicaService $fichaTecnicaService,
        protected ProductoService $productoService,
        protected \App\Services\CostoMaterialOpService $costoMaterialOpService
    ) {
        $this->thisAtributos = (new Producto())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' Productos '));
        // Fichas con sus materiales y OPs que impiden editarlas.
        $bloqueos = $this->fichaTecnicaService->bloqueosPorProducto();
        // Listado completo: la búsqueda, los filtros, el orden y la paginación
        // viven en el frontend (igual que Materiales), así que aquí no se lee
        // ningún parámetro de filtro.
        $Productos = Producto::query()
            ->with(['distribucionesPorTallas.ordenesProduccion', 'fichaTecnica.materiales', 'procesos'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($producto) use ($bloqueos) {
                $ficha = $producto->fichaTecnica->first();
                $bloqueo = $bloqueos[$producto->id] ?? [];

                $producto->ficha_id = $ficha?->id;
                $producto->ficha_materiales = $ficha
                    ? $ficha->materiales->map(fn ($material) => [
                        'material_componente_id' => $material->material_componente_id,
                        'consumo_estandar'       => (float) $material->consumo_estandar,
                        'unidad_medida'          => $material->unidad_medida,
                        'costo_asociado_teorico' => (float) $material->costo_asociado_teorico,
                    ])->values()->all()
                    : [];
                $producto->ficha_bloqueada = !empty($bloqueo);
                $producto->ficha_bloqueo_detalle = collect($bloqueo)
                    ->map(fn ($estado, $numeroOp) => $numeroOp . ' (' . $estado . ')')
                    ->implode(', ');

                // Procesos del producto: sus filas y el costo total que aporta a
                // la OP (se multiplica por la cantidad del producto).
                $producto->producto_procesos = $producto->procesos
                    ->sortBy('id')
                    ->map(fn ($proceso) => [
                        'id'            => $proceso->id,
                        'nombre'        => $proceso->nombre,
                        'coste_proceso' => (float) $proceso->coste_proceso,
                    ])->values()->all();
                $producto->costo_proceso_total = round((float) $producto->procesos->sum('coste_proceso'), 2);

                // Cantidad total del producto: la suma de lo planificado por talla,
                // el mismo total que muestra el modal «Distribución por Tallas».
                $producto->cantidad = (int) $producto->distribucionesPorTallas->sum('cantidad_planificada');

                // OPs que ya usan estas tallas: el modal solo las muestra y avisa
                // dónde se cambian.
                $producto->ordenes_con_tallas = $producto->distribucionesPorTallas
                    ->flatMap->ordenesProduccion
                    ->unique('id')
                    ->sortBy('numero_op')
                    ->pluck('numero_op')
                    ->filter()
                    ->values()
                    ->all();

                return $producto;
            });
        $listasDesplegables = array_change_key_case($this->listaDesplegableService->getOptionsByCategoriaGrouped('producto'), CASE_LOWER);
        // Opciones para registrar la ficha técnica dentro de "Nuevo producto".
        $fichaOpciones = $this->fichaTecnicaService->opcionesMateriales();

        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'productos' => $Productos->values(),
            'total' => $Productos->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            // 'losSelect'         => $this->losSelect(Producto::class, ' Producto','proveeNombre'),
             'losSelect'         => [
                 'Categoria_original'  => $listasDesplegables['categoria_original'] ?? [],
                 'Familia_normalizada' => $listasDesplegables['familia_normalizada'] ?? [],
                 'Control_de_variante' => $listasDesplegables['control_de_variante'] ?? [],
                 'Opciones'            => $listasDesplegables['opciones'] ?? [],
                 'materiales'          => $fichaOpciones['materiales'],
                 'unidad_medida'       => $fichaOpciones['unidad_medida'],
             ],
            'titulos'           => Producto::getFillableWithTypes(),

        ]);
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
    public function Filtros($request): Builder {
        $Productos = Producto::query();

        // La búsqueda libre es solo por código y título.
        $busqueda = trim((string) $request->input('search', ''));
        if ($busqueda !== '') {
            $like = '%' . $busqueda . '%';
            $Productos->where(function ($query) use ($like) {
                $query->where('Codigo_interno', 'LIKE', $like)
                    ->orWhere('ID_producto_Shopify', 'LIKE', $like)
                    ->orWhere('Titulo_Shopify', 'LIKE', $like);
            });
        }

        foreach ([
            'categoria'         => 'Categoria_original',
            'familia'           => 'Familia_normalizada',
            'control_variante'  => 'Control_de_variante',
        ] as $parametro => $columna) {
            $valor = trim((string) $request->input($parametro, ''));
            if ($valor !== '') {
                $Productos->where($columna, $valor);
            }
        }

        // «Opciones» es el detalle variable (tallas, colores, rangos…), por eso
        // se filtra como texto y no con una lista cerrada.
        $opcion = trim((string) $request->input('opcion', ''));
        if ($opcion !== '') {
            $Productos->where('Opciones', 'LIKE', '%' . $opcion . '%');
        }

        // El estado se guarda como 1/0, pero también hay textos históricos
        // («Activo», «Sí», etc.). Se comparan todos de la misma forma.
        $estado = strtolower(trim((string) $request->input('estado', '')));
        if (in_array($estado, ['activo', 'inactivo'], true)) {
            $activos = ['1', 'activo', 'activa', 'true', 'si', 'sí'];
            $marcadores = implode(',', array_fill(0, count($activos), '?'));

            if ($estado === 'activo') {
                $Productos->whereRaw("LOWER(TRIM(Estado)) IN ($marcadores)", $activos);
            } else {
                $Productos->where(function ($query) use ($marcadores, $activos) {
                    $query->whereRaw("LOWER(TRIM(Estado)) NOT IN ($marcadores)", $activos)
                        ->orWhereNull('Estado');
                });
            }
        }

        foreach (['precio_venta_producto'] as $columna) {
            $precio = (string) $request->input($columna, '');
            if ($precio === 'con_precio') {
                $Productos->whereNotNull($columna);
            } elseif ($precio === 'sin_precio') {
                $Productos->whereNull($columna);
            }
        }

        $tallas = (string) $request->input('tallas', '');
        if ($tallas === 'con') {
            $Productos->whereHas(
                'distribucionesPorTallas',
                fn ($query) => $query->where('talla', '<>', '')
            );
        } elseif ($tallas === 'sin') {
            $Productos->whereDoesntHave(
                'distribucionesPorTallas',
                fn ($query) => $query->where('talla', '<>', '')
            );
        }

        $cantidad = (string) $request->input('cantidad', '');
        if ($cantidad === 'con') {
            $Productos->whereHas(
                'distribucionesPorTallas',
                fn ($query) => $query->where('cantidad_planificada', '>', 0)
            );
        } elseif ($cantidad === 'sin') {
            $Productos->whereDoesntHave(
                'distribucionesPorTallas',
                fn ($query) => $query->where('cantidad_planificada', '>', 0)
            );
        }

        $ficha = (string) $request->input('ficha', '');
        if ($ficha === 'con') {
            $Productos->whereHas('fichaTecnica');
        } elseif ($ficha === 'sin') {
            $Productos->whereDoesntHave('fichaTecnica');
        }

        if ($request->has(['field', 'order'])) {
            $Productos->orderBy($request->field, $request->order);
        } else {
            $Productos->orderBy('updated_at', 'DESC');
        }

        return $Productos;
    }

//    public function Dependencias()
//    {
//        $no_nadasSelect = No_nada::all('id','nombre as name')->toArray();
//        array_unshift($no_nadasSelect,["name"=>"Seleccione un no_nada",'id'=>0]);

//        $ejemploSelec = CentroCosto::all('id', 'nombre as name')->toArray();
//        array_unshift($ejemploSelec, ["name" => "Seleccione un ejemploSelec", 'id' => 0]);
//        return [$no_nadasSelect];
//        return [$no_nadasSelect,$ejemploSelec];
//    }

    //</editor-fold>

    public function PerPageAndPaginate($request, $Productos) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $Productos->forPage($page, $perPage),
            $Productos->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:Productos');

        $datos = $request->validate($this->reglasProducto());

        $materiales = collect($datos['materiales'] ?? [])
            ->filter(fn ($fila) => !empty($fila['material_componente_id']))
            ->values()
            ->all();
        unset($datos['materiales']);

        $tallas = $this->normalizarTallas($datos['tallas'] ?? []);
        unset($datos['tallas']);

        $procesos = $this->procesosDesdeRequest($datos);
        unset($datos['procesos']);

        // Un control "sin talla" no tiene opciones: se guarda vacío sin tallas derivadas.
        if (in_array(trim((string) ($datos['Control_de_variante'] ?? '')), self::CONTROLES_SIN_TALLA, true)) {
            $datos['Opciones'] = '';
            $tallas = [];
        }

        // La tabla productos exige estas columnas: si el formulario las deja vacías
        // se guardan como cadena vacía (el "vacío" de negocio), no como null.
        foreach (['ID_producto_Shopify', 'Categoria_original', 'Familia_normalizada', 'Control_de_variante', 'Opciones'] as $campo) {
            $datos[$campo] = (string) ($datos[$campo] ?? '');
        }
        $datos['Estado'] = ($datos['Estado'] ?? '') === '' ? 1 : (int) $datos['Estado'];
        foreach (['costo_anticipado', 'precio_venta_producto'] as $campo) {
            if (($datos[$campo] ?? null) === '') {
                $datos[$campo] = null;
            }
        }

        $Producto = DB::transaction(function () use ($datos, $materiales, $tallas, $procesos) {
            $Producto = Producto::create(Arr::only($datos, (new Producto())->getFillable()));

            $this->sincronizarDistribucionesPorTalla($Producto, $tallas);
            $this->sincronizarProcesos($Producto, $procesos);

            if (!empty($materiales)) {
                $this->fichaTecnicaService->guardar([
                    'nombre'     => trim(($datos['Titulo_Shopify'] ?? '') ?: ($datos['Codigo_interno'] ?? '')),
                    'productos'  => [$Producto->id],
                    'materiales' => $materiales,
                ]);
            }

            return $Producto;
        });

        Cache::forget('op_selects');
        Myhelp::EscribirEnLog($this, 'STORE:Productos EXITOSO', 'Producto id:' . $Producto->id . ' | ' . $Producto->Codigo_interno, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $Producto->Codigo_interno]));
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

    public function update(Request $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:Productos');

        $datos = $request->validate($this->reglasProducto());
        $Producto = Producto::findOrFail($id);

        $materiales = collect($datos['materiales'] ?? [])
            ->filter(fn ($fila) => !empty($fila['material_componente_id']))
            ->values()
            ->all();
        unset($datos['materiales']);

        // El formulario de edición envía siempre las tallas derivadas del control de variante.
        $editaTallas = $request->has('tallas');
        $tallas = $this->normalizarTallas($datos['tallas'] ?? []);
        unset($datos['tallas']);

        $procesos = $this->procesosDesdeRequest($datos);
        unset($datos['procesos']);
        // Como con los materiales de la ficha: si el formulario envía la zona
        // bloqueada (lista vacía), los procesos registrados se conservan.
        $editaProcesos = !empty($procesos);

        if (in_array(trim((string) ($datos['Control_de_variante'] ?? '')), self::CONTROLES_SIN_TALLA, true)) {
            $datos['Opciones'] = '';
            $tallas = [];
        }

        // La tabla productos exige estas columnas: si el formulario las deja vacías
        // se guardan como cadena vacía (el "vacío" de negocio), no como null.
        foreach (['ID_producto_Shopify', 'Categoria_original', 'Familia_normalizada', 'Control_de_variante', 'Opciones'] as $campo) {
            $datos[$campo] = (string) ($datos[$campo] ?? '');
        }
        $datos['Estado'] = ($datos['Estado'] ?? '') === '' ? 1 : (int) $datos['Estado'];
        foreach (['costo_anticipado', 'precio_venta_producto'] as $campo) {
            if (($datos[$campo] ?? null) === '') {
                $datos[$campo] = null;
            }
        }

        DB::transaction(function () use ($Producto, $datos, $materiales, $editaTallas, $tallas, $editaProcesos, $procesos) {
            $Producto->update(Arr::only($datos, (new Producto())->getFillable()));

            if ($editaTallas) {
                $this->sincronizarDistribucionesPorTalla($Producto, $tallas);
            }

            // Los procesos definen el costo de proceso de las OPs abiertas que
            // usan este producto: se renueva al cambiar las filas.
            if ($editaProcesos) {
                $this->sincronizarProcesos($Producto, $procesos);
                $this->costoMaterialOpService->recalcularCostoProcesoPorProductos([$Producto->id]);
            }

            // La ficha solo se toca si el formulario envía materiales; el servicio
            // bloquea la edición cuando el producto está en una OP activa.
            if (!empty($materiales)) {
                $ficha = $Producto->fichaTecnica()->first();
                $this->fichaTecnicaService->guardar([
                    'nombre'     => $ficha?->nombre ?: trim(($datos['Titulo_Shopify'] ?? '') ?: ($datos['Codigo_interno'] ?? '')),
                    'productos'  => [$Producto->id],
                    'materiales' => $materiales,
                ], $ficha);
            }
        });

        Cache::forget('op_selects');
        Myhelp::EscribirEnLog($this, 'UPDATE:Productos EXITOSO', 'Producto id:' . $Producto->id . ' | ' . $Producto->Codigo_interno, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $Producto->Codigo_interno]));
    }

    /**
     * Reglas de validación del producto, compartidas por alta y edición.
     */
    private function reglasProducto(): array {
        return [
            'Codigo_interno'       => ['required', 'string', 'max:255'],
            'Titulo_Shopify'       => ['required', 'string', 'max:255'],
            'ID_producto_Shopify'  => ['nullable', 'string', 'max:255'],
            'Categoria_original'   => ['nullable', 'string', 'max:255'],
            'Familia_normalizada'  => ['nullable', 'string', 'max:255'],
            'Control_de_variante'  => ['nullable', 'string', 'max:255'],
            'Opciones'             => ['nullable', 'string', 'max:255'],
            'Estado'               => ['nullable', 'integer', 'in:0,1'],
            'Observaciones'        => ['nullable', 'string', 'max:255'],
            'descripcion'          => ['nullable', 'string', 'max:5000'],
            'instrucciones'        => ['nullable', 'string', 'max:5000'],
            'costo_anticipado'      => ['nullable', 'numeric', 'min:0'],
            'precio_venta_producto'      => ['nullable', 'numeric', 'min:0'],
            // Ficha técnica (opcional): se registra junto al producto.
            'materiales'           => ['nullable', 'array'],
            'materiales.*.material_componente_id' => ['required', 'integer', 'exists:materials,id'],
            'materiales.*.consumo_estandar'       => ['required', 'numeric', 'min:0'],
            'materiales.*.unidad_medida'          => ['nullable', 'string', 'max:255'],
            'materiales.*.costo_asociado_teorico' => ['nullable', 'numeric', 'min:0'],
            // Procesos del producto: su suma × la cantidad define el costo de
            // proceso de la OP.
            'procesos'             => ['nullable', 'array', 'max:50'],
            'procesos.*.nombre'    => ['required', 'string', 'max:255'],
            'procesos.*.coste_proceso' => ['nullable', 'numeric', 'min:0'],
            // Tallas derivadas del control de variante (alta y edición).
            'tallas'               => ['nullable', 'array', 'max:50'],
            'tallas.*'             => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * Tallas recibidas del formulario, saneadas y sin duplicados.
     *
     * @param  mixed  $tallas
     * @return array<int, string>
     */
    private function normalizarTallas($tallas): array {
        return collect(is_array($tallas) ? $tallas : [])
            ->map(fn ($talla) => trim((string) $talla))
            ->filter(fn ($talla) => $talla !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Materializa las tallas del control de variante como filas de
     * distribucion_por_tallas y las vincula al producto por el pivote.
     * Reutiliza la fila que ya tiene esa talla (conserva su cantidad planificada),
     * crea las que faltan y desvincula, sin borrar, las que ya no aplican.
     *
     * @param  array<int, string>  $tallas
     */
    /**
     * Filtra las filas de procesos enviadas por el formulario: se ignora las
     * que quedaron sin nombre.
     *
     * @return list<array{nombre: string, coste_proceso: mixed}>
     */
    private function procesosDesdeRequest(array $datos): array {
        return collect($datos['procesos'] ?? [])
            ->filter(fn ($fila) => trim((string) ($fila['nombre'] ?? '')) !== '')
            ->values()
            ->all();
    }

    /**
     * Reemplaza los procesos del producto con las filas recibidas.
     */
    private function sincronizarProcesos(Producto $Producto, array $procesos): void {
        $Producto->procesos()->delete();

        foreach ($procesos as $fila) {
            $Producto->procesos()->create([
                'nombre'        => trim((string) $fila['nombre']),
                'coste_proceso' => (float) ($fila['coste_proceso'] ?? 0),
            ]);
        }
    }

    private function sincronizarDistribucionesPorTalla(Producto $Producto, array $tallas): void {
        $registradas = $Producto->distribucionesPorTallas()
            ->get()
            ->keyBy(fn ($distribucion) => trim((string) $distribucion->talla));

        $ids = array_map(function (string $talla) use ($registradas, $Producto) {
            $existente = $registradas->get($talla);
            if ($existente) {
                return $existente->id;
            }

            return Distribucion_por_tallas::create([
                'grupo' => null,
                'producto_id' => $Producto->id,
                'talla' => $talla,
                'cantidad_planificada' => 0,
            ])->id;
        }, $tallas);

        $Producto->distribucionesPorTallas()->sync($ids);
    }

    /**
     * Sincroniza la distribución por tallas (plantilla) de un producto vía pivote:
     * actualiza las filas que traen id (solo talla y cantidad planificada),
     * crea+vincula las nuevas y desvincula (sin borrar) las que ya no vienen.
     */
    public function syncDistribucion(Request $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin SYNC-DISTRIBUCION:Productos');
        DB::beginTransaction();

        $Producto = Producto::findOrFail($id);
        
        // Snapshot del estado actual para historial
        $estadoAnterior = $Producto->distribucionesPorTallas()
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'talla' => $d->talla,
                'cantidad_planificada' => $d->cantidad_planificada,
            ])
            ->keyBy('id')
            ->toArray();
        
        $conservados = [];
        foreach ($request->input('distribucion_tallas', []) as $talla) {
            if (!empty($talla['id'])) {
                $distribucion = Distribucion_por_tallas::find($talla['id']);
                if ($distribucion) {
                    $datosAnteriores = [
                        'talla' => $distribucion->talla,
                        'cantidad_planificada' => $distribucion->cantidad_planificada,
                    ];
                    
                    $nuevaTalla = $talla['talla'] ?? $distribucion->talla;
                    $nuevaCantidad = (int) ($talla['cantidad_planificada'] ?? 0);
                    
                    // Detectar cambios para historial
                    $huboCambios = $distribucion->talla !== $nuevaTalla 
                        || $distribucion->cantidad_planificada !== $nuevaCantidad;
                    
                    $distribucion->update([
                        'talla' => $nuevaTalla,
                        'cantidad_planificada' => $nuevaCantidad,
                    ]);
                    
                    // Registrar en historial si hubo cambios
                    if ($huboCambios) {
                        \App\Models\ProductoTallasHistory::registrar(
                            $Producto->id,
                            $nuevaTalla,
                            $nuevaCantidad,
                            'update',
                            $datosAnteriores,
                            ['talla' => $nuevaTalla, 'cantidad_planificada' => $nuevaCantidad]
                        );
                    }
                    
                    $conservados[] = $distribucion->id;
                }
                continue;
            }
            if (empty($talla['talla']) && empty($talla['cantidad_planificada'])) {
                continue;
            }
            $nueva = Distribucion_por_tallas::create([
                'grupo' => null,
                'producto_id' => $Producto->id,
                'talla' => $talla['talla'] ?? null,
                'cantidad_planificada' => (int) ($talla['cantidad_planificada'] ?? 0),
            ]);
            $conservados[] = $nueva->id;
            
            // Registrar creación en historial
            \App\Models\ProductoTallasHistory::registrar(
                $Producto->id,
                $nueva->talla,
                $nueva->cantidad_planificada,
                'create',
                null,
                ['talla' => $nueva->talla, 'cantidad_planificada' => $nueva->cantidad_planificada]
            );
        }
        
        // Detectar tallas eliminadas (desvinculadas) para historial
        $idsAnteriores = array_keys($estadoAnterior);
        $eliminadas = array_diff($idsAnteriores, $conservados);
        foreach ($eliminadas as $idEliminado) {
            $distribucionEliminada = $estadoAnterior[$idEliminado];
            \App\Models\ProductoTallasHistory::registrar(
                $Producto->id,
                $distribucionEliminada['talla'],
                $distribucionEliminada['cantidad_planificada'],
                'delete',
                $distribucionEliminada,
                null
            );
        }
        
        $Producto->distribucionesPorTallas()->sync($conservados);

        DB::commit();
        Myhelp::EscribirEnLog($this, 'SYNC-DISTRIBUCION:Productos EXITOSO', 'Producto id:' . $Producto->id . ' | ' . $Producto->Codigo_interno, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $Producto->Codigo_interno]));
    }

    /**
     * Obtiene el historial de cambios de tallas de un producto.
     */
    public function tallasHistory($id)
    {
        $producto = Producto::findOrFail($id);
        $historial = \App\Models\ProductoTallasHistory::porProducto($producto->id, 50);

        return response()->json([
            'producto' => [
                'id' => $producto->id,
                'codigo' => $producto->Codigo_interno,
                'titulo' => $producto->Titulo_Shopify,
            ],
            'historial' => $historial,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($Productoid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:Productos');
        $Producto = Producto::find($Productoid);
        $elnombre = $Producto->nombre;
        $Producto->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:Productos', 'Producto id:' . $Producto->id . ' | ' . $Producto->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(DestroyBulkProductoRequest $request): JsonResponse|RedirectResponse
    {
        $resultado = $this->productoService->eliminarMasivo($request->validated()['ids']);
        if ($request->wantsJson()) {
            return response()->json($resultado);
        }
        return back()->with('bulk_result', $resultado);
    }
    //FIN : STORE - UPDATE - DELETE

    // ─────────────────────────────────────────────────────────
    //  CARGA MASIVA DESDE EXCEL (general, sin FK)
    // ─────────────────────────────────────────────────────────
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
        $columnasRequeridas = ['Codigo_interno', 'Titulo_Shopify'];

        $aliasColumnas = [
            'codigointerno'      => 'Codigo_interno',
            'codigo'             => 'Codigo_interno',
            'tituloshopify'      => 'Titulo_Shopify',
            'titulo'             => 'Titulo_Shopify',
            'idproductoshopify'  => 'ID_producto_Shopify',
            'idshopify'          => 'ID_producto_Shopify',
            'categoriaoriginal'  => 'Categoria_original',
            'categoria'          => 'Categoria_original',
            'familianormalizada' => 'Familia_normalizada',
            'familia'            => 'Familia_normalizada',
            'controldevariante'  => 'Control_de_variante',
            'variante'           => 'Control_de_variante',
            'opciones'           => 'Opciones',
            'rango'              => 'Opciones',
            'rangotallas'        => 'Opciones',
            'estado'             => 'Estado',
            'observaciones'      => 'Observaciones',
            'descripcion'        => 'descripcion',
            'preciopredicho'     => 'costo_anticipado',
            'costoanticipado'    => 'costo_anticipado',
            'preciopreventa'     => 'precio_venta_producto',
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
            if (isset($aliasColumnas[$clave])) {
                $encabezado[$col] = $aliasColumnas[$clave];
                continue;
            }
            // Encabezado no exacto (ej: "Opciones tallas"): aceptar el alias mas largo
            // que sea prefijo, para no ignorar columnas por variantes del nombre.
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
        $texto = function ($valor, int $max = 255): string {
            return mb_substr(strip_tags((string) ($valor ?? '')), 0, $max);
        };
        $entero = function ($valor) {
            return is_numeric($valor) ? (int) $valor : null;
        };

        $errores    = [];
        $existentes = Producto::pluck('Codigo_interno')->flip();
        $registros  = [];

        foreach ($filas as $numFila => $fila) {
            $filaExcel = $numFila + 1; // fila real en el Excel (el encabezado es la 1)

            $registro = [];
            foreach ($encabezado as $col => $claveCanonica) {
                $valorNuevo = $fila[$col] ?? null;
                // Varias columnas pueden mapear a la misma clave (ej: "Rango" y "Opciones"):
                // gana la primera con valor; una vacía no pisa a una ya cargada.
                if (!array_key_exists($claveCanonica, $registro)
                    || (($registro[$claveCanonica] === null || $registro[$claveCanonica] === '')
                        && $valorNuevo !== null && $valorNuevo !== '')) {
                    $registro[$claveCanonica] = $valorNuevo;
                }
            }

            // Omitir filas completamente vacías
            if (!array_filter($registro, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }

            $codigo  = trim((string) ($registro['Codigo_interno'] ?? ''));
            $titulo  = trim((string) ($registro['Titulo_Shopify'] ?? ''));

            if ($codigo === '' || $titulo === '') {
                $errores[] = "Fila {$filaExcel}: faltan datos obligatorios (Codigo interno y Titulo Shopify)";
                continue;
            }

            $registros[] = [
                'Codigo_interno'        => $codigo,
                'Titulo_Shopify'        => $titulo,
                'ID_producto_Shopify'   => $texto($registro['ID_producto_Shopify'] ?? ''),
                'Categoria_original'    => $texto($registro['Categoria_original'] ?? ''),
                'Familia_normalizada'   => $texto($registro['Familia_normalizada'] ?? ''),
                'Control_de_variante'   => $texto($registro['Control_de_variante'] ?? ''),
                'Opciones'              => $texto($registro['Opciones'] ?? ''),
                'Estado'                => $texto($registro['Estado'] ?? ''),
                'Observaciones'         => $texto($registro['Observaciones'] ?? ''),
                'descripcion'           => mb_substr(strip_tags((string) ($registro['descripcion'] ?? '')), 0, 5000),
                'costo_anticipado'       => $entero($registro['costo_anticipado'] ?? null),
                'precio_venta_producto'       => $entero($registro['precio_venta_producto'] ?? null),
            ];
        }

        if (!empty($errores)) {
            return back()->with('error', 'No se importó nada por los siguientes problemas: ' . implode(' | ', $errores));
        }
        if (empty($registros)) {
            return back()->with('error', 'El archivo no contiene filas de datos.');
        }

        // 7. Insertar/actualizar registros (upsert por Codigo_interno)
        $creados      = 0;
        $actualizados = 0;
        DB::beginTransaction();
        try {
            foreach ($registros as $registro) {
                if (isset($existentes[$registro['Codigo_interno']])) {
                    Producto::where('Codigo_interno', $registro['Codigo_interno'])->update($registro);
                    $actualizados++;
                } else {
                    // Columnas NOT NULL sin default que el archivo puede no traer
                    $registro += [
                        'ID_producto_Shopify' => '',
                        'Categoria_original'  => '',
                        'Familia_normalizada' => '',
                        'Control_de_variante' => '',
                        'Opciones'            => '',
                        'Estado'              => '',
                        'Observaciones'       => '',
                        'descripcion'         => '',
                        'costo_anticipado'     => 0,
                        'precio_venta_producto'     => 0,
                    ];
                    Producto::create($registro);
                    $creados++;
                }
            }
            DB::commit();
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
