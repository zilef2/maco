<?php

namespace App\Http\Controllers;

use App\Models\FichaTecnica;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use App\Services\FichaTecnicaService;
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
        protected FichaTecnicaService $fichaTecnicaService
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
        return $this->fichaTecnicaService->opcionesFicha();
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

        return $this->fichaTecnicaService->guardar($datos, $ficha);
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
