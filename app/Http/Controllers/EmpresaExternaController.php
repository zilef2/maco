<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmpresaExternaRequest;
use App\Http\Requests\UpdateEmpresaExternaRequest;
use App\Models\EmpresaExterna;
use App\Models\Proveedor;
use App\Services\ListaDesplegableService;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaExternaController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'EmpresaExterna';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct(protected ListaDesplegableService $listas) {
//        $this->middleware('permission:create EmpresaExterna', ['only' => ['create', 'store']]);
//        $this->middleware('permission:read EmpresaExterna', ['only' => ['index', 'show']]);
//        $this->middleware('permission:update EmpresaExterna', ['only' => ['edit', 'update']]);
//        $this->middleware('permission:delete EmpresaExterna', ['only' => ['destroy', 'destroyBulk']]);
        $this->thisAtributos = (new EmpresaExterna())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' EmpresaExternas '));

        // Listado completo: la tabla filtra, ordena y pagina en el frontend.
        // La clave solo depende de la versión, que se incrementa al escribir.
        $claveListado = 'empresa_externa_index_' . $this->versionCache();
        $EmpresaExternas = Cache::remember($claveListado, now()->addMinutes(10), function () {
            return EmpresaExterna::query()
                ->orderByDesc('updated_at')
                ->get()
                ->values();
        });

        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'empresa_externas' => $EmpresaExternas,
            'total' => $EmpresaExternas->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->losSelect(EmpresaExterna::class, 'EmpresaExterna', 'nombre_comercial'),
	        'titulos'           => EmpresaExterna::getFillableWithTypes(),
            'opcionesSelect'    => [
                'tipo_proveedor' => Proveedor::TIPOS_PROVEEDOR,
                'estado' => Proveedor::ESTADOS,
                'forma_pago' => Proveedor::FORMAS_PAGO,
                'ciudad' => $this->listas->nombresByCategoria('ciudad'),
            ],

        ]);
    }

    /**
     * Versión del cache del listado: al incrementarse, todas las claves
     * anteriores dejan de usarse y se reconstruyen con datos frescos.
     */
    private function versionCache(): int {
        Cache::add('empresa_externa_index_version', 1, now()->addDays(7));
        return (int) Cache::get('empresa_externa_index_version', 1);
    }

    private function invalidarCache(): void {
        Cache::increment('empresa_externa_index_version');
        Cache::forget('op_selects');
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

    public function store(StoreEmpresaExternaRequest $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:EmpresaExternas');
        DB::beginTransaction();
        $EmpresaExterna = EmpresaExterna::create($request->validated());

        DB::commit();
        $this->invalidarCache();
        Myhelp::EscribirEnLog($this, 'STORE:EmpresaExternas EXITOSO', 'EmpresaExterna id:' . $EmpresaExterna->id . ' | ' . $EmpresaExterna->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $EmpresaExterna->nombre]));
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

    public function update(UpdateEmpresaExternaRequest $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:EmpresaExternas');
        DB::beginTransaction();
        $EmpresaExterna = EmpresaExterna::findOrFail($id);
        $EmpresaExterna->update($request->validated());

        DB::commit();
        $this->invalidarCache();
        Myhelp::EscribirEnLog($this, 'UPDATE:EmpresaExternas EXITOSO', 'EmpresaExterna id:' . $EmpresaExterna->id . ' | ' . $EmpresaExterna->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $EmpresaExterna->nombre]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($EmpresaExternaid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:EmpresaExternas');
        $EmpresaExterna = EmpresaExterna::find($EmpresaExternaid);
        $elnombre = $EmpresaExterna->nombre;
        $EmpresaExterna->delete();
        $this->invalidarCache();
        Myhelp::EscribirEnLog($this, 'DELETE:EmpresaExternas', 'EmpresaExterna id:' . $EmpresaExterna->id . ' | ' . $EmpresaExterna->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $EmpresaExterna = EmpresaExterna::whereIn('id', $request->id);
        $EmpresaExterna->delete();
        $this->invalidarCache();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

}
