<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTallerRequest;
use App\Http\Requests\UpdateTallerRequest;
use App\Models\Proveedor;
use App\Models\Taller;
use App\Jobs\ImportarTalleresJob;
use App\Services\ExcelTalleresService;
use App\Services\ImportacionExcelNotificador;
use App\Services\ListaDesplegableService;
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
    public function __construct(protected ListaDesplegableService $listas) {
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
            'filters' => $request->all(['search', 'field', 'order', 'tipo_proveedor', 'estado']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->losSelect(Taller::class, ' Taller', 'nombre_comercial'),
	        'titulos'           => Taller::getFillableWithTypes(),
            'opcionesSelect'    => $this->opcionesSelect(),

        ]);
    }

    /**
     * Catálogos de la ficha del tercero, para los filtros y los formularios.
     */
    private function opcionesSelect(): array {
        return [
            'tipo_proveedor' => Proveedor::TIPOS_PROVEEDOR,
            'estado' => Proveedor::ESTADOS,
            'forma_pago' => Proveedor::FORMAS_PAGO,
            'ciudad' => $this->listas->nombresByCategoria('ciudad'),
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
        $Tallers = Taller::query();
        if ($request->filled('search')) {
            $buscar = '%' . $request->search . '%';
            $Tallers = $Tallers->where(function ($query) use ($buscar) {
                $query->where('nombre_comercial', 'LIKE', $buscar)
                    ->orWhere('nombre', 'LIKE', $buscar)
                    ->orWhere('codigo_taller', 'LIKE', $buscar)
                    ->orWhere('nit', 'LIKE', $buscar)
                    ->orWhere('razon_social', 'LIKE', $buscar)
                    ->orWhere('ciudad', 'LIKE', $buscar)
                    ->orWhere('direccion', 'LIKE', $buscar)
                    ->orWhere('persona_contacto', 'LIKE', $buscar)
                    ->orWhere('que_suministra', 'LIKE', $buscar);
            });
        }

        if ($request->filled('tipo_proveedor')) {
            $Tallers = $Tallers->where('tipo_proveedor', $request->tipo_proveedor);
        }

        if ($request->filled('estado')) {
            $Tallers = $Tallers->where('estado', $request->estado);
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

    public function store(StoreTallerRequest $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:Tallers');
        DB::beginTransaction();
        $Taller = Taller::create($request->validated());

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

    public function update(UpdateTallerRequest $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:Tallers');
        DB::beginTransaction();
        $Taller = Taller::findOrFail($id);
        $Taller->update($request->validated());

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
     * Valida de forma síncrona (tamaño, escaneo de seguridad, columnas y el
     * destino de cada fila) y solo si todo está correcto encola el job que
     * inserta en la BD y envía los correos.
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

            // 2. Fila 1 = títulos. El resto de los datos lo valida el job
            // antes de insertar.
            $excel->validarEncabezados($archivo->getRealPath());

            // 3. El destino de cada fila se comprueba aquí, no en el job: una
            // celda vacía o fuera de la lista cancela la subida, porque esa
            // fila terminaría guardada en la entidad equivocada.
            $excel->validarDestinos($archivo->getRealPath());

            // 4. Todo válido: guardar y encolar la importación.
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
                $request->attributes->get('importacion_excel_id'),
            );

            Myhelp::EscribirEnLog($this, 'uploadExcel ENCOLADO', $nombreArchivo);

            return back()->with(
                'success',
                'Archivo recibido correctamente. La importación se está procesando; '
                . 'si alguna fila tiene errores recibirás el detalle por correo.'
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
