<?php

namespace App\Http\Controllers;

use App\Models\FichaTecnica;
use App\Models\FichaTecnicaMateriales;
use App\Models\Material;
use App\Models\Producto;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FichaTecnicaMaterialesController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'FichaTecnicaMateriales';

    public function __construct(
        protected \App\Services\ListaDesplegableService $listaDesplegableService
    ) {
        $this->thisAtributos = (new FichaTecnica())->getFillable();
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' FichaTecnicaMaterialess '));
        $fichas = $this->Filtros($request)->get()->map(function ($ficha) {
            $ficha->productos_nombres = $ficha->productos
                ->map(fn ($p) => $p->Titulo_Shopify ?: ($p->Codigo_interno ?: 'Producto #' . $p->id))
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
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->losSelect(),
        ]);
    }

    private function losSelect(): array {
        $materiales = Material::orderBy('nombre_base')->get(['id', 'codigo_material', 'nombre_base', 'unidad_inventario'])
            ->map(fn ($m) => [
                'id'    => $m->id,
                'value' => $m->id,
                'name'  => trim(($m->codigo_material ? $m->codigo_material . ' — ' : '') . $m->nombre_base) ?: ('Material #' . $m->id),
                'unidad_inventario' => $m->unidad_inventario,
            ])->values()->all();

        $productos = Producto::all(['id', 'Titulo_Shopify', 'Codigo_interno'])
            ->map(function ($p) {
                $nombre = $p->Titulo_Shopify ?: ($p->Codigo_interno ?: 'Producto #' . $p->id);
                return [
                    'id'    => $p->id,
                    'value' => $p->id,
                    'name'  => $nombre,
                    'label' => $nombre,
                    'title' => $nombre,
                ];
            })->values()->all();

        $productosConFicha = DB::table('ficha_tecnica_producto')->pluck('producto_id')->all();
        $productosSinFicha = array_values(array_filter(
            $productos,
            fn ($p) => !in_array($p['id'], $productosConFicha)
        ));

        $opcionesMaterial = $this->listaDesplegableService->getOptionsByCategoriaGrouped('material');
        $unidadesMedida = array_map(fn ($item) => [
            'id'    => $item['nombre'],
            'value' => $item['nombre'],
            'name'  => $item['nombre'],
            'label' => $item['nombre'],
        ], $opcionesMaterial['unidad_inventario'] ?? []);


        return [
            'materiales'         => $materiales,
            'productos'          => $productos,
            'productos_sin_ficha' => $productosSinFicha,
            'unidad_medida'      => $unidadesMedida,
        ];
    }

    public function Filtros($request): Builder {
        $fichas = FichaTecnica::query()->with(['materiales.material', 'productos']);
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
        $ficha = $this->guardarFicha($request);
        DB::commit();
        Cache::forget('op_selects');
        Myhelp::EscribirEnLog($this, 'STORE:FichaTecnicaMaterialess EXITOSO', 'FichaTecnica id:' . $ficha->id . ' | ' . $ficha->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $ficha->nombre]));
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
        $ficha = FichaTecnica::findOrFail($id);
        $this->guardarFicha($request, $ficha);
        DB::commit();
        Cache::forget('op_selects');
        Myhelp::EscribirEnLog($this, 'UPDATE:FichaTecnicaMaterialess EXITOSO', 'FichaTecnica id:' . $ficha->id . ' | ' . $ficha->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $ficha->nombre]));
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
            'materiales'                      => ['required', 'array', 'min:1'],
            'materiales.*.material_componente_id' => ['required', 'integer', 'exists:materials,id'],
            'materiales.*.consumo_estandar'   => ['required', 'numeric', 'min:0'],
            'materiales.*.unidad_medida'      => ['nullable', 'string', 'max:255'],
            'materiales.*.costo_asociado_teorico' => ['nullable', 'numeric', 'min:0'],
            'materiales.*.costo_asociado_real'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $productoIds = array_map('intval', $datos['productos'] ?? []);
        $enOtraFicha = FichaTecnica::productosConFicha($productoIds, $ficha?->id);
        if (!empty($enOtraFicha)) {
            $nombres = Producto::whereIn('id', $enOtraFicha)->get()
                ->map(fn ($p) => $p->Titulo_Shopify ?: ($p->Codigo_interno ?: 'Producto #' . $p->id))
                ->implode(', ');
            throw ValidationException::withMessages([
                'productos' => __('app.label.ficha_ya_asignada') . ': ' . $nombres,
            ]);
        }

        if (!$ficha) {
            $ficha = FichaTecnica::create(['nombre' => $datos['nombre']]);
        } else {
            $ficha->update(['nombre' => $datos['nombre']]);
        }

        $ficha->materiales()->delete();
        foreach ($datos['materiales'] as $fila) {
            $ficha->materiales()->create([
                'material_componente_id'   => (int) $fila['material_componente_id'],
                'consumo_estandar'         => $fila['consumo_estandar'],
                'unidad_medida'            => $fila['unidad_medida'] ?? null,
                'costo_asociado_teorico'   => $fila['costo_asociado_teorico'] ?? 0,
                'costo_asociado_real'      => $fila['costo_asociado_real'] ?? null,
            ]);
        }

        $ficha->productos()->sync($productoIds);
        return $ficha->refresh();
    }

    public function destroy($FichaTecnicaMaterialesid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:FichaTecnicaMaterialess');
        $ficha = FichaTecnica::findOrFail($FichaTecnicaMaterialesid);
        $elnombre = $ficha->nombre;
        DB::beginTransaction();
        $ficha->materiales()->delete();
        $ficha->productos()->detach();
        $ficha->delete();
        DB::commit();
        Cache::forget('op_selects');
        Myhelp::EscribirEnLog($this, 'DELETE:FichaTecnicaMaterialess', 'FichaTecnica id:' . $ficha->id . ' | ' . $elnombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $fichas = FichaTecnica::whereIn('id', $request->id)->get();
        DB::beginTransaction();
        foreach ($fichas as $ficha) {
            $ficha->materiales()->delete();
            $ficha->productos()->detach();
            $ficha->delete();
        }
        DB::commit();
        Cache::forget('op_selects');
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }

}
