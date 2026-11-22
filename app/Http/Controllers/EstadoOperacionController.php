<?php

namespace App\Http\Controllers;

use App\Models\EstadoOperacion;
use App\Traits\ExcelDesdeDriveTrait;
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
use PhpOffice\PhpSpreadsheet\IOFactory;

class EstadoOperacionController extends Controller {
    use ExcelDesdeDriveTrait;
    public array $thisAtributos;
    public string $FromController = 'EstadoOperacion';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct() {
//        $this->middleware('permission:create EstadoOperacion', ['only' => ['create', 'store']]);
//        $this->middleware('permission:read EstadoOperacion', ['only' => ['index', 'show']]);
//        $this->middleware('permission:update EstadoOperacion', ['only' => ['edit', 'update']]);
//        $this->middleware('permission:delete EstadoOperacion', ['only' => ['destroy', 'destroyBulk']]);
        $this->thisAtributos = (new EstadoOperacion())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' EstadoOperacions '));
        $EstadoOperacions = $this->Filtros($request)->get();
//        $losSelect = $this->Dependencias();


        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $EstadoOperacions),
            'total' => $EstadoOperacions->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->losSelect(EstadoOperacion::class, ' EstadoOperacion','proveeNombre'),
	        'titulos'           => EstadoOperacion::getFillableWithTypes(),

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
        $EstadoOperacions = EstadoOperacion::query();
        if ($request->has('search')) {
            $EstadoOperacions = $EstadoOperacions->where(function ($query) use ($request) {
                $query->where('nombre', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('codigo', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('identificacion', 'LIKE', "%" . $request->search . "%")
                ;
            });
        }

        if ($request->has(['field', 'order'])) {
            $EstadoOperacions = $EstadoOperacions->orderBy($request->field, $request->order);
        } else
            $EstadoOperacions = $EstadoOperacions->orderBy('updated_at', 'DESC');
        return $EstadoOperacions;
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

    public function PerPageAndPaginate($request, $EstadoOperacions) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $EstadoOperacions->forPage($page, $perPage),
            $EstadoOperacions->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:EstadoOperacions');
        DB::beginTransaction();
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $EstadoOperacion = EstadoOperacion::create($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'STORE:EstadoOperacions EXITOSO', 'EstadoOperacion id:' . $EstadoOperacion->id . ' | ' . $EstadoOperacion->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $EstadoOperacion->nombre]));
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:EstadoOperacions');
        DB::beginTransaction();
        $EstadoOperacion = EstadoOperacion::findOrFail($id);
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $EstadoOperacion->update($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE:EstadoOperacions EXITOSO', 'EstadoOperacion id:' . $EstadoOperacion->id . ' | ' . $EstadoOperacion->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $EstadoOperacion->nombre]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($EstadoOperacionid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:EstadoOperacions');
        $EstadoOperacion = EstadoOperacion::find($EstadoOperacionid);
        $elnombre = $EstadoOperacion->nombre;
        $EstadoOperacion->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:EstadoOperacions', 'EstadoOperacion id:' . $EstadoOperacion->id . ' | ' . $EstadoOperacion->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $EstadoOperacion = EstadoOperacion::whereIn('id', $request->id);
        $EstadoOperacion->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

    // ─────────────────────────────────────────────────────────
    //  CARGA MASIVA DESDE EXCEL (todos los campos son texto)
    // ─────────────────────────────────────────────────────────
    public function uploadExcel(Request $request): RedirectResponse
    {
        Myhelp::EscribirEnLog($this, 'uploadExcel: inicio');

        // 1. Validaciones de seguridad del archivo (previas a cualquier lectura)
        $request->validate(self::reglasDeSubidaExcel(), self::mensajesDeSubidaExcel());

        $archivo = $this->excelDeLaSubida($request);

        // 2. Doble-verificación de extensión
        if (strtolower($archivo->getClientOriginalExtension()) !== 'xlsx') {
            return back()->with('error', 'Solo se permiten archivos con extensión .xlsx');
        }

        // 3. Verificación de firma de bytes (ZIP/OOXML: PK\x03\x04)
        $handle = fopen($archivo->getRealPath(), 'rb');
        $magic  = fread($handle, 4);
        fclose($handle);
        if ($magic !== "PK\x03\x04") {
            return back()->with('error', 'El archivo no tiene un formato XLSX válido.');
        }

        // 4. Leer el archivo con PhpSpreadsheet
        try {
            $spreadsheet = IOFactory::load($archivo->getRealPath());
        } catch (\Throwable $e) {
            Myhelp::EscribirEnLog($this, 'uploadExcel ERROR lectura', $e->getMessage());
            return back()->with('error', 'No se pudo leer el archivo: ' . $e->getMessage());
        }

        $hoja  = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray(null, true, true, true);

        if (empty($filas)) {
            return back()->with('error', 'El archivo está vacío.');
        }

        // 5. Normalizar encabezados (acepta snake_case y nombres legibles)
        $columnasRequeridas = ['nombre'];

        $aliasColumnas = [
            'nombre'         => 'nombre',
            'estado'         => 'nombre',
            'cuandoaplica'   => 'cuando_aplica',
            'aplica'         => 'cuando_aplica',
            'reglaclave'     => 'regla_clave',
            'regla'          => 'regla_clave',
            'puedepasara'    => 'puede_pasar_a',
            'puedepasar'     => 'puede_pasar_a',
            'pasara'         => 'puede_pasar_a',
            'pasar'          => 'puede_pasar_a',
        ];

        $normalizarTexto = fn (string $texto): string => strtr(
            mb_strtolower(trim($texto)),
            ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']
        );

        $filaEncabezados = array_shift($filas);
        $encabezado = [];
        foreach ($filaEncabezados as $col => $titulo) {
            $clave = preg_replace('/[^a-z0-9]/', '', $normalizarTexto((string) $titulo));
            if (isset($aliasColumnas[$clave])) {
                $encabezado[$col] = $aliasColumnas[$clave];
            }
        }

        $faltantes = array_diff($columnasRequeridas, array_values($encabezado));
        if (!empty($faltantes)) {
            return back()->with(
                'error',
                'El archivo no tiene las columnas requeridas. Faltan: ' . implode(', ', $faltantes)
            );
        }

        // 6. Construir registros (sin tocar la BD aún: primero validar todo)
        $errores    = [];
        $existentes = EstadoOperacion::pluck('nombre')->flip();
        $registros  = [];

        foreach ($filas as $numFila => $fila) {
            $filaExcel = $numFila + 1; // fila real en el Excel (el encabezado es la 1)

            $registro = [];
            foreach ($encabezado as $col => $claveCanonica) {
                $valorNuevo = $fila[$col] ?? null;
                // Varias columnas pueden mapear a la misma clave: gana la primera con valor
                if (!array_key_exists($claveCanonica, $registro)
                    || (($registro[$claveCanonica] === null || $registro[$claveCanonica] === '')
                        && $valorNuevo !== null && $valorNuevo !== '')) {
                    $registro[$claveCanonica] = $valorNuevo;
                }
            }

            // Omitir filas completamente vacías
            if (!array_filter($registro, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }

            $nombre = trim((string) ($registro['nombre'] ?? ''));
            if ($nombre === '') {
                $errores[] = "Fila {$filaExcel}: falta el nombre (obligatorio)";
                continue;
            }

            $registros[] = [
                'nombre'          => mb_substr(strip_tags($nombre), 0, 255),
                'cuando_aplica'   => strip_tags((string) ($registro['cuando_aplica'] ?? '')),
                'regla_clave'     => strip_tags((string) ($registro['regla_clave'] ?? '')),
                'puede_pasar_a'   => mb_substr(strip_tags((string) ($registro['puede_pasar_a'] ?? '')), 0, 255),
            ];
        }

        if (!empty($errores)) {
            return back()->with('error', 'No se importó nada por los siguientes problemas: ' . implode(' | ', $errores));
        }
        if (empty($registros)) {
            return back()->with('error', 'El archivo no contiene filas de datos.');
        }

        // 7. Insertar/actualizar registros (upsert por nombre)
        $creados      = 0;
        $actualizados = 0;
        DB::beginTransaction();
        try {
            foreach ($registros as $registro) {
                if (isset($existentes[$registro['nombre']])) {
                    EstadoOperacion::where('nombre', $registro['nombre'])->update($registro);
                    $actualizados++;
                } else {
                    EstadoOperacion::create($registro);
                    $creados++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Myhelp::EscribirEnLog($this, 'uploadExcel ERROR inserción', $e->getMessage());
            return back()->with('error', 'Error al guardar los registros: ' . $e->getMessage());
        }

        Myhelp::EscribirEnLog($this, 'uploadExcel OK', "Creados: {$creados} | Actualizados: {$actualizados}");
        return back()->with('success', "Importación completada: {$creados} creado(s), {$actualizados} actualizado(s).");
    }

}
