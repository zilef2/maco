<?php

namespace App\Http\Controllers;

use App\Models\OrdenProduccion;
use App\Models\ProcesoExterno;
use App\Models\Proveedor;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProcesoExternoController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'ProcesoExterno';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct() {
//        $this->middleware('permission:create ProcesoExterno', ['only' => ['create', 'store']]);
//        $this->middleware('permission:read ProcesoExterno', ['only' => ['index', 'show']]);
//        $this->middleware('permission:update ProcesoExterno', ['only' => ['edit', 'update']]);
//        $this->middleware('permission:delete ProcesoExterno', ['only' => ['destroy', 'destroyBulk']]);
        $this->thisAtributos = (new ProcesoExterno())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' ProcesoExternos '));
        $ProcesoExternos = $this->Filtros($request)
            ->with(['ordenProduccion:id,numero_op', 'materialOrigen:id,nombre_base', 'proveedorDestino:id,nombre'])
            ->get()
            ->map(function ($pe) {
                $pe->orden_numero = $pe->ordenProduccion?->numero_op ? 'OP ' . $pe->ordenProduccion->numero_op : 'Sin OP';
                $pe->material_nombre = $pe->materialOrigen?->nombre_base ?? ($pe->material_origen_id ? '#' . $pe->material_origen_id : '');
                $pe->proveedor_nombre = $pe->proveedorDestino?->nombre ?? '';
                return $pe;
            });
//        $losSelect = $this->Dependencias();


        $perPage = $request->has('perPage') ? $request->perPage : 10;

        // Solo cuentan las OP que todavía tienen material fuera: al registrar
        // el regreso se llena fecha_regreso_real y la OP deja de estar activa.
        $ordenesEnProcesoExterno = ProcesoExterno::query()
            ->whereNull('fecha_regreso_real')
            ->whereNotNull('orden_produccion_id')
            ->distinct()
            ->count('orden_produccion_id');

        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $ProcesoExternos),
            'total' => $ProcesoExternos->count(),
            'ordenesEnProcesoExterno' => $ordenesEnProcesoExterno,
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->obtenerSelects(),
	        'titulos'           => ProcesoExterno::getFillableWithTypes(),

        ]);
    }

    public function obtenerSelects(): array {
        $ordenes = OrdenProduccion::orderByDesc('id')
            ->get(['id', 'numero_op'])
            ->map(function ($op) {
                $nombre = 'OP ' . $op->numero_op;
                return [
                    'id'    => $op->id,
                    'value' => $op->id,
                    'name'  => $nombre,
                    'label' => $nombre,
                    'title' => $nombre,
                ];
            });

        $materiales = Material::orderBy('nombre_base')
            ->get(['id', 'categoria', 'nombre_base'])
            ->map(function ($material) {
                $nombre_base = $material->nombre_base ?: ($material->categoria ?: 'Material #' . $material->id);
                return [
                    'id'    => $material->id,
                    'value' => $material->id,
                    'name'  => $nombre_base,
                    'label' => $nombre_base,
                    'title' => $nombre_base,
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

        return [
            'orden_produccion_id'  => $ordenes,
            'material_origen_id'   => $materiales,
            'proveedor_destino_id' => $proveedores,
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
    public function Filtros($request): Builder {
        $ProcesoExternos = ProcesoExterno::query();
        if ($request->has('search')) {
            $ProcesoExternos = $ProcesoExternos->where(function ($query) use ($request) {
                $query->where('etapa_componente', 'LIKE', "%" . $request->search . "%")
                    ->orWhere('servicio_proceso', 'LIKE', "%" . $request->search . "%")
                    ->orWhere('bodega_origen', 'LIKE', "%" . $request->search . "%")
                ;
            });
        }

        if ($request->has(['field', 'order'])) {
            $ProcesoExternos = $ProcesoExternos->orderBy($request->field, $request->order);
        } else
            $ProcesoExternos = $ProcesoExternos->orderBy('orden_produccion_id', 'ASC')->orderBy('id', 'DESC');
        return $ProcesoExternos;
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

    public function PerPageAndPaginate($request, $ProcesoExternos) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $ProcesoExternos->forPage($page, $perPage),
            $ProcesoExternos->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:ProcesoExternos');
        DB::beginTransaction();
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $ProcesoExterno = ProcesoExterno::create($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'STORE:ProcesoExternos EXITOSO', 'ProcesoExterno id:' . $ProcesoExterno->id . ' | ' . $ProcesoExterno->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $ProcesoExterno->nombre]));
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:ProcesoExternos');
        DB::beginTransaction();
        $ProcesoExterno = ProcesoExterno::findOrFail($id);
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $ProcesoExterno->update($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE:ProcesoExternos EXITOSO', 'ProcesoExterno id:' . $ProcesoExterno->id . ' | ' . $ProcesoExterno->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $ProcesoExterno->nombre]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($ProcesoExternoid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:ProcesoExternos');
        $ProcesoExterno = ProcesoExterno::find($ProcesoExternoid);
        $elnombre = $ProcesoExterno->nombre;
        $ProcesoExterno->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:ProcesoExternos', 'ProcesoExterno id:' . $ProcesoExterno->id . ' | ' . $ProcesoExterno->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $ProcesoExterno = ProcesoExterno::whereIn('id', $request->id);
        $ProcesoExterno->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

}
