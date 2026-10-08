<?php

namespace App\Http\Controllers;

use App\Models\CierreOrdenProduccion;
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

class CierreOrdenProduccionController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'CierreOrdenProduccion';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct() {
//        $this->middleware('permission:create CierreOrdenProduccion', ['only' => ['create', 'store']]);
//        $this->middleware('permission:read CierreOrdenProduccion', ['only' => ['index', 'show']]);
//        $this->middleware('permission:update CierreOrdenProduccion', ['only' => ['edit', 'update']]);
//        $this->middleware('permission:delete CierreOrdenProduccion', ['only' => ['destroy', 'destroyBulk']]);
        $this->thisAtributos = (new CierreOrdenProduccion())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' CierreOrdenProduccions '));
        $CierreOrdenProduccions = $this->Filtros($request)->get();
//        $losSelect = $this->Dependencias();


        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $CierreOrdenProduccions),
            'total' => $CierreOrdenProduccions->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->losSelect(CierreOrdenProduccion::class, ' CierreOrdenProduccion','proveeNombre'),
	        'titulos'           => CierreOrdenProduccion::getFillableWithTypes(),

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
        $CierreOrdenProduccions = CierreOrdenProduccion::query();
        if ($request->has('search')) {
            $CierreOrdenProduccions = $CierreOrdenProduccions->where(function ($query) use ($request) {
                $query->where('nombre', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('codigo', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('identificacion', 'LIKE', "%" . $request->search . "%")
                ;
            });
        }

        if ($request->has(['field', 'order'])) {
            $CierreOrdenProduccions = $CierreOrdenProduccions->orderBy($request->field, $request->order);
        } else
            $CierreOrdenProduccions = $CierreOrdenProduccions->orderBy('updated_at', 'DESC');
        return $CierreOrdenProduccions;
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

    public function PerPageAndPaginate($request, $CierreOrdenProduccions) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $CierreOrdenProduccions->forPage($page, $perPage),
            $CierreOrdenProduccions->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:CierreOrdenProduccions');
        DB::beginTransaction();
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $CierreOrdenProduccion = CierreOrdenProduccion::create($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'STORE:CierreOrdenProduccions EXITOSO', 'CierreOrdenProduccion id:' . $CierreOrdenProduccion->id . ' | ' . $CierreOrdenProduccion->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $CierreOrdenProduccion->nombre]));
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:CierreOrdenProduccions');
        DB::beginTransaction();
        $CierreOrdenProduccion = CierreOrdenProduccion::findOrFail($id);
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $CierreOrdenProduccion->update($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE:CierreOrdenProduccions EXITOSO', 'CierreOrdenProduccion id:' . $CierreOrdenProduccion->id . ' | ' . $CierreOrdenProduccion->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $CierreOrdenProduccion->nombre]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($CierreOrdenProduccionid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:CierreOrdenProduccions');
        $CierreOrdenProduccion = CierreOrdenProduccion::find($CierreOrdenProduccionid);
        $elnombre = $CierreOrdenProduccion->nombre;
        $CierreOrdenProduccion->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:CierreOrdenProduccions', 'CierreOrdenProduccion id:' . $CierreOrdenProduccion->id . ' | ' . $CierreOrdenProduccion->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $CierreOrdenProduccion = CierreOrdenProduccion::whereIn('id', $request->id);
        $CierreOrdenProduccion->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

}
