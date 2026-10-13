<?php

namespace App\Http\Controllers;

use App\Models\Distribucion_por_tallas;
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
        $OrdenProduccions = $this->Filtros($request)->get()->map(function ($op) {
            $producto = $op->productoReferencia;
            $op->producto_nombre = $producto
                ? ($producto->Titulo_Shopify ?: ($producto->Codigo_interno ?: 'Producto #' . $producto->id))
                : ($op->producto_referencia_id ? '#' . $op->producto_referencia_id : '');
            $op->taller_nombre = $op->taller?->nombre ?? ($op->taller_id ? '#' . $op->taller_id : '');
            $op->estado_nombre = $op->estadoOp?->nombre ?? ($op->estado_op_id ? '#' . $op->estado_op_id : '');
            $op->responsable_nombre = $op->responsable?->name ?? ($op->user_id ? '#' . $op->user_id : '');
            return $op;
        });

        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $OrdenProduccions),
            'total' => $OrdenProduccions->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->obtenerSelects(),
	        'titulos'           => OrdenProduccion::getFillableWithTypes(),

        ]);
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

        $estadoOperaciones = EstadoOperacion::all(['id', 'nombre'])
            ->map(function ($estado) {
                return [
                    'id'    => $estado->id,
                    'value' => $estado->id,
                    'name'  => $estado->nombre,
                    'label' => $estado->nombre,
                    'title' => $estado->nombre,
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

        $gruposTallas = Distribucion_por_tallas::whereNotNull('grupo')
            ->orderBy('grupo')
            ->orderBy('talla')
            ->get(['id', 'grupo', 'talla', 'cantidad_planificada'])
            ->groupBy('grupo')
            ->map(function ($filas, $grupo) {
                $nombres = $filas->pluck('talla')->filter()->implode(', ');
                $etiqueta = 'Grupo ' . $grupo . ($nombres ? ' (' . $nombres . ')' : '');
                return [
                    'id'     => (int) $grupo,
                    'value'  => (int) $grupo,
                    'name'   => $etiqueta,
                    'label'  => $etiqueta,
                    'title'  => $etiqueta,
                    'tallas' => $filas->map(function ($fila) {
                        return [
                            'id'                   => $fila->id,
                            'talla'                => $fila->talla,
                            'cantidad_planificada' => $fila->cantidad_planificada,
                        ];
                    })->values()->all(),
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
        $OrdenProduccions = OrdenProduccion::query()->with(['productoReferencia', 'taller', 'estadoOp', 'responsable', 'distribucionesPorTallas']);
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

        $data = $request->only(['numero_op', 'producto_referencia_id', 'cantidad_planificada', 'fecha_creacion', 'fecha_prevista_envio_taller', 'taller_id', 'estado_op_id', 'user_id', 'id_shopify_producto', 'observaciones']);

        if (empty($data['user_id']) && $request->filled('responsable_id')) {
            $data['user_id'] = $request->input('responsable_id');
        }

        if (empty($data['fecha_creacion'])) {
            $data['fecha_creacion'] = now()->toDateString();
        }
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

        DB::commit();
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

        $data = $request->only(['numero_op', 'producto_referencia_id', 'cantidad_planificada', 'fecha_creacion', 'fecha_prevista_envio_taller', 'taller_id', 'estado_op_id', 'user_id', 'id_shopify_producto', 'observaciones']);

        if (empty($data['user_id']) && $request->filled('responsable_id')) {
            $data['user_id'] = $request->input('responsable_id');
        }
        $OrdenProduccion = OrdenProduccion::findOrFail($id);

        $OrdenProduccion->forceFill($data);
        $OrdenProduccion->save();

        if ($request->has('distribucion_tallas')) {
            $this->sincronizarTallas($OrdenProduccion, $request->input('distribucion_tallas', []), true, $request->input('grupo_tallas'));
        }

        DB::commit();
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'UPDATE:OrdenProduccions EXITOSO', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $identificador]));
    }

    public function syncDistribucion(Request $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin SYNC-DISTRIBUCION:OrdenProduccions');
        DB::beginTransaction();

        $OrdenProduccion = OrdenProduccion::findOrFail($id);
        $this->sincronizarTallas($OrdenProduccion, $request->input('distribucion_tallas', []), false);

        DB::commit();
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
        Myhelp::EscribirEnLog($this, 'DELETE:OrdenProduccions', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $OrdenProduccion->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $OrdenProduccion = OrdenProduccion::whereIn('id', $request->id);
        $OrdenProduccion->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

}
