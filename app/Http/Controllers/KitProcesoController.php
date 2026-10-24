<?php

namespace App\Http\Controllers;

use App\Models\KitProceso;
use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\Proveedor;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KitProcesoController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'KitProceso';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct() {
        $this->thisAtributos = (new KitProceso())->getFillable();
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' KitProcesos '));

        $todas = $this->Filtros($request)
            ->with([
                'ordenProduccion:id,numero_op',
                'material:id,codigo_material,nombre_base,existencia_actual',
                'proveedor:id,nombre',
            ])
            ->get()
            ->map(function (KitProceso $kit) {
                $kit->orden_produccion_numero = $kit->ordenProduccion?->numero_op;
                $kit->material_codigo = $kit->material?->codigo_material;
                $kit->material_nombre = $kit->material?->nombre_base;
                $kit->proveedor_nombre = $kit->proveedor?->nombre;

                // La cantidad disponible siempre es la existencia actual del
                // material; el faltante y el listo se recalculan con ella.
                $disponible = (float) ($kit->material?->existencia_actual ?? 0);
                $preparada = (float) ($kit->cantidad_preparada ?? 0);
                $requerida = (float) ($kit->cantidad_requerida ?? 0);

                $kit->cantidad_disponible = $disponible;
                $kit->falta = $disponible - $preparada;
                $kit->listo = $requerida > 0 && $preparada >= $requerida;

                // Los datos para la vista ya vienen aplanados; no hace falta
                // enviar las relaciones completas al cliente.
                $kit->makeHidden(['ordenProduccion', 'material', 'proveedor']);

                return $kit;
            });

        $resumen = $this->resumen($todas);
        $resumenPorOp = $this->resumenPorOp($todas);

        $perPage = $request->has('perPage') ? (int) $request->perPage : 10;

        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $todas),
            'total' => $todas->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order', 'orden_produccion_id']),
            'perPage' => $perPage,
            'numberPermissions' => $numberPermissions,
            'titulos' => KitProceso::getFillableWithTypes(),

            // Resumen de la selección (incluye todos los registros filtrados,
            // no solamente la página visible).
            'resumen' => $resumen,
            'resumenPorOp' => $resumenPorOp,

            // Catálogos para filtros y para el modal de edición.
            'ordenes' => $this->opcionesOrden(),
            'proveedores' => $this->opcionesProveedor(),
            'estadosRequeridos' => $this->estadosRequeridos(),
        ]);
    }

    public function Filtros($request): Builder {
        $KitProcesos = KitProceso::query();

        if ($request->filled('orden_produccion_id')) {
            $KitProcesos->where('orden_produccion_id', (int) $request->input('orden_produccion_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $KitProcesos->where(function ($query) use ($search) {
                $query->where('estado_requerido', 'LIKE', "%{$search}%")
                    ->orWhere('unidad', 'LIKE', "%{$search}%")
                    ->orWhere('observaciones', 'LIKE', "%{$search}%")
                    ->orWhereHas('ordenProduccion', fn ($op) => $op->where('numero_op', 'LIKE', "%{$search}%"))
                    ->orWhereHas('material', function ($material) use ($search) {
                        $material->where('codigo_material', 'LIKE', "%{$search}%")
                            ->orWhere('nombre_base', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('proveedor', fn ($proveedor) => $proveedor->where('nombre', 'LIKE', "%{$search}%"));
            });
        }

        $ordenables = [
            'orden_produccion_id', 'material_id', 'proveedor_id', 'estado_requerido',
            'unidad', 'cantidad_requerida', 'listo', 'cantidad_disponible',
            'cantidad_preparada', 'falta', 'observaciones', 'id', 'updated_at',
        ];

        if ($request->filled('field') && in_array($request->input('field'), $ordenables, true)) {
            $order = strtolower((string) $request->input('order')) === 'asc' ? 'asc' : 'desc';
            $KitProcesos->orderBy($request->input('field'), $order);
        } else {
            $KitProcesos->orderByDesc('orden_produccion_id')->orderBy('id');
        }

        return $KitProcesos;
    }

    //</editor-fold>

    public function PerPageAndPaginate($request, $KitProcesos) {
        $perPage = $request->has('perPage') ? (int) $request->perPage : 10;
        $page = request('page', 1);

        return new LengthAwarePaginator(
            $KitProcesos->forPage($page, $perPage),
            $KitProcesos->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    public function store(Request $request): RedirectResponse {
        Myhelp::EscribirEnLog($this, ' Begin STORE:KitProcesos');

        $datos = $request->validate([
            'orden_produccion_id' => ['required', 'integer', 'exists:orden_produccions,id'],
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedors,id'],
            'estado_requerido' => ['nullable', 'string', 'max:255'],
            'unidad' => ['nullable', 'string', 'max:100'],
            'cantidad_requerida' => ['nullable', 'numeric', 'min:0'],
            'cantidad_preparada' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        // La disponibilidad no se captura: es la existencia actual del material.
        $material = Material::findOrFail($datos['material_id']);
        $datos['cantidad_disponible'] = (float) ($material->existencia_actual ?? 0);

        DB::beginTransaction();
        $KitProceso = KitProceso::firstOrCreate(
            [
                'orden_produccion_id' => $datos['orden_produccion_id'],
                'material_id' => $datos['material_id'],
            ],
            $datos
        );
        DB::commit();

        Myhelp::EscribirEnLog($this, 'STORE:KitProcesos EXITOSO', 'KitProceso id:' . $KitProceso->id, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => 'Kit ' . $KitProceso->id]));
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
        Myhelp::EscribirEnLog($this, ' Begin UPDATE:KitProcesos');

        $datos = $request->validate([
            'estado_requerido' => ['required', 'string', 'max:255'],
            'proveedor_id' => ['required', 'integer', 'exists:proveedors,id'],
            'cantidad_preparada' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();
        $KitProceso = KitProceso::with('material:id,existencia_actual')->findOrFail($id);
        $KitProceso->estado_requerido = $datos['estado_requerido'];
        $KitProceso->proveedor_id = $datos['proveedor_id'];

        // La cantidad preparada es opcional: si el formulario no la envía se
        // conserva el valor actual (puede ser null) en lugar de volverlo cero.
        if (array_key_exists('cantidad_preparada', $datos) && $datos['cantidad_preparada'] !== null) {
            $KitProceso->cantidad_preparada = (float) $datos['cantidad_preparada'];
        }

        $KitProceso->cantidad_disponible = (float) ($KitProceso->material?->existencia_actual ?? 0);
        $KitProceso->observaciones = $datos['observaciones'] ?? null;
        $KitProceso->save();
        DB::commit();

        Myhelp::EscribirEnLog($this, 'UPDATE:KitProcesos EXITOSO', 'KitProceso id:' . $KitProceso->id, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => 'Kit ' . $KitProceso->id]));
    }

    /**
     * Guarda de una sola vez todos los kits de una OP. La pantalla de edición
     * muestra la lista completa de materiales de la orden.
     */
    public function updatePorOrden(Request $request, int $id): RedirectResponse
    {
        Myhelp::EscribirEnLog($this, ' Begin UPDATE:KitProcesos por OP');

        OrdenProduccion::findOrFail($id);

        $datos = $request->validate([
            'filas' => ['required', 'array', 'min:1'],
            'filas.*.id' => ['required', 'integer', 'exists:kit_procesos,id'],
            'filas.*.estado_requerido' => ['required', 'string', 'max:255'],
            'filas.*.proveedor_id' => ['required', 'integer', 'exists:proveedors,id'],
            'filas.*.cantidad_preparada' => ['nullable', 'numeric', 'min:0'],
            'filas.*.observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $kits = KitProceso::where('orden_produccion_id', $id)
            ->with('material:id,existencia_actual')
            ->get()
            ->keyBy('id');

        DB::beginTransaction();
        foreach ($datos['filas'] as $fila) {
            $kit = $kits->get((int) $fila['id']);
            if (!$kit) {
                continue;
            }

            $kit->estado_requerido = $fila['estado_requerido'];
            $kit->proveedor_id = $fila['proveedor_id'];

            // Si la fila no trae cantidad preparada, se conserva la que ya
            // tenía el kit. El campo es opcional y no debe reiniciarse.
            if (array_key_exists('cantidad_preparada', $fila) && $fila['cantidad_preparada'] !== null) {
                $kit->cantidad_preparada = (float) $fila['cantidad_preparada'];
            }

            $kit->cantidad_disponible = (float) ($kit->material?->existencia_actual ?? 0);
            $kit->observaciones = $fila['observaciones'] ?? null;
            $kit->save();
        }
        DB::commit();

        Myhelp::EscribirEnLog($this, 'UPDATE:KitProcesos por OP EXITOSO', 'OrdenProduccion id:' . $id, false);
        return back()->with('success', 'Kits de la orden actualizados correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($KitProcesoid) {
        Myhelp::EscribirEnLog($this, 'DELETE:KitProcesos');

        $KitProceso = KitProceso::findOrFail($KitProcesoid);
        $identificador = 'Kit ' . $KitProceso->id;
        $KitProceso->delete();

        Myhelp::EscribirEnLog($this, 'DELETE:KitProcesos', 'KitProceso id:' . $KitProceso->id . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $identificador]));
    }

    public function destroyBulk(Request $request) {
        KitProceso::whereIn('id', $request->id)->delete();

        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

    // ─────────────────────────────────────────────────────────
    //  KIT DE UNA ORDEN DE PRODUCCIÓN (para modal desde OP)
    // ─────────────────────────────────────────────────────────
    public function porOrden(int $id): JsonResponse
    {
        $orden = OrdenProduccion::find($id);

        $kits = KitProceso::where('orden_produccion_id', $id)
            ->with([
                'material' => fn ($q) => $q->select('id', 'codigo_material', 'nombre_base', 'existencia_actual'),
                'proveedor:id,nombre',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (KitProceso $kit) {
                $disponible = (float) ($kit->material?->existencia_actual ?? 0);
                $requerida = (float) ($kit->cantidad_requerida ?? 0);
                // "Preparada" es opcional: si no está registrada se envía null para que el
                // modal la muestre como "—" y la asuma igual a la requerida, no como 0.
                $preparada = $kit->cantidad_preparada === null ? null : (float) $kit->cantidad_preparada;
                $preparadaEfectiva = $preparada ?? $requerida;

                return [
                    'id' => $kit->id,
                    'material_codigo' => $kit->material?->codigo_material,
                    'material_nombre' => $kit->material?->nombre_base,
                    'proveedor_id' => $kit->proveedor_id,
                    'proveedor_nombre' => $kit->proveedor?->nombre,
                    'estado_requerido' => $kit->estado_requerido,
                    'unidad' => $kit->unidad,
                    'cantidad_requerida' => $requerida,
                    // La disponibilidad es la existencia actual del material.
                    'cantidad_disponible' => $disponible,
                    'cantidad_preparada' => $preparada,
                    'falta' => $disponible - $preparadaEfectiva,
                    'listo' => $requerida > 0 && $preparadaEfectiva >= $requerida,
                    'observaciones' => $kit->observaciones,
                ];
            });

        return response()->json([
            'orden' => $orden ? [
                'id' => $orden->id,
                'numero_op' => $orden->numero_op,
            ] : null,
            'kits' => $kits,
        ]);
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
        $magic = fread($handle, 4);
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

        $hoja = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray(null, true, true, true); // celdas indexadas por letra

        if (empty($filas)) {
            return back()->with('error', 'El archivo está vacío.');
        }

        // 5. Normalizar encabezados: acepta el formato antiguo (snake_case, con ids)
        //    y el formato nuevo de la planilla (nombres legibles con acentos y espacios).
        // La disponibilidad, el faltante y el listo no se piden: se calculan
        // con la existencia del material y la cantidad preparada.
        $columnasRequeridas = [
            'material', 'estado_requerido', 'unidad', 'cantidad_requerida',
            'cantidad_preparada', 'observaciones',
        ];

        $aliasColumnas = [
            'ordenproduccionid' => 'orden_produccion_id',
            'materialid' => 'material',
            'material' => 'material',
            'materialcomponente' => 'material',
            'codigomaterial' => 'material',
            'proveedor' => 'proveedor',
            'proveedorid' => 'proveedor',
            'proveedornombre' => 'proveedor',
            'estadorequerido' => 'estado_requerido',
            'unidad' => 'unidad',
            'cantidadrequerida' => 'cantidad_requerida',
            'cantidaddisponible' => 'cantidad_disponible',
            'cantidadpreparada' => 'cantidad_preparada',
            'listo' => 'listo',
            'falta' => 'falta',
            'observaciones' => 'observaciones',
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
        $ordenProduccionId = $request->filled('orden_produccion_id')
            ? (int) $request->input('orden_produccion_id')
            : null;
        $errores = [];
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

            // El proveedor es opcional; si la columna viene, se valida por nombre exacto o id.
            $proveedorTexto = trim((string) ($registro['proveedor'] ?? ''));
            $proveedor = null;
            if ($proveedorTexto !== '') {
                $proveedor = Proveedor::where('nombre', $proveedorTexto)->first()
                    ?? (is_numeric($proveedorTexto) ? Proveedor::find((int) $proveedorTexto) : null);

                if (!$proveedor) {
                    $errores[] = "Fila {$filaExcel}: no existe el proveedor '{$proveedorTexto}'";
                    continue;
                }
            }

            $registros[] = [
                'orden_produccion_id' => $ordenProduccionId,
                'material_id' => (int) $material->id,
                'proveedor_id' => $proveedor?->id,
                'estado_requerido' => mb_substr(strip_tags((string) ($registro['estado_requerido'] ?? '')), 0, 255),
                'unidad' => mb_substr(strip_tags((string) ($registro['unidad'] ?? '')), 0, 100),
                'cantidad_requerida' => is_numeric($registro['cantidad_requerida'] ?? null) ? (float) $registro['cantidad_requerida'] : null,
                'cantidad_disponible' => (float) ($material->existencia_actual ?? 0),
                'cantidad_preparada' => is_numeric($registro['cantidad_preparada'] ?? null) ? (float) $registro['cantidad_preparada'] : 0,
                'observaciones' => mb_substr(strip_tags((string) ($registro['observaciones'] ?? '')), 0, 1000),
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
                KitProceso::create($registro);
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

    // ─────────────────────────────────────────────────────────
    //  RESUMEN / CATÁLOGOS
    // ─────────────────────────────────────────────────────────

    /**
     * Totales de la selección completa (no solo de la página visible).
     * Las cantidades se agrupan por unidad de medida porque no se pueden
     * sumar entre sí (unidades, metros y kilos no son comparables).
     */
    private function resumen($filas): array {
        return [
            'registros' => $filas->count(),
            'listos' => $filas->filter(fn ($f) => (bool) $f->listo)->count(),
            'estados' => $this->contarEstados($filas),
            'porUnidad' => $this->resumenPorUnidad($filas),
        ];
    }

    /**
     * Totales por unidad de medida (und, m, kg, …). Sumar todo con todo
     * daría una cifra sin significado.
     */
    private function resumenPorUnidad($filas): array {
        return $filas
            ->groupBy(fn ($f) => trim((string) ($f->unidad ?? '')) ?: 'Sin unidad')
            ->map(function ($grupo, $unidad) {
                return [
                    'unidad' => $unidad,
                    'registros' => $grupo->count(),
                    'requerida' => round((float) $grupo->sum(fn ($f) => (float) ($f->cantidad_requerida ?? 0)), 4),
                    'disponible' => round((float) $grupo->sum(fn ($f) => (float) ($f->cantidad_disponible ?? 0)), 4),
                    'preparada' => round((float) $grupo->sum(fn ($f) => (float) ($f->cantidad_preparada ?? 0)), 4),
                    'falta' => round((float) $grupo->sum(fn ($f) => (float) ($f->falta ?? 0)), 4),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * Un resumen por OP para poder leer de un vistazo cuántos estados
     * requeridos tiene cada orden, sin tener que sumar filas a mano.
     */
    private function resumenPorOp($filas): array {
        return $filas
            ->groupBy('orden_produccion_id')
            ->map(function ($grupo, $opId) {
                $primera = $grupo->first();

                return [
                    'orden_produccion_id' => (int) $opId,
                    'numero_op' => $primera->orden_produccion_numero ?: ('OP #' . $opId),
                    'registros' => $grupo->count(),
                    'listos' => $grupo->filter(fn ($f) => (bool) $f->listo)->count(),
                    'estados' => $this->contarEstados($grupo),
                    'porUnidad' => $this->resumenPorUnidad($grupo),
                ];
            })
            ->sortBy('numero_op', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Agrupa los estados por una raíz común para que variantes como
     * "Terminado" y "Terminada" cuenten como un solo estado.
     */
    private function contarEstados($filas): array {
        $grupos = [];

        foreach ($filas as $fila) {
            $info = $this->normalizarEstadoRequerido($fila->estado_requerido);
            if (!isset($grupos[$info['clave']])) {
                $grupos[$info['clave']] = [
                    'clave' => $info['clave'],
                    'label' => $info['label'],
                    'count' => 0,
                ];
            }
            $grupos[$info['clave']]['count']++;
        }

        usort($grupos, fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']));

        return array_values($grupos);
    }

    private function normalizarEstadoRequerido(?string $estado): array {
        $original = trim((string) $estado);

        if ($original === '') {
            return ['clave' => 'sin-estado', 'label' => 'Sin estado'];
        }

        $texto = Str::ascii($original);
        $texto = mb_strtolower($texto);
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
        $texto = trim(preg_replace('/\s+/', ' ', $texto));

        $conectores = ['de', 'del', 'la', 'el', 'los', 'las', 'y', 'en', 'con', 'sin', 'para', 'por', 'al'];
        $sufijos = ['adas', 'ados', 'idos', 'ida', 'ido', 'ada', 'ado', 'as', 'os', 'a', 'o', 's'];
        $raices = [];

        foreach (explode(' ', $texto) as $palabra) {
            if ($palabra === '') {
                continue;
            }
            if (in_array($palabra, $conectores, true) || mb_strlen($palabra) <= 3) {
                $raices[] = $palabra;
                continue;
            }

            $raiz = $palabra;
            foreach ($sufijos as $sufijo) {
                if (mb_strlen($palabra) > mb_strlen($sufijo) + 2 && str_ends_with($palabra, $sufijo)) {
                    $raiz = mb_substr($palabra, 0, -mb_strlen($sufijo));
                    break;
                }
            }
            $raices[] = $raiz;
        }

        $clave = implode(' ', $raices);

        $etiquetas = [
            'materi prim' => 'Materia prima',
            'materia prima' => 'Materia prima',
            'termin' => 'Terminado',
            'cort interven' => 'Cortada + intervenida',
            'confeccion' => 'Confeccionado',
            'empac' => 'Empacado',
            'list' => 'Listo',
            'proces' => 'Proceso',
            'faltante' => 'Faltante',
            'complet' => 'Completa',
            'prepar' => 'Preparado',
            'revis' => 'Revisado',
            'aprob' => 'Aprobado',
            'entreg' => 'Entregado',
            'pendient' => 'Pendiente',
        ];

        return [
            'clave' => $clave,
            'label' => $etiquetas[$clave] ?? $this->tituloEstado($original),
        ];
    }

    private function tituloEstado(string $texto): string {
        $texto = trim($texto);
        if ($texto === '') {
            return 'Sin estado';
        }

        return mb_strtoupper(mb_substr($texto, 0, 1)) . mb_strtolower(mb_substr($texto, 1));
    }

    private function opcionesOrden(): array {
        return OrdenProduccion::query()
            ->orderByDesc('id')
            ->get(['id', 'numero_op'])
            ->map(fn (OrdenProduccion $op) => [
                'id' => $op->id,
                'value' => $op->id,
                'numero_op' => $op->numero_op,
                'label' => $op->numero_op ?: ('OP #' . $op->id),
                'name' => $op->numero_op ?: ('OP #' . $op->id),
            ])
            ->values()
            ->all();
    }

    private function opcionesProveedor(): array {
        return Proveedor::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (Proveedor $proveedor) => [
                'id' => $proveedor->id,
                'value' => $proveedor->id,
                'nombre' => $proveedor->nombre,
                'label' => $proveedor->nombre,
                'name' => $proveedor->nombre,
            ])
            ->values()
            ->all();
    }

    private function estadosRequeridos(): array {
        return KitProceso::query()
            ->whereNotNull('estado_requerido')
            ->where('estado_requerido', '<>', '')
            ->orderBy('estado_requerido')
            ->pluck('estado_requerido')
            ->map(fn ($estado) => trim((string) $estado))
            ->filter()
            ->unique(fn ($estado) => mb_strtolower($estado))
            ->values()
            ->all();
    }
}
