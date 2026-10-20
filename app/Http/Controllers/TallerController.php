<?php

namespace App\Http\Controllers;

use App\Models\Taller;
use App\Jobs\ImportarTalleresJob;
use App\Services\ExcelTalleresService;
use App\Services\ImportacionExcelNotificador;
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

class TallerController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'Taller';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct() {
//        $this->middleware('permission:create Taller', ['only' => ['create', 'store']]);
//        $this->middleware('permission:read Taller', ['only' => ['index', 'show']]);
//        $this->middleware('permission:update Taller', ['only' => ['edit', 'update']]);
//        $this->middleware('permission:delete Taller', ['only' => ['destroy', 'destroyBulk']]);
        $this->thisAtributos = (new Taller())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' Tallers '));
        $Tallers = $this->Filtros($request)->get();
//        $losSelect = $this->Dependencias();


        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $Tallers),
            'total' => $Tallers->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->losSelect(Taller::class, ' Taller','proveeNombre'),
	        'titulos'           => Taller::getFillableWithTypes(),

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
        $Tallers = Taller::query();
        if ($request->has('search')) {
            $Tallers = $Tallers->where(function ($query) use ($request) {
                $query->where('nombre', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('codigo', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('identificacion', 'LIKE', "%" . $request->search . "%")
                ;
            });
        }

        if ($request->has(['field', 'order'])) {
            $Tallers = $Tallers->orderBy($request->field, $request->order);
        } else
            $Tallers = $Tallers->orderBy('updated_at', 'DESC');
        return $Tallers;
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

    public function PerPageAndPaginate($request, $Tallers) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $Tallers->forPage($page, $perPage),
            $Tallers->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:Tallers');
        DB::beginTransaction();
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $Taller = Taller::create($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'STORE:Tallers EXITOSO', 'Taller id:' . $Taller->id . ' | ' . $Taller->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $Taller->nombre]));
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:Tallers');
        DB::beginTransaction();
        $Taller = Taller::findOrFail($id);
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $Taller->update($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE:Tallers EXITOSO', 'Taller id:' . $Taller->id . ' | ' . $Taller->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $Taller->nombre]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($Tallerid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:Tallers');
        $Taller = Taller::find($Tallerid);
        $elnombre = $Taller->nombre;
        $Taller->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:Tallers', 'Taller id:' . $Taller->id . ' | ' . $Taller->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $Taller = Taller::whereIn('id', $request->id);
        $Taller->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

    /**
     * Carga masiva de talleres desde Excel.
     *
     * Valida de forma síncrona (tamaño, escaneo de seguridad, columnas y
     * filas) y solo si todo está correcto encola el job que inserta en la BD
     * y envía los correos.
     */
    public function uploadExcel(
        Request $request,
        ExcelTalleresService $excel,
        ImportacionExcelNotificador $notificador,
    ): RedirectResponse {
        Myhelp::EscribirEnLog($this, 'uploadExcel: inicio');

        $request->validate([
            'archivo' => ['required', 'file', 'max:10240', 'mimes:xlsx'],
        ], [
            'archivo.required' => 'Selecciona un archivo .xlsx.',
            'archivo.file' => 'El archivo no es válido.',
            'archivo.max' => 'El archivo supera el tamaño máximo de 10 MB.',
            'archivo.mimes' => 'Solo se permiten archivos .xlsx.',
        ]);

        $archivo = $request->file('archivo');
        $usuario = $request->user();
        $nombreArchivo = $archivo->getClientOriginalName();
        $rutaRelativa = null;

        try {
            if (strtolower($archivo->getClientOriginalExtension()) !== 'xlsx') {
                throw new \RuntimeException('Solo se permiten archivos con extensión .xlsx.');
            }

            // 1. Escaneo de seguridad del contenedor XLSX.
            $problemas = $excel->escanearSeguridad($archivo->getRealPath());
            if (!empty($problemas)) {
                throw new \RuntimeException(implode(' | ', $problemas));
            }

            // 2. Fila 1 = títulos; el resto datos. Valida "nombre" y "direccion".
            $excel->leerRegistros($archivo->getRealPath());

            // 3. Todo válido: guardar y encolar la importación.
            $rutaRelativa = $archivo->store('imports/talleres', 'local');
            if (!$rutaRelativa) {
                throw new \RuntimeException('No se pudo guardar el archivo para su procesamiento.');
            }

            ImportarTalleresJob::dispatch(
                $rutaRelativa,
                $nombreArchivo,
                $usuario?->email,
                $usuario?->name,
                $usuario?->id,
            );

            Myhelp::EscribirEnLog($this, 'uploadExcel ENCOLADO', $nombreArchivo);

            return back()->with(
                'success',
                'Archivo validado correctamente. La importación se está procesando; '
                . 'en breve podrás ver la información actualizada.'
            );
        } catch (\Throwable $e) {
            if ($rutaRelativa) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($rutaRelativa);
            }

            $notificador->fallo($usuario?->email, $usuario?->name, $nombreArchivo, 'talleres', $e);
            Myhelp::EscribirEnLog($this, 'uploadExcel ERROR', $e->getMessage());

            return back()->with('error', 'No se importó nada: ' . $e->getMessage());
        }
    }

}
