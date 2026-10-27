<?php

namespace App\Http\Controllers;

use App\Models\FichaTecnica;
use App\Models\Material;
use App\Models\Producto;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use App\Services\CostoMaterialOpService;
use App\Services\FichaTecnicaService;
use App\Services\ListaDesplegableService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FichaTecnicaMaterialesController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'FichaTecnicaMateriales';

    public function __construct(
        protected FichaTecnicaService $fichaTecnicaService,
        protected ListaDesplegableService $listaDesplegableService,
        protected CostoMaterialOpService $costosMateriales
    ) {
        $this->thisAtributos = (new FichaTecnica())->getFillable();
    }

    /** Columnas de productos ordenables desde la tabla «sin ficha». */
    private const PRODUCTO_COLUMNAS_ORDENABLES = [
        'Codigo_interno', 'Titulo_Shopify', 'Categoria_original', 'Control_de_variante',
        'Estado', 'precio_preventa', 'precio_estimado', 'created_at', 'updated_at',
    ];

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' FichaTecnicaMaterialess '));
        $fichas = $this->Filtros($request)->get()->map(function ($ficha) {
            $ficha->productos_nombres = $ficha->productos
                ->map(fn ($p) => $p->Titulo_Shopify ?: ($p->Codigo_interno ?: 'Producto #' . $p->id))
                ->implode(', ');
            $ficha->materiales_procesados_nombres = $ficha->materialesProcesados
                ->map(fn ($m) => trim(($m->codigo_material ? $m->codigo_material . ' — ' : '') . $m->nombre_base))
                ->implode(', ');
            $ficha->materiales_count = $ficha->materiales->count();
            return $ficha;
        });

        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $fichas),
            'total' => $fichas->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order', 'psearch', 'pfield', 'porder', 'pperPage', 'ppage']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->losSelect(),
            'productosSinFicha' => $this->productosSinFicha($request),
            'selectProducto'    => $this->selectProducto(),
            'titulosProducto'   => Producto::getFillableWithTypes(),
        ]);
    }

    /**
     * Productos que aún no tienen ficha técnica, con lo que muestra la tabla de
     * Productos (cantidad por talla y sus acciones de fila).
     */
    private function productosSinFicha(Request $request): LengthAwarePaginator {
        $busqueda = trim((string) $request->input('psearch', ''));
        $campo = (string) $request->input('pfield', 'Codigo_interno');
        if (!in_array($campo, self::PRODUCTO_COLUMNAS_ORDENABLES, true)) {
            $campo = 'Codigo_interno';
        }
        $direccion = $request->input('porder') === 'desc' ? 'desc' : 'asc';
        $porPagina = max(1, (int) $request->input('pperPage', 10));

        $productos = Producto::query()
            ->whereDoesntHave('fichaTecnica')
            ->with('distribucionesPorTallas.ordenesProduccion')
            ->when($busqueda !== '', function ($consulta) use ($busqueda) {
                $consulta->where(function ($q) use ($busqueda) {
                    $q->where('Codigo_interno', 'LIKE', "%$busqueda%")
                        ->orWhere('Titulo_Shopify', 'LIKE', "%$busqueda%")
                        ->orWhere('Categoria_original', 'LIKE', "%$busqueda%");
                });
            })
            ->orderBy($campo, $direccion);

        $paginados = $productos->paginate($porPagina, ['*'], 'ppage')->withQueryString();
        $paginados->getCollection()->each(function ($producto) {
            $producto->cantidad = (int) $producto->distribucionesPorTallas->sum('cantidad_planificada');
            // OPs que ya usan estas tallas: el modal solo las muestra y avisa dónde
            // se cambian.
            $producto->ordenes_con_tallas = $producto->distribucionesPorTallas
                ->flatMap->ordenesProduccion
                ->unique('id')
                ->sortBy('numero_op')
                ->pluck('numero_op')
                ->filter()
                ->values()
                ->all();
            $producto->ficha_id = null;
            $producto->ficha_materiales = [];
            $producto->ficha_bloqueada = false;
            $producto->ficha_bloqueo_detalle = '';
        });

        return $paginados;
    }

    /** Selects que necesita el formulario de Producto (mismo origen que ProductoController). */
    private function selectProducto(): array {
        $listas = array_change_key_case($this->listaDesplegableService->getOptionsByCategoriaGrouped('producto'), CASE_LOWER);
        $opciones = $this->fichaTecnicaService->opcionesMateriales();

        return [
            'Categoria_original'  => $listas['categoria_original'] ?? [],
            'Familia_normalizada' => $listas['familia_normalizada'] ?? [],
            'Control_de_variante' => $listas['control_de_variante'] ?? [],
            'Opciones'            => $listas['opciones'] ?? [],
            'materiales'          => $opciones['materiales'],
            'unidad_medida'       => $opciones['unidad_medida'],
        ];
    }

    private function losSelect(): array {
        return $this->fichaTecnicaService->opcionesFicha();
    }

    public function Filtros($request): Builder {
        $fichas = FichaTecnica::query()->with(['materiales.material', 'productos', 'materialesProcesados']);
        if ($request->has('search')) {
            $fichas = $fichas->where(function ($query) use ($request) {
                $query->where('nombre', 'LIKE', "%" . $request->search . "%");
            });
        }

        if ($request->has(['field', 'order'])) {
            $fichas = $fichas->orderBy($request->field, $request->order);
        } else
            $fichas = $fichas->orderBy('updated_at', 'DESC');
        return $fichas;
    }

    public function PerPageAndPaginate($request, $fichas) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $fichas->forPage($page, $perPage),
            $fichas->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:FichaTecnicaMaterialess');
        DB::beginTransaction();
        try {
            $ficha = $this->guardarFicha($request);
            DB::commit();
            Cache::increment('material_index_version');
            Cache::increment('op_index_version');
            Cache::forget('op_selects');
            Myhelp::EscribirEnLog($this, 'STORE:FichaTecnicaMaterialess EXITOSO', 'FichaTecnica id:' . $ficha->id . ' | ' . $ficha->nombre, false);
            return back()->with('success', __('app.label.created_successfully', ['name' => $ficha->nombre]));
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function create() {
    }

    public function show($id) {
    }

    public function edit($id) {
    }

    public function update(Request $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:FichaTecnicaMaterialess');
        DB::beginTransaction();
        try {
            $ficha = FichaTecnica::findOrFail($id);
            $this->guardarFicha($request, $ficha);
            DB::commit();
            Cache::increment('material_index_version');
            Cache::increment('op_index_version');
            Cache::forget('op_selects');
            Myhelp::EscribirEnLog($this, 'UPDATE:FichaTecnicaMaterialess EXITOSO', 'FichaTecnica id:' . $ficha->id . ' | ' . $ficha->nombre, false);
            return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $ficha->nombre]));
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Crea o actualiza la ficha con sus materiales y productos.
     * Un producto solo puede pertenecer a una ficha (ficha única por producto).
     */
    private function guardarFicha(Request $request, ?FichaTecnica $ficha = null): FichaTecnica {
        $datos = $request->validate([
            'nombre'                          => ['required', 'string', 'max:255'],
            'productos'                       => ['present', 'array'],
            'productos.*'                     => ['integer', 'exists:productos,id'],
            'materiales_procesados'           => ['present', 'array'],
            'materiales_procesados.*'         => ['integer', 'exists:materials,id'],
            'materiales'                      => ['required', 'array', 'min:1'],
            'materiales.*.material_componente_id' => ['required', 'integer', 'exists:materials,id'],
            'materiales.*.consumo_estandar'   => ['required', 'numeric', 'min:0'],
            'materiales.*.unidad_medida'      => ['nullable', 'string', 'max:255'],
            'materiales.*.costo_asociado_teorico' => ['nullable', 'numeric', 'min:0'],
            'materiales.*.costo_asociado_real'    => ['nullable', 'numeric', 'min:0'],
        ]);

        if (empty($datos['productos']) && empty($datos['materiales_procesados'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'productos' => 'Seleccione un producto o un material procesado para esta ficha.',
            ]);
        }

        $noProcesados = Material::whereIn('id', $datos['materiales_procesados'])
            ->where('material_procesado', false)
            ->pluck('nombre_base')
            ->all();

        if (!empty($noProcesados)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'materiales_procesados' => 'Solo los materiales con proceso pueden tener ficha técnica: ' . implode(', ', $noProcesados),
            ]);
        }

        return $this->fichaTecnicaService->guardar($datos, $ficha);
    }

    /**
     * OPs activas que impiden modificar o borrar una ficha.
     *
     * @return array<string, string>
     */
    private function bloqueosDeFicha(FichaTecnica $ficha): array
    {
        $bloqueos = [];

        foreach ($ficha->productos()->pluck('productos.id') as $productoId) {
            $bloqueos += $this->fichaTecnicaService->operacionesQueBloquean((int) $productoId);
        }
        foreach ($ficha->materialesProcesados()->pluck('materials.id') as $materialId) {
            $bloqueos += $this->fichaTecnicaService->operacionesQueBloqueanMaterial((int) $materialId);
        }

        return $bloqueos;
    }

    public function destroy($FichaTecnicaMaterialesid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:FichaTecnicaMaterialess');
        $ficha = FichaTecnica::findOrFail($FichaTecnicaMaterialesid);

        $bloqueos = $this->bloqueosDeFicha($ficha);
        if (!empty($bloqueos)) {
            $detalle = collect($bloqueos)
                ->map(fn ($estado, $numeroOp) => $numeroOp . ' (' . $estado . ')')
                ->implode(', ');

            throw \Illuminate\Validation\ValidationException::withMessages([
                'ficha' => 'No se puede eliminar la ficha: está en la OP ' . $detalle
                    . '. Solo se permite en Borrador, Preparación o Cerrada.',
            ]);
        }

        $elnombre = $ficha->nombre;
        DB::beginTransaction();
        try {
            $productoIds = $ficha->productos()->pluck('productos.id')->map(fn ($id) => (int) $id)->all();
            $materialIds = $ficha->materialesProcesados()->pluck('materials.id')->map(fn ($id) => (int) $id)->all();

            $ficha->materiales()->delete();
            $ficha->productos()->detach();
            $ficha->materialesProcesados()->detach();
            $ficha->delete();

            // Las OPs abiertas que usaban la ficha dejan de costear esos objetivos.
            $this->costosMateriales->recalcularPorProductos($productoIds);
            $this->costosMateriales->recalcularPorMateriales($materialIds);

            DB::commit();
            Cache::increment('material_index_version');
            Cache::increment('op_index_version');
            Cache::forget('op_selects');
            Myhelp::EscribirEnLog($this, 'DELETE:FichaTecnicaMaterialess', 'FichaTecnica id:' . $ficha->id . ' | ' . $elnombre . ' borrado', false);
            return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroyBulk(Request $request) {
        $fichas = FichaTecnica::whereIn('id', $request->id)->get();
        DB::beginTransaction();
        foreach ($fichas as $ficha) {
            $ficha->materiales()->delete();
            $ficha->productos()->detach();
            $ficha->materialesProcesados()->detach();
            $ficha->delete();
        }
        DB::commit();
        Cache::increment('material_index_version');
        Cache::increment('op_index_version');
        Cache::forget('op_selects');
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }

}
