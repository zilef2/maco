<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePresupuestoRequest;
use App\Services\PresupuestoService;
use App\Models\Presupuesto;
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

use App\Services\ListaDesplegableService;

class PresupuestoController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'Presupuesto';

    public function __construct(
        protected PresupuestoService $presupuestoService,
        protected ListaDesplegableService $listaDesplegableService
    ) {
        $this->thisAtributos = (new Presupuesto())->getFillable();
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' Presupuestos '));
        $Presupuestos = $this->Filtros($request)->get();
        $listasDesplegables = $this->listaDesplegableService->getOptionsByCategoriaGrouped('presupuesto');

        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $Presupuestos),
            'total' => $Presupuestos->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect' => [
                'estado' => $listasDesplegables['estado'] ?? [],
                'motivo_reajuste' => $listasDesplegables['motivo_reajuste'] ?? [],
            ],
	        'titulos'           => Presupuesto::getFillableWithTypes(),

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
        $Presupuestos = Presupuesto::query();
        if ($request->has('search')) {
            $Presupuestos = $Presupuestos->where(function ($query) use ($request) {
                $query->where('nombre', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('codigo', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('identificacion', 'LIKE', "%" . $request->search . "%")
                ;
            });
        }

        if ($request->has(['field', 'order'])) {
            $Presupuestos = $Presupuestos->orderBy($request->field, $request->order);
        } else
            $Presupuestos = $Presupuestos->orderBy('updated_at', 'DESC');
        return $Presupuestos;
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

    public function PerPageAndPaginate($request, $Presupuestos) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $Presupuestos->forPage($page, $perPage),
            $Presupuestos->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(StorePresupuestoRequest $request): RedirectResponse {
        Myhelp::EscribirEnLog($this, ' Begin STORE:Presupuestos');
        $presupuesto = $this->presupuestoService->create($request->validated());
        Myhelp::EscribirEnLog($this, 'STORE:Presupuestos EXITOSO', 'Presupuesto id:' . $presupuesto->id . ' | ' . $presupuesto->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $presupuesto->nombre]));
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:Presupuestos');
        DB::beginTransaction();
        $Presupuesto = Presupuesto::findOrFail($id);
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $Presupuesto->update($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE:Presupuestos EXITOSO', 'Presupuesto id:' . $Presupuesto->id . ' | ' . $Presupuesto->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $Presupuesto->nombre]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($Presupuestoid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:Presupuestos');
        $Presupuesto = Presupuesto::find($Presupuestoid);
        $elnombre = $Presupuesto->nombre;
        $Presupuesto->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:Presupuestos', 'Presupuesto id:' . $Presupuesto->id . ' | ' . $Presupuesto->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $Presupuesto = Presupuesto::whereIn('id', $request->id);
        $Presupuesto->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

}
