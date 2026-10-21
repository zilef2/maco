<?php

namespace App\Http\Controllers;

use App\Models\Distribucion_por_tallas;
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

class Distribucion_por_tallasController extends Controller {
    // El listado es solo lectura y muestra muchas tallas: 200 filas por página.
    private const PER_PAGE_POR_DEFECTO = 200;

    public array $thisAtributos;
    public string $FromController = 'Distribucion_por_tallas';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct() {
//        $this->middleware('permission:create Distribucion_por_tallas', ['only' => ['create', 'store']]);
//        $this->middleware('permission:read Distribucion_por_tallas', ['only' => ['index', 'show']]);
//        $this->middleware('permission:update Distribucion_por_tallas', ['only' => ['edit', 'update']]);
//        $this->middleware('permission:delete Distribucion_por_tallas', ['only' => ['destroy', 'destroyBulk']]);
        $this->thisAtributos = (new Distribucion_por_tallas())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' Distribucion_por_tallass '));
        $Distribucion_por_tallass = $this->Filtros($request)->get();
//        $losSelect = $this->Dependencias();

        $titulo = str_replace('_', ' ', $this->FromController);
        $perPage = $request->has('perPage') ? $request->perPage : self::PER_PAGE_POR_DEFECTO;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $Distribucion_por_tallass),
            'total' => $Distribucion_por_tallass->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $titulo), 'href' => route($this->FromController . '.index')]],
            'title' => __($titulo),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->losSelect(Distribucion_por_tallas::class, ' Distribucion_por_tallas','proveeNombre'),
	        'titulos'           => Distribucion_por_tallas::getFillableWithTypes(),

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
        $Distribucion_por_tallass = Distribucion_por_tallas::query();
        if ($request->has('search')) {
            $Distribucion_por_tallass = $Distribucion_por_tallass->where(function ($query) use ($request) {
                $query->where('talla', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('codigo', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('identificacion', 'LIKE', "%" . $request->search . "%")
                ;
            });
        }

        if ($request->has(['field', 'order'])) {
            $Distribucion_por_tallass = $Distribucion_por_tallass->orderBy($request->field, $request->order);
        } else
            $Distribucion_por_tallass = $Distribucion_por_tallass->orderBy('updated_at', 'DESC');
        return $Distribucion_por_tallass;
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

    public function PerPageAndPaginate($request, $Distribucion_por_tallass) {
        $perPage = $request->has('perPage') ? $request->perPage : self::PER_PAGE_POR_DEFECTO;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $Distribucion_por_tallass->forPage($page, $perPage),
            $Distribucion_por_tallass->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:Distribucion_por_tallass');
        DB::beginTransaction();
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $Distribucion_por_tallas = Distribucion_por_tallas::create($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'STORE:Distribucion_por_tallass EXITOSO', 'Distribucion_por_tallas id:' . $Distribucion_por_tallas->id . ' | ' . $Distribucion_por_tallas->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $Distribucion_por_tallas->nombre]));
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:Distribucion_por_tallass');
        DB::beginTransaction();
        $Distribucion_por_tallas = Distribucion_por_tallas::findOrFail($id);
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $Distribucion_por_tallas->update($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE:Distribucion_por_tallass EXITOSO', 'Distribucion_por_tallas id:' . $Distribucion_por_tallas->id . ' | ' . $Distribucion_por_tallas->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $Distribucion_por_tallas->nombre]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($Distribucion_por_tallasid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:Distribucion_por_tallass');
        $Distribucion_por_tallas = Distribucion_por_tallas::find($Distribucion_por_tallasid);
        $elnombre = $Distribucion_por_tallas->nombre;
        $Distribucion_por_tallas->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:Distribucion_por_tallass', 'Distribucion_por_tallas id:' . $Distribucion_por_tallas->id . ' | ' . $Distribucion_por_tallas->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $Distribucion_por_tallas = Distribucion_por_tallas::whereIn('id', $request->id);
        $Distribucion_por_tallas->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

}
