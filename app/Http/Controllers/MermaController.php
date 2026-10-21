<?php

namespace App\Http\Controllers;

use App\Models\Merma;
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

class MermaController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'Merma';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct() {
//        $this->middleware('permission:create Merma', ['only' => ['create', 'store']]);
//        $this->middleware('permission:read Merma', ['only' => ['index', 'show']]);
//        $this->middleware('permission:update Merma', ['only' => ['edit', 'update']]);
//        $this->middleware('permission:delete Merma', ['only' => ['destroy', 'destroyBulk']]);
        $this->thisAtributos = (new Merma())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' Mermas '));
        $Mermas = $this->Filtros($request)->get();
//        $losSelect = $this->Dependencias();


        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $Mermas),
            'total' => $Mermas->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->losSelect(Merma::class, ' Merma','proveeNombre'),
	        'titulos'           => Merma::getFillableWithTypes(),

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
        $Mermas = Merma::query();
        if ($request->has('search')) {
            $Mermas = $Mermas->where(function ($query) use ($request) {
                $query->where('nombre', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('codigo', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('identificacion', 'LIKE', "%" . $request->search . "%")
                ;
            });
        }

        if ($request->has(['field', 'order'])) {
            $Mermas = $Mermas->orderBy($request->field, $request->order);
        } else
            $Mermas = $Mermas->orderBy('updated_at', 'DESC');
        return $Mermas;
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

    public function PerPageAndPaginate($request, $Mermas) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $Mermas->forPage($page, $perPage),
            $Mermas->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:Mermas');
        DB::beginTransaction();
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $Merma = Merma::create($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'STORE:Mermas EXITOSO', 'Merma id:' . $Merma->id . ' | ' . $Merma->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $Merma->nombre]));
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:Mermas');
        DB::beginTransaction();
        $Merma = Merma::findOrFail($id);
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $Merma->update($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE:Mermas EXITOSO', 'Merma id:' . $Merma->id . ' | ' . $Merma->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $Merma->nombre]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($Mermaid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:Mermas');
        $Merma = Merma::find($Mermaid);
        $elnombre = $Merma->nombre;
        $Merma->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:Mermas', 'Merma id:' . $Merma->id . ' | ' . $Merma->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $Merma = Merma::whereIn('id', $request->id);
        $Merma->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

}
