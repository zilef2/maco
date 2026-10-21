<?php

namespace App\Http\Controllers;

use App\Models\Distribucion_por_tallas;
use App\Models\CierreOrdenProduccion;
use App\Models\GrupoTalla;
use App\Models\KitProceso;
use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\Parametro;
use App\Models\User;
use App\Models\EstadoOperacion;
use App\Models\EstadoOpMaterial;
use App\Models\Producto;
use App\Models\EmpresaExterna;
use App\Models\ProcesoExterno;
use App\Models\Proveedor;
use App\Models\RecepcionCalidad;
use App\Models\Taller;
use App\Http\Requests\StoreOrdenProduccionRequest;
use App\Traits\MaterialMovimientoTrait;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrdenProduccionController extends Controller {
    use MaterialMovimientoTrait;

    public array $thisAtributos;
    public string $FromController = 'OrdenProduccion';

    public function __construct() {
        $this->thisAtributos = (new OrdenProduccion())->getFillable();
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' OrdenProduccions '));

        // Listado completo: la tabla filtra, ordena y pagina en el frontend.
        // La clave solo depende de la versión, que se incrementa al escribir.
        $claveListado = 'op_index_v6_' . $this->versionCache();
        $OrdenProduccions = Cache::remember($claveListado, now()->addMinutes(10), function () {
            return OrdenProduccion::query()
                ->with(['productoReferencia', 'taller', 'empresaExterna', 'estadoOp', 'responsable', 'distribucionesPorTallas.producto', 'productos', 'recepcionesCalidad', 'kitProcesos.material', 'procesosExternos.materialOrigen', 'procesosExternos.proveedorDestino', 'cierres'])
                ->orderByDesc('updated_at')
                ->get()
                ->map(function ($op) {
                    $producto = $op->productoReferencia;
                    $nombreProducto = fn ($p) => $p
                        ? ($p->Titulo_Shopify ?: ($p->Codigo_interno ?: 'Producto #' . $p->id))
                        : null;

                    $op->producto_nombre = $nombreProducto($producto)
                        ?? ($op->producto_referencia_id ? '#' . $op->producto_referencia_id : '');
                    $op->productos_nombres = $op->productos
                        ->map(fn ($p) => $nombreProducto($p))
                        ->implode(', ');

                    // Nombres por producto para las tablas de detalle (tallas y recepción).
                    $nombresProductos = $op->productos->mapWithKeys(
                        fn ($p) => [$p->id => $nombreProducto($p)]
                    );

                    // Detalle por talla con su producto, para la recepción de calidad.
                    $op->distribuciones_detalle = $op->distribucionesPorTallas->map(function ($d) use ($nombreProducto, $nombresProductos) {
                        return [
                            'id'                   => $d->id,
                            'talla'                => $d->talla,
                            'cantidad_planificada' => $d->cantidad_planificada,
                            'producto_id'          => $d->producto_id,
                            'producto_nombre'      => $nombreProducto($d->producto)
                                ?? $nombresProductos[$d->producto_id]
                                ?? null,
                        ];
                    })->values()->all();

                    // Materiales del kit, con la necesidad total y la existencia
                    // actual. Se usan en el detalle al hacer clic en el estado.
                    $op->materiales_detalle = $op->kitProcesos->map(function ($kit) {
                        $requerida = (float) ($kit->cantidad_requerida ?? 0);
                        $disponible = (float) ($kit->material?->existencia_actual ?? 0);
                        $nombre = trim(
                            ($kit->material?->codigo_material ? $kit->material->codigo_material . ' — ' : '')
                            . ($kit->material?->nombre_base ?? '')
                        );

                        return [
                            'material_id' => $kit->material_id,
                            'nombre'      => $nombre !== '' ? $nombre : ('Material #' . $kit->material_id),
                            'unidad'      => $kit->unidad ?: ($kit->material?->unidad_inventario ?: 'u'),
                            'requerida'   => round($requerida, 4),
                            'disponible'  => round($disponible, 4),
                            'proveedor_id' => $kit->proveedor_id,
                        ];
                    })->values()->all();

                    $op->materiales_faltantes = collect($op->materiales_detalle)
                        ->map(fn ($fila) => $fila + [
                            'faltante' => round(max(0, (float) $fila['requerida'] - (float) $fila['disponible']), 4),
                        ])
                        ->filter(fn ($fila) => $fila['faltante'] > 0)
                        ->values()->all();

                    // Recepciones ya registradas, para precargar el formulario
                    // de calidad y para el detalle de solo lectura del estado.
                    $op->recepciones_detalle = $op->recepcionesCalidad->map(fn ($r) => [
                        'id'                      => $r->id,
                        'producto_id'             => $r->producto_referencia_id,
                        'producto_nombre'         => $nombresProductos[$r->producto_referencia_id]
                            ?? ((int) $r->producto_referencia_id === (int) $producto?->id ? $nombreProducto($producto) : null),
                        'talla'                   => $r->talla,
                        'cantidad_esperada'       => $r->cantidad_esperada,
                        'cantidad_recibida'       => $r->cantidad_recibida,
                        'cantidad_aprobada'       => $r->cantidad_aprobada,
                        'cantidad_en_reparacion'  => $r->cantidad_en_reparacion,
                        'cantidad_no_recuperable' => $r->cantidad_no_recuperable,
                    ])->values()->all();

                    // Procesos externos que todavía no han regresado: se usan
                    // al pasar la OP a "Lista para taller".
                    $op->procesos_externos_pendientes = $op->procesosExternos
                        ->filter(fn ($pe) => $pe->fecha_regreso_real === null)
                        ->map(fn ($pe) => [
                            'id'                      => $pe->id,
                            'material_nombre'         => $pe->materialOrigen?->nombre_base
                                ?: ('Material #' . $pe->material_origen_id),
                            'cantidad_salida'         => (float) ($pe->cantidad_salida ?? 0),
                            'unidad_medida'           => $pe->unidad_medida,
                            'proveedor_nombre'        => $pe->proveedorDestino?->nombre,
                            'fecha_salida'            => $pe->fecha_salida?->toDateTimeString(),
                            'fecha_prevista_regreso'  => $pe->fecha_prevista_regreso?->toDateString(),
                        ])->values()->all();

                    // Cierre registrado (borrador o definitivo) para precargar
                    // el formulario al seleccionar «Cerrada».
                    $cierre = $op->cierres->sortByDesc('id')->first();
                    $op->cierre_detalle = $cierre ? [
                        'id'                  => $cierre->id,
                        'materiales_sobrantes' => $cierre->materiales_sobrantes,
                        'perdidas_mermas'     => $cierre->perdidas_mermas,
                        'costo_real_final'    => $cierre->costo_real_final !== null ? (float) $cierre->costo_real_final : null,
                        'completado'          => (bool) $cierre->completado,
                    ] : null;

                    $op->taller_nombre = $op->taller?->nombre ?? ($op->taller_id ? '#' . $op->taller_id : '');
                    $op->empresa_externa_nombre = $op->empresaExterna?->nombre ?? ($op->empresa_externa_id ? '#' . $op->empresa_externa_id : '');
                    $op->estado_nombre = $op->estadoOp?->nombre ?? ($op->estado_op_id ? '#' . $op->estado_op_id : '');
                    $op->estado_secundario_nombre = $op->estadoOpSecundario?->nombre ?? null;
                    $op->responsable_nombre = $op->responsable?->name ?? ($op->user_id ? '#' . $op->user_id : '');
                    return $op;
                })
                ->values();
        });

        // Selects para los formularios (productos, talleres, estados, usuarios, grupos, siguiente OP)
        $losSelect = $this->obtenerSelects();

        // Último borrador pendiente (guardado con "Terminar después") para el botón "Continuar última OP"
        $borradorId = OrdenProduccion::where('estado_op_id', EstadoOperacion::where('nombre', 'Borrador')->value('id'))
            ->latest('updated_at')->value('id');
        $borradorUltimo = $borradorId
            ? OrdenProduccion::with(['productos:id,Titulo_Shopify,Codigo_interno', 'distribucionesPorTallas'])->find($borradorId)
            : null;

        return Inertia::render($this->FromController . '/Index', [
            'ordenes' => $OrdenProduccions,
            'total' => $OrdenProduccions->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'perPage' => 10,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $losSelect,
	        'titulos'           => OrdenProduccion::getFillableWithTypes(),
            'borradorUltimo'    => $borradorUltimo,
            // Si es verdadero, el cambio de estado a «En taller» muestra
            // cuánto se resta de inventario por material.
            'mostrarRestaMateriales' => Parametro::bool(Parametro::MOSTRAR_RESTA_MATERIALES_TALLER, true),

        ]);
    }

    /**
     * Versión del cache del listado: al incrementarse, todas las claves
     * anteriores dejan de usarse y se reconstruyen con datos frescos.
     */
    private function versionCache(): int {
        Cache::add('op_index_version', 1, now()->addDays(7));
        return (int) Cache::get('op_index_version', 1);
    }

    /**
     * Invalida el listado y los selects tras una escritura (create/update/delete).
     */
    private function invalidarCache(): void {
        Cache::increment('op_index_version');
        Cache::forget('op_selects');
    }

    /**
     * El campo `externa` (boolean) se mantiene sincronizado con `tipo`:
     * 'externa' => true y taller_id libre; 'taller' => false y sin empresa.
     * Si el request no envía tipo, los datos se dejan intactos.
     */
    private function normalizarTipo(array $data): array {
        if (!array_key_exists('tipo', $data) || !in_array($data['tipo'], ['taller', 'externa'], true)) {
            return $data;
        }

        $data['externa'] = $data['tipo'] === 'externa';
        if ($data['tipo'] === 'externa') {
            $data['taller_id'] = null;
            $data['empresa_externa_id'] = is_numeric($data['empresa_externa_id'] ?? null) ? (int) $data['empresa_externa_id'] : null;
        } else {
            $data['empresa_externa_id'] = null;
        }

        return $data;
    }

    public function obtenerSelects(): array {
        $usuarios = User::where('name', '!=', 'Superadmin')
            ->where('name', '!=', 'superadmin')
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'superadmin');
            })
            ->get(['id', 'name'])
            ->map(function ($user) {
                return [
                    'id'    => $user->id,
                    'value' => $user->id,
                    'name'  => $user->name,
                    'label' => $user->name,
                    'title' => $user->name,
                ];
            });

        $estadoOperaciones = EstadoOperacion::all(['id', 'nombre', 'puede_pasar_a', 'regla_clave'])
            ->map(function ($estado) {
                return [
                    'id'    => $estado->id,
                    'value' => $estado->id,
                    'name'  => $estado->nombre,
                    'label' => $estado->nombre,
                    'title' => $estado->nombre,
                    'permitidos' => $estado->idsPuedePasarA(),
                    'regla_clave' => $estado->regla_clave,
                ];
            });

        $productos = Producto::with(['fichaTecnica.materiales.material', 'distribucionesPorTallas'])->get(['id', 'Titulo_Shopify', 'Codigo_interno', 'Opciones'])
            ->map(function ($producto) {
                $nombre = $producto->Titulo_Shopify ?: ($producto->Codigo_interno ?: 'Producto #' . $producto->id);
                $ficha = $producto->fichaTecnica->first();
                return [
                    'id'    => $producto->id,
                    'value' => $producto->id,
                    'name'  => $nombre,
                    'label' => $nombre,
                    'title' => $nombre,
                    'Opciones' => $producto->Opciones,
                    'distribuciones_por_tallas' => $producto->distribucionesPorTallas->map(fn ($d) => [
                        'id' => $d->id,
                        'grupo' => $d->grupo,
                        'talla' => $d->talla,
                        'cantidad_planificada' => $d->cantidad_planificada,
                    ])->values()->all(),
                    'ficha' => $ficha ? ['nombre' => $ficha->nombre] : null,
                    'ficha_materiales' => $ficha?->materiales->map(fn ($fila) => [
                        'material_id'       => $fila->material_componente_id,
                        'nombre'            => trim(($fila->material?->codigo_material ? $fila->material->codigo_material . ' — ' : '') . $fila->material?->nombre_base),
                        'consumo_estandar'  => (float) $fila->consumo_estandar,
                        'unidad_medida'     => $fila->unidad_medida,
                        'costo_asociado_teorico' => (float) $fila->costo_asociado_teorico,
                        'existencia_actual' => (float) ($fila->material?->existencia_actual ?? 0),
                    ])->values()->all() ?? [],
                ];
            });

        $siguienteNumeroOp = (int) str_replace('OP-', '', OrdenProduccion::siguienteNumeroOp());

        $estadosMateriales = EstadoOpMaterial::all(['id', 'nombre'])
            ->map(function ($estado) {
                return [
                    'id'    => $estado->id,
                    'value' => $estado->id,
                    'name'  => $estado->nombre,
                    'label' => $estado->nombre,
                    'title' => $estado->nombre,
                ];
            });

        $talleres = Taller::all(['id', 'nombre'])
            ->map(function ($taller) {
                return [
                    'id'    => $taller->id,
                    'value' => $taller->id,
                    'name'  => $taller->nombre,
                    'label' => $taller->nombre,
                    'title' => $taller->nombre,
                ];
            });

        $empresasExternas = EmpresaExterna::all(['id', 'nombre'])
            ->map(function ($empresa) {
                return [
                    'id'    => $empresa->id,
                    'value' => $empresa->id,
                    'name'  => $empresa->nombre,
                    'label' => $empresa->nombre,
                    'title' => $empresa->nombre,
                ];
            });

        $proveedores = Proveedor::orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(function ($proveedor) {
                return [
                    'id'    => $proveedor->id,
                    'value' => $proveedor->id,
                    'name'  => $proveedor->nombre,
                    'label' => $proveedor->nombre,
                    'title' => $proveedor->nombre,
                ];
            });

        $gruposTallas = GrupoTalla::whereNotNull('valor')
            ->orderBy('valor')
            ->orderBy('id')
            ->get(['id', 'nombre', 'valor'])
            ->groupBy('valor')
            ->map(function ($filas, $valor) {
                $nombres = $filas->pluck('nombre')->filter()->implode(', ');
                $etiqueta = 'Grupo ' . $valor . ($nombres ? ' (' . $nombres . ')' : '');
                return [
                    'id'     => (int) $valor,
                    'value'  => (int) $valor,
                    'name'   => $etiqueta,
                    'label'  => $etiqueta,
                    'title'  => $etiqueta,
                    'tallas' => $filas->map(fn ($fila) => [
                        'id'                   => $fila->id,
                        'talla'                => $fila->nombre,
                        'cantidad_planificada' => null,
                    ])->values()->all(),
                ];
            })->values()->all();

        return [
            // 'responsable_id'         => $usuarios,
            // 'responsable'            => $usuarios,
            // 'estado_op_id'           => $estadoOperaciones,
            // 'estado_op'              => $estadoOperaciones,
            // 'producto_referencia_id' => $productos,
            // 'producto_referencia'    => $productos,
            // 'producto'               => $productos,
            // 'taller'                 => $talleres,
            // 'siguiente_numero_op'    => $siguienteNumeroOp,
            'responsable_id'         => $usuarios,
            'user_id'                => $usuarios,
            'estado_op_id'           => $estadoOperaciones,
            'producto_referencia_id' => $productos,
            'producto_id'            => $productos,
            'taller_id'              => $talleres,
            'tipo'                   => [
                ['id' => 'taller', 'value' => 'taller', 'name' => 'Taller', 'label' => 'Taller', 'title' => 'Taller'],
                ['id' => 'externa', 'value' => 'externa', 'name' => 'Externa', 'label' => 'Externa', 'title' => 'Externa'],
            ],
            'empresa_externa_id'     => $empresasExternas,
            'proveedor_destino_id'   => $proveedores,
            'grupos_tallas'          => $gruposTallas,
            'estado_op_material_id'  => $estadosMateriales,
            'siguiente_numero_op'    => $siguienteNumeroOp,
        ];
    }

	public function losSelect(string $modelClass, string $label, string $displayField = 'nombre'): array {
		if (!class_exists($modelClass)) {
			return [];
		}
		
		$modelCollection = call_user_func([$modelClass, 'all']);
		
		if (!$modelCollection instanceof Collection) {
			return [];
		}
		
		return [
			$label => Myhelp::NEW_turnInSelectID($modelCollection, $label.' ', $displayField),
		];
	}

    public function Filtros($request): Builder {
        $OrdenProduccions = OrdenProduccion::query()->with(['productoReferencia', 'taller', 'empresaExterna', 'estadoOp', 'estadoOpSecundario', 'responsable', 'distribucionesPorTallas', 'productos']);
        if ($request->has('search')) {
            $OrdenProduccions = $OrdenProduccions->where(function ($query) use ($request) {
                $query->where('numero_op', 'LIKE', "%" . $request->search . "%");
            });
        }

        if ($request->has(['field', 'order'])) {
            $OrdenProduccions = $OrdenProduccions->orderBy($request->field, $request->order);
        } else {
            $OrdenProduccions = $OrdenProduccions->orderBy('updated_at', 'DESC');
        }
        return $OrdenProduccions;
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

    public function PerPageAndPaginate($request, $OrdenProduccions) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $OrdenProduccions->forPage($page, $perPage),
            $OrdenProduccions->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(StoreOrdenProduccionRequest $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:OrdenProduccions');
        DB::beginTransaction();

        $data = $request->only(['cantidad_planificada', 'fecha_prevista_envio_taller', 'taller_id', 'tipo', 'empresa_externa_id', 'user_id', 'id_shopify_producto', 'observaciones', 'estado_op_material_id', 'externa']);
        $data['producto_referencia_id'] = $request->filled('producto_referencia_id') ? $request->input('producto_referencia_id') : null;
        $data['fecha_prevista_envio_taller'] = ($data['fecha_prevista_envio_taller'] ?? '') === '' ? null : $data['fecha_prevista_envio_taller'];
        $data['estado_op_material_id'] = ($data['estado_op_material_id'] ?? '') === '' ? null : $data['estado_op_material_id'];
        $data = $this->normalizarTipo($data);

        if (empty($data['user_id']) && $request->filled('responsable_id')) {
            $data['user_id'] = $request->input('responsable_id');
        }

        $data['estado_op_id'] = EstadoOperacion::where('nombre', 'Preparación')->value('id');

        // El número de OP se calcula siempre en el servidor a partir del mayor
        // registrado (+1) para impedir que dos OP compartan el mismo código.
        $data['numero_op'] = OrdenProduccion::siguienteNumeroOp();

        $OrdenProduccion = new OrdenProduccion();
        $OrdenProduccion->forceFill($data);
        $OrdenProduccion->save();

        $grupoTallas = $request->input('grupo_tallas');
        // Las tallas pueden venir de la plantilla del producto (con id) o ser
        // nuevas. En ambos casos se guarda la cantidad planificada que digitó
        // el usuario, para que la OP y su kit no queden en cero.
        $this->sincronizarTallas(
            $OrdenProduccion,
            $request->input('distribucion_tallas', []),
            true,
            $grupoTallas
        );

        $productosOp = array_map('intval', $request->input('productos', []));
        $OrdenProduccion->productos()->sync($productosOp);

        // Aviso de materiales insuficientes según la ficha técnica de cada producto
        $faltantes = $this->faltantesDeMateriales(
            $productosOp,
            (int) ($data['cantidad_planificada'] ?? 0),
            array_map('floatval', (array) $request->input('productos_cantidad', []))
        );
        if (!empty($faltantes)) {
            $detalle = collect($faltantes)
                ->map(fn ($f) => $f['nombre'] . ': faltan ' . round($f['faltante'], 2) . ' (necesita ' . round($f['necesita'], 2) . ', hay ' . round($f['hay'], 2) . ')')
                ->implode(' | ');
            app(\Alejo\Notificaciones\NotificacionesManager::class)->crear(
                'OP ' . ($data['numero_op'] ?? $OrdenProduccion->id) . ': material insuficiente — ' . $detalle,
                'alta',
                $data['user_id'] ?? null
            );
        }

        // El kit de procesos se genera automáticamente con la OP;
        // estado_requerido queda vacío hasta que el usuario lo digite.
        $this->sincronizarKitProceso(
            $OrdenProduccion,
            $productosOp,
            (int) ($data['cantidad_planificada'] ?? 0),
            array_map('floatval', (array) $request->input('productos_cantidad', []))
        );

        DB::commit();
        $this->invalidarCache();
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'STORE:OrdenProduccions EXITOSO', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $identificador]));
    }

    /**
     * Guarda la OP como Borrador ("Terminar después"): acepta datos parciales,
     * crea la orden si no existe id (con el número OP calculado en el servidor).
     */
    public function guardarBorrador(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin BORRADOR:OrdenProduccions');
        $request->validate([
            'id' => ['nullable', 'integer', 'exists:orden_produccions,id'],
            'numero_op' => ['nullable', 'string', 'regex:/^OP-\d+$/'],
            'cantidad_planificada' => ['nullable', 'numeric', 'min:0'],
            'fecha_prevista_envio_taller' => ['nullable', 'date'],
            'taller_id' => ['nullable', 'integer', 'exists:tallers,id'],
            'tipo' => ['nullable', 'in:taller,externa'],
            'empresa_externa_id' => ['nullable', 'integer', 'exists:empresa_externas,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'id_shopify_producto' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'estado_op_material_id' => ['nullable', 'integer', 'exists:estado_op_materials,id'],
            'externa' => ['nullable', 'boolean'],
            'productos' => ['nullable', 'array'],
            'productos.*' => ['integer', 'exists:productos,id'],
            'distribucion_tallas' => ['nullable', 'array'],
            'distribucion_tallas.*.id' => ['nullable', 'integer'],
            'distribucion_tallas.*.talla' => ['nullable', 'string', 'max:255'],
            'distribucion_tallas.*.cantidad_planificada' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();

        $data = $request->only(['numero_op', 'cantidad_planificada', 'fecha_prevista_envio_taller', 'taller_id', 'tipo', 'empresa_externa_id', 'user_id', 'id_shopify_producto', 'observaciones', 'estado_op_material_id', 'externa']);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data = $this->normalizarTipo($data);
        $data['estado_op_id'] = EstadoOperacion::where('nombre', 'Borrador')->value('id');
        if (empty($data['cantidad_planificada'])) {
            $data['cantidad_planificada'] = 0;
        }
        if (empty($data['user_id']) && $request->filled('responsable_id')) {
            $data['user_id'] = $request->input('responsable_id');
        }
        if (empty($data['user_id']) && $request->user()) {
            $data['user_id'] = $request->user()->id;
        }

        $OrdenProduccion = $request->filled('id')
            ? OrdenProduccion::findOrFail((int) $request->input('id'))
            : new OrdenProduccion();

        if (!$OrdenProduccion->exists) {
            // OP nueva: el número se calcula en el servidor (mayor registrado + 1)
            // y no se confía en el valor del formulario, para no repetir códigos.
            $data['numero_op'] = OrdenProduccion::siguienteNumeroOp();
        } elseif (!empty($data['numero_op'])
            && $data['numero_op'] !== $OrdenProduccion->numero_op
            && OrdenProduccion::where('numero_op', $data['numero_op'])->whereKeyNot($OrdenProduccion->id)->exists()) {
            DB::rollBack();
            return back()->withErrors(['numero_op' => 'Ya existe una OP con el número ' . $data['numero_op'] . '.']);
        }

        $OrdenProduccion->forceFill($data);
        $OrdenProduccion->save();

        if ($request->has('distribucion_tallas')) {
            $this->sincronizarTallas($OrdenProduccion, $request->input('distribucion_tallas', []), true, $request->input('grupo_tallas'));
        }

        if ($request->has('productos')) {
            $OrdenProduccion->productos()->sync(array_map('intval', (array) $request->input('productos', [])));
        }

        DB::commit();
        $this->invalidarCache();
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'BORRADOR:OrdenProduccions EXITOSO', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador, false);
        return back()->with('success', 'Borrador guardado: ' . $identificador . '. Puede continuarla desde el botón "Continuar última OP".');
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:OrdenProduccions');
        DB::beginTransaction();

        $data = $request->only(['numero_op', 'cantidad_planificada', 'fecha_prevista_envio_taller', 'taller_id', 'tipo', 'empresa_externa_id', 'user_id', 'id_shopify_producto', 'observaciones', 'estado_op_material_id', 'externa']);
        $data['producto_referencia_id'] = $request->filled('producto_referencia_id') ? $request->input('producto_referencia_id') : null;
        $data['fecha_prevista_envio_taller'] = ($data['fecha_prevista_envio_taller'] ?? '') === '' ? null : $data['fecha_prevista_envio_taller'];
        $data['estado_op_material_id'] = ($data['estado_op_material_id'] ?? '') === '' ? null : $data['estado_op_material_id'];
        $data = $this->normalizarTipo($data);

        if (empty($data['user_id']) && $request->filled('responsable_id')) {
            $data['user_id'] = $request->input('responsable_id');
        }
        $OrdenProduccion = OrdenProduccion::findOrFail($id);

        if (!empty($data['numero_op'])
            && $data['numero_op'] !== $OrdenProduccion->numero_op
            && OrdenProduccion::where('numero_op', $data['numero_op'])->whereKeyNot($OrdenProduccion->id)->exists()) {
            DB::rollBack();
            return back()->withErrors(['numero_op' => 'Ya existe una OP con el número ' . $data['numero_op'] . '.']);
        }

        $eraBorrador = $OrdenProduccion->estadoOp?->nombre === 'Borrador';

        $OrdenProduccion->forceFill($data);
        $OrdenProduccion->save();

        // Al continuar un borrador guardado con "Terminar después", la orden pasa a Preparación
        if ($eraBorrador) {
            $OrdenProduccion->forceFill([
                'estado_op_id' => EstadoOperacion::where('nombre', 'Preparación')->value('id'),
            ])->save();
        }

        if ($request->has('distribucion_tallas')) {
            $this->sincronizarTallas($OrdenProduccion, $request->input('distribucion_tallas', []), true, $request->input('grupo_tallas'));
        }

        $productosOp = $request->has('productos')
            ? array_map('intval', (array) $request->input('productos', []))
            : $OrdenProduccion->productos()->pluck('productos.id')->all();

        if ($request->has('productos')) {
            $OrdenProduccion->productos()->sync($productosOp);
        }

        // Si la OP cambia de productos o cantidades, el kit se mantiene
        // sincronizado: crea las filas faltantes y actualiza las cantidades.
        $this->sincronizarKitProceso(
            $OrdenProduccion,
            $productosOp,
            (int) ($data['cantidad_planificada'] ?? $OrdenProduccion->cantidad_planificada ?? 0),
            array_map('floatval', (array) $request->input('productos_cantidad', []))
        );

        DB::commit();
        $this->invalidarCache();
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'UPDATE:OrdenProduccions EXITOSO', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $identificador]));
    }

    /**
     * Necesidad agregada de materiales: consumo_estandar de la ficha × cantidad
     * del producto. Si no viene el mapa de cantidades por producto se usa la
     * cantidad planificada total de la OP para cada producto.
     *
     * @return array<int, array{nombre:string, unidad:string, necesita:float, hay:float}>
     */
    private function necesidadMateriales(array $productoIds, int $cantidadPlanificada, array $cantidadesPorProducto = []): array {
        if (empty($productoIds)) {
            return [];
        }

        $necesidad = [];
        $productos = Producto::with('fichaTecnica.materiales.material')->whereIn('id', $productoIds)->get();
        foreach ($productos as $producto) {
            $cantidad = array_key_exists($producto->id, $cantidadesPorProducto)
                ? (float) $cantidadesPorProducto[$producto->id]
                : ($cantidadesPorProducto ? 0 : $cantidadPlanificada);
            if ($cantidad <= 0) {
                continue;
            }
            $ficha = $producto->fichaTecnica->first();
            if (!$ficha) {
                continue;
            }
            foreach ($ficha->materiales as $fila) {
                $id = $fila->material_componente_id;
                if (!$id) {
                    continue;
                }
                if (!isset($necesidad[$id])) {
                    $necesidad[$id] = [
                        'nombre'   => trim(($fila->material?->codigo_material ? $fila->material->codigo_material . ' — ' : '') . $fila->material?->nombre_base),
                        'unidad'   => $fila->unidad_medida ?: ($fila->material?->unidad_inventario ?: 'u'),
                        'necesita' => 0,
                        'hay'      => (float) ($fila->material?->existencia_actual ?? 0),
                    ];
                }
                $necesidad[$id]['necesita'] += (float) $fila->consumo_estandar * $cantidad;
            }
        }

        return $necesidad;
    }

    /**
     * Materiales cuya necesidad supera la existencia actual en inventario.
     */
    private function faltantesDeMateriales(array $productoIds, int $cantidadPlanificada, array $cantidadesPorProducto = []): array {
        return collect($this->necesidadMateriales($productoIds, $cantidadPlanificada, $cantidadesPorProducto))
            ->map(fn ($fila) => $fila + ['faltante' => max(0, $fila['necesita'] - $fila['hay'])])
            ->filter(fn ($fila) => $fila['faltante'] > 0)
            ->values()->all();
    }

    /**
     * Sincroniza el KitProceso de la OP: una fila por material de la ficha
     * técnica, con estado_requerido y proveedor vacíos hasta que el usuario
     * los defina. Al editar una OP se actualizan las cantidades y se crean
     * las filas que falten, sin pisar el trabajo ya registrado.
     */
    private function sincronizarKitProceso(OrdenProduccion $op, array $productoIds, int $cantidadPlanificada, array $cantidadesPorProducto = []): void {
        if (empty($productoIds)) {
            return;
        }

        $necesidad = $this->necesidadMateriales($productoIds, $cantidadPlanificada, $cantidadesPorProducto);
        if (empty($necesidad)) {
            return;
        }

        foreach ($necesidad as $materialId => $fila) {
            $kit = KitProceso::firstOrNew([
                'orden_produccion_id' => $op->id,
                'material_id' => $materialId,
            ]);

            $kit->unidad = $fila['unidad'];
            $kit->cantidad_requerida = round($fila['necesita'], 4);

            // La disponibilidad siempre refleja la existencia actual del
            // material; el faltante lo calcula el modelo al guardar.
            $kit->cantidad_disponible = (float) ($fila['hay'] ?? 0);

            if (!$kit->exists) {
                $kit->estado_requerido = null;
                $kit->proveedor_id = null;
                $kit->listo = false;
            }

            $kit->save();
        }

        // Si la OP dejó de requerir un material, se elimina su fila solo
        // cuando todavía no tiene trabajo registrado.
        $sobrantes = KitProceso::where('orden_produccion_id', $op->id)
            ->whereNotIn('material_id', array_keys($necesidad))
            ->get();

        foreach ($sobrantes as $kit) {
            if (empty($kit->estado_requerido)
                && empty($kit->proveedor_id)
                && empty($kit->cantidad_preparada)
                && empty($kit->observaciones)) {
                $kit->delete();
            }
        }
    }

    /**
     * Cambia exclusivamente el estado de una OP, validando la transición
     * contra la columna puede_pasar_a del estado actual.
     *
     * Al pasar a "Recibiendo" (o al actualizar una OP que ya está en ese estado)
     * se genera/actualiza un registro de recepción por cada talla: la talla, su
     * producto y lo esperado salen de la OP, y la suma de aprobada + en
     * reparación + no recuperable debe cuadrar con esa cantidad (esa suma es la
     * cantidad recibida).
     *
     * Si la suma de pares en reparación queda en cero, la OP pasa
     * automáticamente a "Pendiente" en la misma operación.
     */
    /** Único par de estados que puede coexistir en una OP al mismo tiempo. */
    private const PAR_COEXISTENCIA = ['Esperando materiales', 'Procesos externos'];

    public function cambiarEstado(Request $request, $id): RedirectResponse {
        $datos = $request->validate([
            'estado_op_id' => ['required', 'integer', 'exists:estado_operacions,id'],
            'estado_op_secundario_id' => ['nullable', 'integer', 'exists:estado_operacions,id', 'different:estado_op_id'],
            'recepciones' => ['nullable', 'array'],
            'recepciones.*.distribucion_id' => ['required', 'integer'],
            'recepciones.*.cantidad_aprobada' => ['nullable', 'integer', 'min:0'],
            'recepciones.*.cantidad_en_reparacion' => ['nullable', 'integer', 'min:0'],
            'recepciones.*.cantidad_no_recuperable' => ['nullable', 'integer', 'min:0'],
            'proceso_externo' => ['nullable', 'array'],
            'proceso_externo.proveedor_destino_id' => ['nullable', 'integer', 'exists:proveedors,id'],
            'proceso_externo.etapa_componente' => ['nullable', 'string', 'max:255'],
            'proceso_externo.bodega_origen' => ['nullable', 'string', 'max:255'],
            'proceso_externo.servicio_proceso' => ['nullable', 'string', 'max:255'],
            'proceso_externo.fecha_prevista_regreso' => ['nullable', 'date'],
            'proceso_externo.resultado_observaciones' => ['nullable', 'string'],
            'proceso_externo.items' => ['nullable', 'array'],
            'proceso_externo.items.*.material_origen_id' => ['required', 'integer', 'exists:materials,id'],
            'proceso_externo.items.*.cantidad_salida' => ['nullable', 'numeric', 'gt:0'],
            'proceso_externo.items.*.unidad_medida' => ['nullable', 'string', 'max:255'],
            'fecha_regreso_real' => ['nullable', 'date'],
            'cierre' => ['nullable', 'array'],
            'cierre.materiales_sobrantes' => ['nullable', 'integer', 'min:0'],
            'cierre.perdidas_mermas' => ['nullable', 'integer', 'min:0'],
            'cierre.costo_real_final' => ['nullable', 'numeric', 'min:0'],
        ]);

        $OrdenProduccion = OrdenProduccion::findOrFail($id);
        $estadoActual = $OrdenProduccion->estadoOp;
        $permitidos = $estadoActual?->idsPuedePasarA() ?? [];
        $estadoSolicitadoId = (int) $datos['estado_op_id'];

        // Reenviar el estado actual es válido: permite ajustar solo el estado secundario.
        $esMismoEstado = $estadoSolicitadoId === (int) $OrdenProduccion->estado_op_id;

        if (! $esMismoEstado && ! in_array($estadoSolicitadoId, $permitidos, true)) {
            return back()->withErrors([
                'estado_op_id' => "Transición no permitida desde el estado '{$estadoActual?->nombre}'.",
            ]);
        }

        $nuevoEstado = EstadoOperacion::find($estadoSolicitadoId);
        $secundarioId = $datos['estado_op_secundario_id'] ?? null;
        $secundarioNombre = $secundarioId
            ? EstadoOperacion::whereKey($secundarioId)->value('nombre')
            : null;

        // Coexistencia: solo "Esperando materiales" + "Procesos externos".
        if ($secundarioId) {
            if (! in_array($nuevoEstado?->nombre, self::PAR_COEXISTENCIA, true)
                || ! in_array($secundarioNombre, self::PAR_COEXISTENCIA, true)
                || $secundarioNombre === $nuevoEstado?->nombre) {
                return back()->withErrors([
                    'estado_op_secundario_id' => 'Solo «Esperando materiales» y «Procesos externos» pueden coexistir como estados simultáneos.',
                ]);
            }
        }

        // Se registra el proceso externo solo al entrar a ese estado, no en
        // cada edición posterior de una OP que ya está en procesos externos.
        $estadoSecundarioActualNombre = $OrdenProduccion->estadoOpSecundario?->nombre;
        $estabaEnProcesoExterno = $estadoActual?->nombre === 'Procesos externos'
            || $estadoSecundarioActualNombre === 'Procesos externos';
        $estaraEnProcesoExterno = $nuevoEstado?->nombre === 'Procesos externos'
            || $secundarioNombre === 'Procesos externos';
        $entraProcesoExterno = $estaraEnProcesoExterno && ! $estabaEnProcesoExterno;

        $procesoExternoDatos = $datos['proceso_externo'] ?? [];
        $itemsProceso = collect($procesoExternoDatos['items'] ?? [])
            ->filter(fn ($item) => !empty($item['material_origen_id']))
            ->unique(fn ($item) => (int) $item['material_origen_id'])
            ->values();

        if ($entraProcesoExterno) {
            if (empty($procesoExternoDatos['proveedor_destino_id']) || $itemsProceso->isEmpty()) {
                return back()->withErrors([
                    'proceso_externo' => 'Registre el proveedor destino y al menos un material para el proceso externo.',
                ]);
            }

            if (empty(trim((string) ($procesoExternoDatos['etapa_componente'] ?? '')))) {
                return back()->withErrors([
                    'proceso_externo.etapa_componente' => 'Indique la etapa o componente del proceso externo.',
                ]);
            }
        }

        // La recepción se muestra/actualiza cuando la OP ya está recibiendo o
        // cuando se selecciona "Recibiendo" desde cualquier estado permitido.
        $esRecepcion = $estadoActual?->nombre === 'Recibiendo'
            || $nuevoEstado?->nombre === 'Recibiendo';

        $recepciones = $esRecepcion
            ? $this->recepcionesDeCalidad($OrdenProduccion, $datos['recepciones'] ?? [])
            : [];

        $totalEnReparacion = collect($recepciones)
            ->sum(fn ($fila) => (int) ($fila['cantidad_en_reparacion'] ?? 0));

        // Si la OP no se está recibiendo en este momento, los pares en
        // reparación se consultan en las recepciones ya guardadas.
        $totalEnReparacionEfectivo = $esRecepcion && !empty($recepciones)
            ? (int) $totalEnReparacion
            : (int) $OrdenProduccion->recepcionesCalidad()
                ->where('cantidad_en_reparacion', '>', 0)
                ->sum('cantidad_en_reparacion');

        // Una OP no puede cerrarse mientras queden pares en reparación.
        if ($nuevoEstado?->nombre === 'Cerrada' && $totalEnReparacionEfectivo > 0) {
            return back()->withErrors([
                'estado_op_id' => "No se puede cerrar la OP: aún hay {$totalEnReparacionEfectivo} par(es) en reparación.",
            ]);
        }

        // Si no quedó ningún par en reparación, "Recibiendo" pasa solo a
        // "Pendiente"; un estado elegido explícitamente (p. ej. "Cerrada") no
        // se sobrescribe.
        $estadoFinal = $nuevoEstado;
        $pasaAPendiente = false;
        if ($esRecepcion && $nuevoEstado?->nombre === 'Recibiendo' && $totalEnReparacion === 0) {
            $estadoPendiente = EstadoOperacion::where('nombre', 'Pendiente')->first();
            $puedePasarAPendiente = $estadoPendiente
                && (
                    (int) $estadoPendiente->id === (int) $OrdenProduccion->estado_op_id
                    || in_array((int) $estadoPendiente->id, $permitidos, true)
                );

            if ($puedePasarAPendiente) {
                $estadoFinal = $estadoPendiente;
                $pasaAPendiente = true;
            }
        }

        $estadoFinalId = (int) ($estadoFinal?->id ?? $estadoSolicitadoId);
        $estadoFinalNombre = $estadoFinal?->nombre ?? $nuevoEstado?->nombre;
        $recepcionesGeneradas = 0;
        $procesosCreados = 0;
        $materialesDescontados = 0;

        // Entrar a "En taller" saca el kit de bodega: se descuenta inventario
        // una sola vez (reenviar el mismo estado no vuelve a restar).
        $entraEnTaller = $estadoFinalNombre === 'En taller' && $estadoActual?->nombre !== 'En taller';

        // Cerrar la OP exige los tres datos del cierre.
        $esCierre = $estadoFinalNombre === 'Cerrada';
        $cierreDatos = $datos['cierre'] ?? [];

        if ($esCierre) {
            $faltantes = [];
            foreach ([
                'materiales_sobrantes' => 'materiales sobrantes o recuperados',
                'perdidas_mermas'      => 'pérdidas en mermas (0 si no hubo)',
                'costo_real_final'     => 'costo real final',
            ] as $campo => $etiqueta) {
                if (!isset($cierreDatos[$campo]) || $cierreDatos[$campo] === '' || $cierreDatos[$campo] === null) {
                    $faltantes[] = $etiqueta;
                }
            }

            if (!empty($faltantes)) {
                return back()->withErrors([
                    'cierre' => 'Complete el cierre antes de cerrar la OP: falta ' . implode(', ', $faltantes) . '.',
                ]);
            }
        }

        // Materiales del kit que se registrarán como salida al proceso externo.
        $kitsProceso = $entraProcesoExterno
            ? $OrdenProduccion->kitProcesos()->with('material')->get()
                ->keyBy(fn ($kit) => (int) $kit->material_id)
            : collect();

        // "Lista para taller" cierra los procesos externos pendientes: es
        // obligatorio indicar cuándo regresó el material.
        if ($estadoFinalNombre === 'Lista para taller'
            && empty($datos['fecha_regreso_real'])
            && ProcesoExterno::where('orden_produccion_id', $OrdenProduccion->id)
                ->whereNull('fecha_regreso_real')
                ->exists()) {
            return back()->withErrors([
                'fecha_regreso_real' => 'Indique la fecha real de regreso de los procesos externos.',
            ]);
        }

        // "Desde cuándo está en recepción": se fija al entrar y se conserva
        // mientras la OP siga en ese estado; se limpia al salir.
        $recepcionDesde = $estadoFinal?->nombre === 'Recibiendo'
            ? ($OrdenProduccion->recepcion_desde ?: now())
            : null;

        DB::transaction(function () use (
            $OrdenProduccion,
            $estadoFinalId,
            $secundarioId,
            $recepciones,
            $recepcionDesde,
            $entraProcesoExterno,
            $procesoExternoDatos,
            $itemsProceso,
            $kitsProceso,
            $estadoFinalNombre,
            $datos,
            $entraEnTaller,
            $esCierre,
            $cierreDatos,
            &$recepcionesGeneradas,
            &$procesosCreados,
            &$materialesDescontados
        ) {
            if ($entraEnTaller) {
                $materialesDescontados = $this->descontarMaterialesTaller($OrdenProduccion);
            }

            $OrdenProduccion->forceFill([
                'estado_op_id' => $estadoFinalId,
                'estado_op_secundario_id' => $secundarioId,
                'recepcion_desde' => $recepcionDesde,
            ])->save();

            if ($esCierre) {
                CierreOrdenProduccion::updateOrCreate(
                    ['orden_produccion_id' => $OrdenProduccion->id],
                    [
                        'materiales_sobrantes' => (int) $cierreDatos['materiales_sobrantes'],
                        'perdidas_mermas'      => (int) $cierreDatos['perdidas_mermas'],
                        'costo_real_final'     => round((float) $cierreDatos['costo_real_final'], 2),
                        'completado'           => true,
                        'estado_accion'        => 'cerrado',
                        'control_cierre'       => 'Cierre de OP',
                        'responsable_id'       => Auth::id() ?: $OrdenProduccion->user_id,
                        'fecha_cierre'         => now(),
                    ]
                );
            }

            if (!empty($recepciones)) {
                $recepcionesGeneradas = RecepcionCalidad::generarDesdeOrden($OrdenProduccion, $recepciones);
            }

            if ($entraProcesoExterno) {
                $fechaSalida = now();

                foreach ($itemsProceso as $item) {
                    $materialId = (int) $item['material_origen_id'];
                    $kit = $kitsProceso->get($materialId);

                    if (!$kit) {
                        throw ValidationException::withMessages([
                            'proceso_externo' => 'Uno de los materiales seleccionados no pertenece al kit de la OP.',
                        ]);
                    }

                    // La cantidad sale de la OP (kit), no de lo que digite el usuario.
                    $cantidad = round((float) ($kit->cantidad_requerida ?? 0), 4);
                    if ($cantidad <= 0) {
                        continue;
                    }

                    ProcesoExterno::create([
                        'orden_produccion_id'     => $OrdenProduccion->id,
                        'etapa_componente'        => $procesoExternoDatos['etapa_componente'] ?? null,
                        'material_origen_id'      => $materialId,
                        'cantidad_salida'         => $cantidad,
                        'unidad_medida'           => $item['unidad_medida']
                            ?? $kit->unidad
                            ?? $kit->material?->unidad_inventario,
                        'proveedor_destino_id'    => (int) $procesoExternoDatos['proveedor_destino_id'],
                        'bodega_origen'           => $procesoExternoDatos['bodega_origen'] ?? null,
                        'servicio_proceso'        => $procesoExternoDatos['servicio_proceso'] ?? null,
                        'fecha_salida'            => $fechaSalida,
                        'fecha_prevista_regreso'  => $procesoExternoDatos['fecha_prevista_regreso'] ?? null,
                        'resultado_observaciones' => $procesoExternoDatos['resultado_observaciones'] ?? null,
                    ]);

                    $procesosCreados++;
                }

                if ($procesosCreados === 0) {
                    throw ValidationException::withMessages([
                        'proceso_externo' => 'Los materiales seleccionados no tienen cantidad pendiente para salir.',
                    ]);
                }
            }

            if ($estadoFinalNombre === 'Lista para taller' && !empty($datos['fecha_regreso_real'])) {
                ProcesoExterno::where('orden_produccion_id', $OrdenProduccion->id)
                    ->whereNull('fecha_regreso_real')
                    ->update(['fecha_regreso_real' => $datos['fecha_regreso_real']]);
            }
        });

        $this->invalidarCache();

        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'CAMBIAR-ESTADO:OrdenProduccions', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador . ' | ' . ($estadoActual?->nombre ?? '?') . ' -> ' . ($estadoFinalNombre ?? '?') . ($pasaAPendiente ? ' (automático)' : ''), false);

        $mensaje = __('app.label.updated_successfully2', ['nombre' => $identificador]);
        if ($recepcionesGeneradas > 0) {
            $mensaje .= ' Se generaron ' . $recepcionesGeneradas . ' registros de recepción de calidad.';
        }
        if ($procesosCreados > 0) {
            $mensaje .= ' Se registraron ' . $procesosCreados . ' materiales en proceso externo.';
        }
        if ($materialesDescontados > 0) {
            $mensaje .= ' Se descontaron ' . $materialesDescontados . ' materiales del inventario para el taller.';
        }
        if ($esCierre) {
            $mensaje .= ' La OP quedó cerrada con su cierre registrado.';
        }
        if ($pasaAPendiente) {
            $mensaje .= ' La OP pasó automáticamente a «Pendiente» porque no quedaron pares en reparación.';
        }

        return back()->with('success', $mensaje);
    }

    /**
     * Guarda un avance del cierre de la OP sin cambiar su estado.
     * Permite dejar el formulario incompleto y terminarlo antes de cerrar.
     */
    public function guardarCierre(Request $request, $id): RedirectResponse {
        $datos = $request->validate([
            'materiales_sobrantes' => ['nullable', 'integer', 'min:0'],
            'perdidas_mermas'      => ['nullable', 'integer', 'min:0'],
            'costo_real_final'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $orden = OrdenProduccion::findOrFail($id);

        if ($orden->estadoOp?->nombre === 'Cerrada') {
            return back()->withErrors(['cierre' => 'La OP ya está cerrada.']);
        }

        CierreOrdenProduccion::updateOrCreate(
            ['orden_produccion_id' => $orden->id],
            [
                'materiales_sobrantes' => $datos['materiales_sobrantes'] ?? null,
                'perdidas_mermas'      => $datos['perdidas_mermas'] ?? null,
                'costo_real_final'     => $datos['costo_real_final'] ?? null,
                'completado'           => false,
                'estado_accion'        => 'borrador',
                'control_cierre'       => 'Cierre de OP',
                'responsable_id'       => Auth::id() ?: $orden->user_id,
                'fecha_cierre'         => null,
            ]
        );

        $this->invalidarCache();

        $identificador = $orden->numero_op ?? $orden->id;
        Myhelp::EscribirEnLog($this, 'CIERRE-BORRADOR:OrdenProduccions', 'OrdenProduccion id:' . $orden->id . ' | ' . $identificador, false);

        return back()->with('success', 'Avance del cierre guardado. Esta información se conservará cuando cierre la OP.');
    }

    /**
     * Al pasar a «En taller» el kit sale de bodega: se exige que lo preparado
     * sea igual a lo requerido, se valida la existencia y se descuenta el
     * inventario dejando un MaterialMovimiento de salida por material.
     *
     * @throws ValidationException si alguna cantidad no cuadra o falta stock
     */
    private function descontarMaterialesTaller(OrdenProduccion $orden): int {
        $kits = $orden->kitProcesos()->with('material')->orderBy('id')->get();
        if ($kits->isEmpty()) {
            return 0;
        }

        $descuadres = [];
        foreach ($kits as $kit) {
            $requerida = round((float) ($kit->cantidad_requerida ?? 0), 2);
            $preparada = round((float) ($kit->cantidad_preparada ?? 0), 2);

            if (abs($preparada - $requerida) > 0.004) {
                $nombre = $kit->material?->nombre_base ?: ('Material #' . $kit->material_id);
                $descuadres[] = $nombre . ' (requerida ' . $requerida . ', preparada ' . $preparada . ')';
            }
        }

        if (!empty($descuadres)) {
            throw ValidationException::withMessages([
                'estado_op_id' => 'No se puede pasar a «En taller»: la cantidad preparada debe ser igual a la requerida — '
                    . implode(' | ', $descuadres),
            ]);
        }

        $motivo = 'Salida de materiales a taller — OP ' . ($orden->numero_op ?? $orden->id);
        $sinStock = [];
        $descontados = 0;

        foreach ($kits as $kit) {
            $cantidad = round((float) ($kit->cantidad_requerida ?? 0), 2);
            if ($cantidad <= 0) {
                continue;
            }

            $material = Material::whereKey($kit->material_id)->lockForUpdate()->first();
            if (!$material) {
                $sinStock[] = 'Material #' . $kit->material_id . ' no existe';
                continue;
            }

            $existencia = (float) ($material->existencia_actual ?? 0);
            if ($existencia < $cantidad) {
                $sinStock[] = ($material->nombre_base ?: 'Material #' . $material->id)
                    . ': se necesitan ' . $cantidad . ($material->unidad_inventario ? ' ' . $material->unidad_inventario : '')
                    . ', solo hay ' . $existencia;
                continue;
            }

            $nueva = round($existencia - $cantidad, 2);
            $material->update(['existencia_actual' => $nueva]);

            $this->registrarMovimientoExistencia($material, $existencia, $nueva, 'salida', $motivo);
            $descontados++;
        }

        if (!empty($sinStock)) {
            throw ValidationException::withMessages([
                'estado_op_id' => 'No se puede pasar a «En taller»: inventario insuficiente — ' . implode(' | ', $sinStock),
            ]);
        }

        return $descontados;
    }

    /**
     * Valida y normaliza las filas de recepción enviadas desde el modal.
     * Cada fila corresponde a una talla distribuida en la OP; la suma de las
     * tres cantidades de calidad debe ser igual a la cantidad esperada de esa
     * talla, y el producto debe pertenecer a la OP.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recepcionesDeCalidad(OrdenProduccion $orden, array $filas): array {
        $distribuciones = $orden->distribucionesPorTallas()->get()->keyBy('id');
        if ($distribuciones->isEmpty()) {
            return [];
        }

        $productosOp = $orden->productos()->pluck('productos.id')->map(fn ($id) => (int) $id)->all();
        $normalizadas = [];
        $vistas = [];

        foreach ($filas as $fila) {
            $distribucion = $distribuciones->get((int) ($fila['distribucion_id'] ?? 0));
            if (!$distribucion) {
                throw ValidationException::withMessages([
                    'recepciones' => 'Una de las filas de recepción no pertenece a esta orden de producción.',
                ]);
            }

            // El producto sale de la distribución de la talla; no se le pregunta al usuario.
            $productoId = (int) ($distribucion->producto_id ?? 0)
                ?: (int) ($orden->producto_referencia_id ?? 0)
                ?: (count($productosOp) === 1 ? (int) $productosOp[0] : 0);
            if (!empty($productosOp) && !in_array($productoId, $productosOp, true)) {
                throw ValidationException::withMessages([
                    'recepciones' => "La talla {$distribucion->talla} no tiene un producto asociado en la OP.",
                ]);
            }

            $aprobada = (int) ($fila['cantidad_aprobada'] ?? 0);
            $reparacion = (int) ($fila['cantidad_en_reparacion'] ?? 0);
            $noRecuperable = (int) ($fila['cantidad_no_recuperable'] ?? 0);
            $suma = $aprobada + $reparacion + $noRecuperable;
            $esperada = (int) $distribucion->cantidad_planificada;

            if ($suma !== $esperada) {
                throw ValidationException::withMessages([
                    'recepciones' => "Talla {$distribucion->talla}: aprobada + en reparación + no recuperable suma {$suma} y debe ser {$esperada}.",
                ]);
            }

            $clave = $productoId . '|' . $distribucion->talla;
            if (isset($vistas[$clave])) {
                throw ValidationException::withMessages([
                    'recepciones' => "La talla {$distribucion->talla} está repetida para el mismo producto.",
                ]);
            }
            $vistas[$clave] = true;

            $normalizadas[$distribucion->id] = [
                'producto_id' => $productoId ?: null,
                'talla' => $distribucion->talla,
                'cantidad_esperada' => $esperada,
                'cantidad_recibida' => $suma,
                'cantidad_aprobada' => $aprobada,
                'cantidad_en_reparacion' => $reparacion,
                'cantidad_no_recuperable' => $noRecuperable,
            ];
        }

        foreach ($distribuciones as $distribucion) {
            if (!isset($normalizadas[$distribucion->id])) {
                throw ValidationException::withMessages([
                    'recepciones' => "Falta registrar la recepción de la talla {$distribucion->talla}.",
                ]);
            }
        }

        return array_values($normalizadas);
    }

    public function syncDistribucion(Request $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin SYNC-DISTRIBUCION:OrdenProduccions');
        DB::beginTransaction();

        $OrdenProduccion = OrdenProduccion::findOrFail($id);
        $this->sincronizarTallas($OrdenProduccion, $request->input('distribucion_tallas', []), false);

        DB::commit();
        $this->invalidarCache();
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'SYNC-DISTRIBUCION:OrdenProduccions EXITOSO', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $identificador]));
    }

    /**
     * Sincroniza las tallas de una orden vía pivote: actualiza las que traen id,
     * crea+vincula las nuevas y desvincula (sin borrar) las que ya no vienen.
     * Con $conPlanificada en true también actualiza cantidad_planificada.
     */
    private function sincronizarTallas(OrdenProduccion $orden, array $tallas, bool $conPlanificada, $grupoNuevo = null): array {
        $conservados = [];
        $aEnteroONulo = function ($valor) {
            if ($valor === '' || $valor === null) {
                return null;
            }
            return (int) $valor;
        };
        foreach ($tallas as $talla) {
            if (!empty($talla['id'])) {
                $distribucion = Distribucion_por_tallas::find($talla['id']);
                if ($distribucion) {
                    $datos = [
                        'talla' => $talla['talla'] ?? $distribucion->talla,
                        'cantidad_recibida_buena' => $aEnteroONulo($talla['cantidad_recibida_buena'] ?? null),
                        'cantidad_pendiente_reparacion' => $aEnteroONulo($talla['cantidad_pendiente_reparacion'] ?? null),
                    ];
                    if ($conPlanificada && array_key_exists('cantidad_planificada', $talla)) {
                        $datos['cantidad_planificada'] = (int) ($talla['cantidad_planificada'] ?? 0);
                    }
                    if (empty($distribucion->producto_id) && !empty($talla['producto_id'])) {
                        $datos['producto_id'] = (int) $talla['producto_id'];
                    }
                    $distribucion->update($datos);
                    $conservados[] = $distribucion->id;
                }
                continue;
            }
            if (empty($talla['talla']) && empty($talla['cantidad_planificada']) && empty($talla['cantidad_recibida_buena']) && empty($talla['cantidad_pendiente_reparacion'])) {
                continue;
            }
            $nueva = Distribucion_por_tallas::create([
                'grupo' => $grupoNuevo,
                'producto_id' => $talla['producto_id'] ?? null,
                'talla' => $talla['talla'] ?? null,
                'cantidad_planificada' => (int) ($talla['cantidad_planificada'] ?? 0),
                'cantidad_recibida_buena' => $aEnteroONulo($talla['cantidad_recibida_buena'] ?? null),
                'cantidad_pendiente_reparacion' => $aEnteroONulo($talla['cantidad_pendiente_reparacion'] ?? null),
            ]);
            $conservados[] = $nueva->id;
        }
        $orden->distribucionesPorTallas()->sync($conservados);
        return $conservados;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($OrdenProduccionid) {
        Myhelp::EscribirEnLog($this, 'DELETE:OrdenProduccions');

        $OrdenProduccion = OrdenProduccion::findOrFail($OrdenProduccionid);
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;

        // Las filas del kit tienen llave foránea a la OP: se eliminan primero
        // para que la OP pueda borrarse.
        KitProceso::where('orden_produccion_id', $OrdenProduccion->id)->delete();
        $OrdenProduccion->delete();

        $this->invalidarCache();
        Myhelp::EscribirEnLog($this, 'DELETE:OrdenProduccions', 'OrdenProduccion id:' . $OrdenProduccionid . ' | ' . $identificador . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $identificador]));
    }

    public function destroyBulk(Request $request) {
        $ids = (array) $request->input('id', []);

        KitProceso::whereIn('orden_produccion_id', $ids)->delete();
        OrdenProduccion::whereIn('id', $ids)->delete();

        $this->invalidarCache();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($ids) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

}
