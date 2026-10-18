<?php

namespace App\Http\Controllers;

use App\Models\Producto;
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
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductoController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'Producto';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct( protected \App\Services\ListaDesplegableService $listaDesplegableService ) {
        $this->thisAtributos = (new Producto())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' Productos '));
        $Productos = $this->Filtros($request)->with('distribucionesPorTallas')->get();
        $listasDesplegables = array_change_key_case($this->listaDesplegableService->getOptionsByCategoriaGrouped('producto'), CASE_LOWER);

        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $Productos),
            'total' => $Productos->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            // 'losSelect'         => $this->losSelect(Producto::class, ' Producto','proveeNombre'),
             'losSelect'         => [
                 'Categoria_original'  => $listasDesplegables['categoria_original'] ?? [],
                 'Familia_normalizada' => $listasDesplegables['familia_normalizada'] ?? [],
                 'Control_de_variante' => $listasDesplegables['control_de_variante'] ?? [],
                 'Opciones'            => $listasDesplegables['opciones'] ?? [],
             ],
 	        'titulos'           => Producto::getFillableWithTypes(),

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
        $Productos = Producto::query();
        if ($request->has('search')) {
            $Productos = $Productos->where(function ($query) use ($request) {
                $query->where('Codigo_interno', 'LIKE', "%" . $request->search . "%")
                    ->orWhere('ID_producto_Shopify', 'LIKE', "%" . $request->search . "%")
                    ->orWhere('Categoria_original', 'LIKE', "%" . $request->search . "%")
                    ->orWhere('Titulo_Shopify', 'LIKE', "%" . $request->search . "%")
                ;
            });
        }

        if ($request->has(['field', 'order'])) {
            $Productos = $Productos->orderBy($request->field, $request->order);
        } else
            $Productos = $Productos->orderBy('updated_at', 'DESC');
        return $Productos;
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

    public function PerPageAndPaginate($request, $Productos) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $Productos->forPage($page, $perPage),
            $Productos->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:Productos');
        DB::beginTransaction();
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $Producto = Producto::create($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'STORE:Productos EXITOSO', 'Producto id:' . $Producto->id . ' | ' . $Producto->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $Producto->nombre]));
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:Productos');
        DB::beginTransaction();
        $Producto = Producto::findOrFail($id);
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $Producto->update($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE:Productos EXITOSO', 'Producto id:' . $Producto->id . ' | ' . $Producto->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $Producto->nombre]));
    }

    /**
     * Sincroniza la distribución por tallas (plantilla) de un producto vía pivote:
     * actualiza las filas que traen id (solo talla y cantidad planificada),
     * crea+vincula las nuevas y desvincula (sin borrar) las que ya no vienen.
     */
    public function syncDistribucion(Request $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin SYNC-DISTRIBUCION:Productos');
        DB::beginTransaction();

        $Producto = Producto::findOrFail($id);
        $conservados = [];
        foreach ($request->input('distribucion_tallas', []) as $talla) {
            if (!empty($talla['id'])) {
                $distribucion = Distribucion_por_tallas::find($talla['id']);
                if ($distribucion) {
                    $distribucion->update([
                        'talla' => $talla['talla'] ?? $distribucion->talla,
                        'cantidad_planificada' => (int) ($talla['cantidad_planificada'] ?? 0),
                    ]);
                    $conservados[] = $distribucion->id;
                }
                continue;
            }
            if (empty($talla['talla']) && empty($talla['cantidad_planificada'])) {
                continue;
            }
            $nueva = Distribucion_por_tallas::create([
                'grupo' => null,
                'talla' => $talla['talla'] ?? null,
                'cantidad_planificada' => (int) ($talla['cantidad_planificada'] ?? 0),
            ]);
            $conservados[] = $nueva->id;
        }
        $Producto->distribucionesPorTallas()->sync($conservados);

        DB::commit();
        Myhelp::EscribirEnLog($this, 'SYNC-DISTRIBUCION:Productos EXITOSO', 'Producto id:' . $Producto->id . ' | ' . $Producto->Codigo_interno, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $Producto->Codigo_interno]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($Productoid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:Productos');
        $Producto = Producto::find($Productoid);
        $elnombre = $Producto->nombre;
        $Producto->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:Productos', 'Producto id:' . $Producto->id . ' | ' . $Producto->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $Producto = Producto::whereIn('id', $request->id);
        $Producto->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

    // ─────────────────────────────────────────────────────────
    //  CARGA MASIVA DESDE EXCEL (general, sin FK)
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

        $hoja  = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray(null, true, true, true);

        if (empty($filas)) {
            return back()->with('error', 'El archivo está vacío.');
        }

        // 5. Normalizar encabezados (acepta snake_case y nombres legibles)
        $columnasRequeridas = ['Codigo_interno', 'Titulo_Shopify'];

        $aliasColumnas = [
            'codigointerno'      => 'Codigo_interno',
            'codigo'             => 'Codigo_interno',
            'tituloshopify'      => 'Titulo_Shopify',
            'titulo'             => 'Titulo_Shopify',
            'idproductoshopify'  => 'ID_producto_Shopify',
            'idshopify'          => 'ID_producto_Shopify',
            'categoriaoriginal'  => 'Categoria_original',
            'categoria'          => 'Categoria_original',
            'familianormalizada' => 'Familia_normalizada',
            'familia'            => 'Familia_normalizada',
            'controldevariante'  => 'Control_de_variante',
            'variante'           => 'Control_de_variante',
            'opciones'           => 'Opciones',
            'rango'              => 'Opciones',
            'rangotallas'        => 'Opciones',
            'estado'             => 'Estado',
            'observaciones'      => 'Observaciones',
            'descripcion'        => 'descripcion',
            'preciopredicho'     => 'precio_predicho',
            'preciopreventa'     => 'precio_preventa',
        ];

        $normalizarTexto = fn (string $texto): string => strtr(
            mb_strtolower(trim($texto)),
            ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']
        );

        $filaEncabezados = array_shift($filas);
        $encabezado = [];
        $encabezadosIgnorados = [];
        $clavesAlias = array_keys($aliasColumnas);
        usort($clavesAlias, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($filaEncabezados as $col => $titulo) {
            $clave = preg_replace('/[^a-z0-9]/', '', $normalizarTexto((string) $titulo));
            if ($clave === '') {
                continue;
            }
            if (isset($aliasColumnas[$clave])) {
                $encabezado[$col] = $aliasColumnas[$clave];
                continue;
            }
            // Encabezado no exacto (ej: "Opciones tallas"): aceptar el alias mas largo
            // que sea prefijo, para no ignorar columnas por variantes del nombre.
            $mapeada = null;
            foreach ($clavesAlias as $alias) {
                if (str_starts_with($clave, $alias)) {
                    $mapeada = $aliasColumnas[$alias];
                    break;
                }
            }
            if ($mapeada !== null) {
                $encabezado[$col] = $mapeada;
            } else {
                $encabezadosIgnorados[] = trim((string) $titulo);
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
        $texto = function ($valor, int $max = 255): string {
            return mb_substr(strip_tags((string) ($valor ?? '')), 0, $max);
        };
        $entero = function ($valor) {
            return is_numeric($valor) ? (int) $valor : null;
        };

        $errores    = [];
        $existentes = Producto::pluck('Codigo_interno')->flip();
        $registros  = [];

        foreach ($filas as $numFila => $fila) {
            $filaExcel = $numFila + 1; // fila real en el Excel (el encabezado es la 1)

            $registro = [];
            foreach ($encabezado as $col => $claveCanonica) {
                $valorNuevo = $fila[$col] ?? null;
                // Varias columnas pueden mapear a la misma clave (ej: "Rango" y "Opciones"):
                // gana la primera con valor; una vacía no pisa a una ya cargada.
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

            $codigo  = trim((string) ($registro['Codigo_interno'] ?? ''));
            $titulo  = trim((string) ($registro['Titulo_Shopify'] ?? ''));

            if ($codigo === '' || $titulo === '') {
                $errores[] = "Fila {$filaExcel}: faltan datos obligatorios (Codigo interno y Titulo Shopify)";
                continue;
            }

            $registros[] = [
                'Codigo_interno'        => $codigo,
                'Titulo_Shopify'        => $titulo,
                'ID_producto_Shopify'   => $texto($registro['ID_producto_Shopify'] ?? ''),
                'Categoria_original'    => $texto($registro['Categoria_original'] ?? ''),
                'Familia_normalizada'   => $texto($registro['Familia_normalizada'] ?? ''),
                'Control_de_variante'   => $texto($registro['Control_de_variante'] ?? ''),
                'Opciones'              => $texto($registro['Opciones'] ?? ''),
                'Estado'                => $texto($registro['Estado'] ?? ''),
                'Observaciones'         => $texto($registro['Observaciones'] ?? ''),
                'descripcion'           => mb_substr(strip_tags((string) ($registro['descripcion'] ?? '')), 0, 5000),
                'precio_predicho'       => $entero($registro['precio_predicho'] ?? null),
                'precio_preventa'       => $entero($registro['precio_preventa'] ?? null),
            ];
        }

        if (!empty($errores)) {
            return back()->with('error', 'No se importó nada por los siguientes problemas: ' . implode(' | ', $errores));
        }
        if (empty($registros)) {
            return back()->with('error', 'El archivo no contiene filas de datos.');
        }

        // 7. Insertar/actualizar registros (upsert por Codigo_interno)
        $creados      = 0;
        $actualizados = 0;
        DB::beginTransaction();
        try {
            foreach ($registros as $registro) {
                if (isset($existentes[$registro['Codigo_interno']])) {
                    Producto::where('Codigo_interno', $registro['Codigo_interno'])->update($registro);
                    $actualizados++;
                } else {
                    // Columnas NOT NULL sin default que el archivo puede no traer
                    $registro += [
                        'ID_producto_Shopify' => '',
                        'Categoria_original'  => '',
                        'Familia_normalizada' => '',
                        'Control_de_variante' => '',
                        'Opciones'            => '',
                        'Estado'              => '',
                        'Observaciones'       => '',
                        'descripcion'         => '',
                        'precio_predicho'     => 0,
                        'precio_preventa'     => 0,
                    ];
                    Producto::create($registro);
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
        $avisoIgnoradas = empty($encabezadosIgnorados)
            ? ''
            : ' Columnas ignoradas (encabezado no reconocido): ' . implode(', ', $encabezadosIgnorados) . '.';
        return back()->with('success', "Importación completada: {$creados} creado(s), {$actualizados} actualizado(s).{$avisoIgnoradas}");
    }

}
