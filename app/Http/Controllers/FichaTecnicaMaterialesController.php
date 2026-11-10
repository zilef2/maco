<?php

namespace App\Http\Controllers;

use App\Models\FichaTecnica;
use App\Models\Material;
use App\Models\Producto;
use App\Models\Proveedor;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use App\Services\CostoMaterialOpService;
use App\Services\FichaTecnicaService;
use App\Services\ListaDesplegableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    /**
     * Las tres tablas de la página reciben el listado completo una sola vez:
     * buscar, ordenar y paginar se hace en el cliente.
     */
    public function index() {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' FichaTecnicaMaterialess '));

        return Inertia::render($this->FromController . '/Index', [
            'fichas' => $this->fichas(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'numberPermissions' => $numberPermissions,
            'losSelect'          => $this->losSelect(),
            'productosSinFicha'  => $this->productosSinFicha(),
            'selectProducto'     => $this->selectProducto(),
            'titulosProducto'    => Producto::getFillableWithTypes(),
            'materialesSinFicha' => $this->materialesSinFicha(),
            'selectMaterial'     => $this->selectMaterial(),
            'titulosMaterial'    => Material::getFillableWithTypes(),
        ]);
    }

    private function fichas(): array {
        return FichaTecnica::with(['materiales.material', 'productos', 'materialesProcesados'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($ficha) {
                $ficha->productos_nombres = $ficha->productos
                    ->map(fn ($p) => $p->Titulo_Shopify ?: ($p->Codigo_interno ?: 'Producto #' . $p->id))
                    ->implode(', ');
                $ficha->materiales_procesados_nombres = $ficha->materialesProcesados
                    ->map(fn ($m) => trim(($m->codigo_material ? $m->codigo_material . ' — ' : '') . $m->nombre_base))
                    ->implode(', ');
                $ficha->materiales_count = $ficha->materiales->count();
                $ficha->productos_count = $ficha->productos->count();
                $ficha->materiales_procesados_count = $ficha->materialesProcesados->count();
                return $ficha;
            })
            ->all();
    }

    /**
     * Productos que aún no tienen ficha técnica, con lo que muestra la tabla de
     * Productos (cantidad por talla y sus acciones de fila).
     */
    private function productosSinFicha(): array {
        return Producto::query()
            ->whereDoesntHave('fichaTecnica')
            ->with('distribucionesPorTallas.ordenesProduccion')
            ->orderBy('Codigo_interno')
            ->get()
            ->each(function ($producto) {
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
            })
            ->all();
    }

    /**
     * Materiales con proceso que aún no tienen ficha técnica: el mismo caso que
     * los productos, pero el objetivo es un material de la bodega.
     */
    private function materialesSinFicha(): array {
        $materiales = Material::query()
            ->where('material_procesado', true)
            ->whereDoesntHave('fichaTecnica')
            ->with(['lastPrice', 'ordenesProduccion.estadoOp:id,nombre'])
            ->orderBy('codigo_material')
            ->get();

        // Un material sin ficha también puede estar en una OP activa: en ese caso
        // su ficha no se puede crear todavía, y el modal debe decir por qué.
        $bloqueos = $this->fichaTecnicaService->bloqueosPorMaterial();
        return $materiales
            ->each(function ($material) use ($bloqueos) {
                $material->costo_actual = $material->costoUnitarioActual();
                $material->ordenes_con_material = collect($bloqueos[$material->id] ?? [])
                    ->keys()
                    ->sortBy(fn ($numeroOp) => (string) $numeroOp)
                    ->values()
                    ->all();
                $material->ficha_id = null;
                $material->ficha_nombre = null;
                $material->ficha_materiales = [];
                $material->ficha_bloqueada = !empty($bloqueos[$material->id] ?? []);
                $material->ficha_bloqueo_detalle = collect($bloqueos[$material->id] ?? [])
                    ->map(fn ($estado, $numeroOp) => $numeroOp . ' (' . $estado . ')')
                    ->implode(', ');
            })
            ->all();
    }

    /** Selects que necesita el formulario de Material (mismo origen que MaterialController). */
    private function selectMaterial(): array {
        $listas = array_change_key_case($this->listaDesplegableService->getOptionsByCategoriaGrouped('material'), CASE_LOWER);
        $opciones = $this->fichaTecnicaService->opcionesMateriales();
        $proveedores = Proveedor::all(['id', 'nombre'])
            ->map(fn ($proveedor) => [
                'id' => $proveedor->id,
                'value' => $proveedor->id,
                'nombre' => $proveedor->nombre,
                'label' => $proveedor->nombre,
                'name' => $proveedor->nombre,
                'title' => $proveedor->nombre,
            ])->values()->all();

        return [
            'unidad_inventario' => $listas['unidad_inventario'] ?? [],
            'categoria' => $listas['categoria'] ?? [],
            'modelo_diseno_acabado' => $listas['modelo_diseno_acabado'] ?? $listas['variante_atributo_sugerido'] ?? $listas['variante'] ?? [],
            'variante_atributo_sugerido' => $listas['modelo_diseno_acabado'] ?? $listas['variante_atributo_sugerido'] ?? $listas['variante'] ?? [],
            'proveedor_habitual' => $proveedores,
            'materiales' => $opciones['materiales'],
            'unidad_medida' => $opciones['unidad_medida'],
        ];
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
     * El nombre es automático y lo arma el servicio desde el objetivo.
     */
    private function guardarFicha(Request $request, ?FichaTecnica $ficha = null): FichaTecnica {
        $datos = $request->validate([
            'nombre'                          => ['nullable', 'string', 'max:255'],
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
