<?php

namespace App\Http\Controllers;

use App\Models\Distribucion_por_tallas;
use App\Models\GrupoTalla;
use App\Models\OrdenProduccion;
use App\Models\User;
use App\Models\EstadoOperacion;
use App\Models\Producto;
use App\Models\Taller;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrdenProduccionController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'OrdenProduccion';

    public function __construct() {
        $this->thisAtributos = (new OrdenProduccion())->getFillable();
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' OrdenProduccions '));

        // Cache del listado: la clave incluye versión + filtros; se invalida al escribir
        $filtros = $request->all(['search', 'field', 'order']);
        $claveListado = 'op_index_' . $this->versionCache() . '_' . md5(json_encode($filtros));
        $OrdenProduccions = Cache::remember($claveListado, now()->addMinutes(0), function () use ($request) {
            return $this->Filtros($request)->get()->map(function ($op) {
                $producto = $op->productoReferencia;
                $op->producto_nombre = $producto
                    ? ($producto->Titulo_Shopify ?: ($producto->Codigo_interno ?: 'Producto #' . $producto->id))
                    : ($op->producto_referencia_id ? '#' . $op->producto_referencia_id : '');
                $op->productos_nombres = $op->productos
                    ->map(fn ($p) => $p->Titulo_Shopify ?: ($p->Codigo_interno ?: 'Producto #' . $p->id))
                    ->implode(', ');
                $op->taller_nombre = $op->taller?->nombre ?? ($op->taller_id ? '#' . $op->taller_id : '');
                $op->estado_nombre = $op->estadoOp?->nombre ?? ($op->estado_op_id ? '#' . $op->estado_op_id : '');
                $op->responsable_nombre = $op->responsable?->name ?? ($op->user_id ? '#' . $op->user_id : '');
                return $op;
            });
        });

        // Cache de los selects (productos, talleres, estados, usuarios, grupos, siguiente OP)
        $losSelect = Cache::remember('op_selects', now()->addMinutes(30), fn () => $this->obtenerSelects());

        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $OrdenProduccions),
            'total' => $OrdenProduccions->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $losSelect,
	        'titulos'           => OrdenProduccion::getFillableWithTypes(),

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

        $productos = Producto::all(['id', 'Titulo_Shopify', 'Codigo_interno'])
            ->map(function ($producto) {
                $nombre = $producto->Titulo_Shopify ?: ($producto->Codigo_interno ?: 'Producto #' . $producto->id);
                return [
                    'id'    => $producto->id,
                    'value' => $producto->id,
                    'name'  => $nombre,
                    'label' => $nombre,
                    'title' => $nombre,
                ];
            });

        $maxOp = OrdenProduccion::selectRaw('MAX(CAST(numero_op AS UNSIGNED)) as max_op')->value('max_op');
        $siguienteNumeroOp = ($maxOp && (int)$maxOp >= 100) ? ((int)$maxOp + 1) : 100;

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
            'grupos_tallas'          => $gruposTallas,
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
        $OrdenProduccions = OrdenProduccion::query()->with(['productoReferencia', 'taller', 'estadoOp', 'responsable', 'distribucionesPorTallas', 'productos']);
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

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:OrdenProduccions');
        DB::beginTransaction();

        $data = $request->only(['numero_op', 'cantidad_planificada', 'fecha_creacion', 'fecha_prevista_envio_taller', 'taller_id', 'user_id', 'id_shopify_producto', 'observaciones']);
        $data['producto_referencia_id'] = $request->filled('producto_referencia_id') ? $request->input('producto_referencia_id') : null;

        if (empty($data['user_id']) && $request->filled('responsable_id')) {
            $data['user_id'] = $request->input('responsable_id');
        }

        if (empty($data['fecha_creacion'])) {
            $data['fecha_creacion'] = now()->toDateString();
        }
        $data['estado_op_id'] = EstadoOperacion::where('nombre', 'Borrador')->value('id');
        if (empty($data['numero_op'])) {
            $maxOp = OrdenProduccion::selectRaw('MAX(CAST(numero_op AS UNSIGNED)) as max_op')->value('max_op');
            $data['numero_op'] = ($maxOp && (int)$maxOp >= 100) ? ((int)$maxOp + 1) : 100;
        }

        $OrdenProduccion = new OrdenProduccion();
        $OrdenProduccion->forceFill($data);
        $OrdenProduccion->save();

        $grupoTallas = $request->input('grupo_tallas');
        foreach ($request->input('distribucion_tallas', []) as $talla) {
            if (!empty($talla['id'])) {
                $OrdenProduccion->distribucionesPorTallas()->syncWithoutDetaching([(int) $talla['id']]);
                continue;
            }
            if (empty($talla['talla']) && empty($talla['cantidad_planificada'])) {
                continue;
            }
            $nueva = Distribucion_por_tallas::create([
                'grupo' => $grupoTallas,
                'talla' => $talla['talla'] ?? null,
                'cantidad_planificada' => (int) ($talla['cantidad_planificada'] ?? 0),
            ]);
            $OrdenProduccion->distribucionesPorTallas()->attach($nueva->id);
        }

        $OrdenProduccion->productos()->sync(array_map('intval', $request->input('productos', [])));

        DB::commit();
        $this->invalidarCache();
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'STORE:OrdenProduccions EXITOSO', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $identificador]));
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

        $data = $request->only(['numero_op', 'cantidad_planificada', 'fecha_creacion', 'fecha_prevista_envio_taller', 'taller_id', 'user_id', 'id_shopify_producto', 'observaciones']);
        $data['producto_referencia_id'] = $request->filled('producto_referencia_id') ? $request->input('producto_referencia_id') : null;

        if (empty($data['user_id']) && $request->filled('responsable_id')) {
            $data['user_id'] = $request->input('responsable_id');
        }
        $OrdenProduccion = OrdenProduccion::findOrFail($id);

        $OrdenProduccion->forceFill($data);
        $OrdenProduccion->save();

        if ($request->has('distribucion_tallas')) {
            $this->sincronizarTallas($OrdenProduccion, $request->input('distribucion_tallas', []), true, $request->input('grupo_tallas'));
        }

        if ($request->has('productos')) {
            $OrdenProduccion->productos()->sync(array_map('intval', $request->input('productos', [])));
        }

        DB::commit();
        $this->invalidarCache();
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'UPDATE:OrdenProduccions EXITOSO', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $identificador]));
    }

    /**
     * Cambia exclusivamente el estado de una OP, validando la transición
     * contra la columna puede_pasar_a del estado actual.
     */
    public function cambiarEstado(Request $request, $id): RedirectResponse {
        $request->validate([
            'estado_op_id' => ['required', 'integer', 'exists:estado_operacions,id'],
        ]);

        $OrdenProduccion = OrdenProduccion::findOrFail($id);
        $estadoActual = $OrdenProduccion->estadoOp;
        $permitidos = $estadoActual?->idsPuedePasarA() ?? [];

        if (! in_array((int) $request->estado_op_id, $permitidos, true)) {
            return back()->withErrors([
                'estado_op_id' => "Transición no permitida desde el estado '{$estadoActual?->nombre}'.",
            ]);
        }

        $OrdenProduccion->forceFill(['estado_op_id' => (int) $request->estado_op_id])->save();
        $this->invalidarCache();

        $nuevoEstado = EstadoOperacion::find($request->estado_op_id);
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'CAMBIAR-ESTADO:OrdenProduccions', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador . ' | ' . ($estadoActual?->nombre ?? '?') . ' -> ' . $nuevoEstado?->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $identificador]));
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
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:OrdenProduccions');
        $OrdenProduccion = OrdenProduccion::find($OrdenProduccionid);
        $elnombre = $OrdenProduccion->nombre;
        $OrdenProduccion->delete();
        $this->invalidarCache();
        Myhelp::EscribirEnLog($this, 'DELETE:OrdenProduccions', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $OrdenProduccion->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $OrdenProduccion = OrdenProduccion::whereIn('id', $request->id);
        $OrdenProduccion->delete();
        $this->invalidarCache();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

}
