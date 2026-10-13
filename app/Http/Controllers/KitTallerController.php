<?php

namespace App\Http\Controllers;

use App\Models\KitTaller;
use App\Models\Material;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KitTallerController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'KitTaller';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct() {
//        $this->middleware('permission:create KitTaller', ['only' => ['create', 'store']]);
//        $this->middleware('permission:read KitTaller', ['only' => ['index', 'show']]);
//        $this->middleware('permission:update KitTaller', ['only' => ['edit', 'update']]);
//        $this->middleware('permission:delete KitTaller', ['only' => ['destroy', 'destroyBulk']]);
        $this->thisAtributos = (new KitTaller())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' KitTallers '));
        $KitTallers = $this->Filtros($request)->get()->map(function ($kit) {
            $kit->orden_produccion_numero = $kit->ordenProduccion?->numero_op;
            $kit->material_codigo = $kit->material?->codigo_material;
            return $kit;
        });
//        $losSelect = $this->Dependencias();


        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $KitTallers),
            'total' => $KitTallers->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->losSelect(KitTaller::class, ' KitTaller','proveeNombre'),
	        'titulos'           => KitTaller::getFillableWithTypes(),

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
        $KitTallers = KitTaller::query();
        if ($request->has('search')) {
            $KitTallers = $KitTallers->where(function ($query) use ($request) {
                $query->where('nombre', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('codigo', 'LIKE', "%" . $request->search . "%")
                    //                    ->orWhere('identificacion', 'LIKE', "%" . $request->search . "%")
                ;
            });
        }

        if ($request->has(['field', 'order'])) {
            $KitTallers = $KitTallers->orderBy($request->field, $request->order);
        } else
            $KitTallers = $KitTallers->orderBy('updated_at', 'DESC');
        return $KitTallers;
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

    public function PerPageAndPaginate($request, $KitTallers) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $KitTallers->forPage($page, $perPage),
            $KitTallers->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:KitTallers');
        DB::beginTransaction();
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $KitTaller = KitTaller::create($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'STORE:KitTallers EXITOSO', 'KitTaller id:' . $KitTaller->id . ' | ' . $KitTaller->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $KitTaller->nombre]));
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:KitTallers');
        DB::beginTransaction();
        $KitTaller = KitTaller::findOrFail($id);
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $KitTaller->update($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE:KitTallers EXITOSO', 'KitTaller id:' . $KitTaller->id . ' | ' . $KitTaller->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $KitTaller->nombre]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($KitTallerid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:KitTallers');
        $KitTaller = KitTaller::find($KitTallerid);
        $elnombre = $KitTaller->nombre;
        $KitTaller->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:KitTallers', 'KitTaller id:' . $KitTaller->id . ' | ' . $KitTaller->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $KitTaller = KitTaller::whereIn('id', $request->id);
        $KitTaller->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

    // ─────────────────────────────────────────────────────────
    //  KIT DE UNA ORDEN DE PRODUCCIÓN (para modal desde OP)
    // ─────────────────────────────────────────────────────────
    public function porOrden(int $id): JsonResponse
    {
        $kits = KitTaller::where('orden_produccion_id', $id)
            ->with(['material' => fn ($q) => $q->select('id', 'codigo_material', 'nombre_base')])
            ->orderBy('id')
            ->get()
            ->map(fn (KitTaller $kit) => [
                'id'                   => $kit->id,
                'material_codigo'      => $kit->material?->codigo_material,
                'material_nombre'      => $kit->material?->nombre_base,
                'estado_requerido'     => $kit->estado_requerido,
                'unidad'               => $kit->unidad,
                'cantidad_requerida'   => $kit->cantidad_requerida,
                'listo'                => (bool) $kit->listo,
                'cantidad_disponible'  => $kit->cantidad_disponible,
                'cantidad_preparada'   => $kit->cantidad_preparada,
                'falta'                => $kit->falta,
                'observaciones'        => $kit->observaciones,
            ]);

        return response()->json(['kits' => $kits]);
    }

    // ─────────────────────────────────────────────────────────
    //  CARGA MASIVA DESDE EXCEL
    // ─────────────────────────────────────────────────────────
    public function uploadExcel(Request $request): RedirectResponse
    {
        Myhelp::EscribirEnLog($this, 'uploadExcel: inicio');

        // 1. Validaciones de seguridad del archivo (previas a cualquier lectura)
        $request->validate([
            'archivo' => [
                'required',
                'file',
                'max:10240',    // máx 10 MB
                'mimes:xlsx',   // valida por extensión del cliente (suficiente como primera capa)
            ],
            'orden_produccion_id' => ['nullable', 'integer', 'exists:orden_produccions,id'],
        ]);

        $archivo = $request->file('archivo');

        // 2. Doble-verificación de extensión (defensa en profundidad)
        if (strtolower($archivo->getClientOriginalExtension()) !== 'xlsx') {
            return back()->with('error', 'Solo se permiten archivos con extensión .xlsx');
        }

        // 3. Verificación de firma de bytes (magic bytes del ZIP/OOXML: PK\x03\x04)
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

        $hoja   = $spreadsheet->getActiveSheet();
        $filas  = $hoja->toArray(null, true, true, true); // celdas indexadas por letra

        if (empty($filas)) {
            return back()->with('error', 'El archivo está vacío.');
        }

        // 5. Normalizar encabezados: acepta el formato antiguo (snake_case, con ids)
        //    y el formato nuevo de la planilla (nombres legibles con acentos y espacios).
        $columnasRequeridas = [
            'material', 'estado_requerido', 'unidad', 'cantidad_requerida',
            'listo', 'cantidad_disponible', 'cantidad_preparada', 'falta', 'observaciones',
        ];

        $aliasColumnas = [
            'ordenproduccionid'  => 'orden_produccion_id',
            'materialid'         => 'material',
            'material'           => 'material',
            'materialcomponente' => 'material',
            'codigomaterial'     => 'material',
            'estadorequerido'    => 'estado_requerido',
            'unidad'             => 'unidad',
            'cantidadrequerida'  => 'cantidad_requerida',
            'cantidaddisponible' => 'cantidad_disponible',
            'cantidadpreparada'  => 'cantidad_preparada',
            'listo'              => 'listo',
            'falta'              => 'falta',
            'observaciones'      => 'observaciones',
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
        $parsearListo = function ($valor): bool {
            $clave = preg_replace('/[^a-z]/', '', mb_strtolower(trim((string) $valor)));
            return in_array($clave, ['si', 's', 'yes', 'y', 'true', 't', '1'], true);
        };

        $ordenProduccionId = $request->filled('orden_produccion_id')
            ? (int) $request->input('orden_produccion_id')
            : null;
        $errores   = [];
        $registros = [];

        foreach ($filas as $numFila => $fila) {
            $filaExcel = $numFila + 1; // fila real en el Excel (el encabezado es la 1)

            $registro = [];
            foreach ($encabezado as $col => $claveCanonica) {
                $registro[$claveCanonica] = $fila[$col] ?? null;
            }

            // Omitir filas completamente vacías
            if (!array_filter($registro, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }

            // La OP llega desde el botón de la fila; si el archivo la trae, se usa como respaldo
            if ($ordenProduccionId === null && !empty($registro['orden_produccion_id'])) {
                $ordenProduccionId = (int) $registro['orden_produccion_id'];
            }

            // El material se recibe por código y se resuelve al id en materials (codigo_material).
            // En el formato antiguo de la planilla la columna trae el id numérico: se usa como respaldo.
            $codigoMaterial = trim((string) ($registro['material'] ?? ''));
            $material = $codigoMaterial === ''
                ? null
                : Material::where('codigo_material', $codigoMaterial)->first()
                    ?? (is_numeric($codigoMaterial) ? Material::find((int) $codigoMaterial) : null);

            if (!$material) {
                $errores[] = "Fila {$filaExcel}: no existe el material con código '{$codigoMaterial}'";
                continue;
            }

            $registros[] = [
                'orden_produccion_id'  => $ordenProduccionId,
                'material_id'          => (int) $material->id,
                'estado_requerido'     => mb_substr(strip_tags((string) ($registro['estado_requerido'] ?? '')), 0, 255),
                'unidad'               => mb_substr(strip_tags((string) ($registro['unidad'] ?? '')), 0, 100),
                'cantidad_requerida'   => is_numeric($registro['cantidad_requerida'] ?? null) ? (float) $registro['cantidad_requerida'] : null,
                'listo'                => $parsearListo($registro['listo'] ?? ''),
                'cantidad_disponible'  => is_numeric($registro['cantidad_disponible'] ?? null) ? (float) $registro['cantidad_disponible'] : null,
                'cantidad_preparada'   => is_numeric($registro['cantidad_preparada'] ?? null) ? (float) $registro['cantidad_preparada'] : null,
                'falta'                => is_numeric($registro['falta'] ?? null) ? (float) $registro['falta'] : null,
                'observaciones'        => mb_substr(strip_tags((string) ($registro['observaciones'] ?? '')), 0, 1000),
            ];
        }

        if ($ordenProduccionId === null) {
            return back()->with('error', 'No se indicó la orden de producción (id) para la importación.');
        }
        if (!empty($errores)) {
            return back()->with('error', 'No se importó nada por los siguientes problemas: ' . implode(' | ', $errores));
        }
        if (empty($registros)) {
            return back()->with('error', 'El archivo no contiene filas de datos.');
        }

        // 7. Insertar registros
        DB::beginTransaction();
        try {
            foreach ($registros as $registro) {
                KitTaller::create($registro);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Myhelp::EscribirEnLog($this, 'uploadExcel ERROR inserción', $e->getMessage());
            return back()->with('error', 'Error al guardar los registros: ' . $e->getMessage());
        }

        $insertados = count($registros);
        Myhelp::EscribirEnLog($this, 'uploadExcel OK', "Insertados: {$insertados}");
        return back()->with('success', "Se importaron {$insertados} registros correctamente.");
    }

}
