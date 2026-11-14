<?php

namespace App\Http\Controllers;

use App\Models\Procesoop;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use App\Services\ProcesoopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcesoopController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'Procesoop';

    public function __construct(
        protected ProcesoopService $procesoopService,
    ) {
        $this->thisAtributos = (new Procesoop())->getFillable();
    }

    public function index(Request $request): Response {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' Procesoops '));
        // Catálogo de nombres: el coste y la asignación viven en el producto.
        $procesoops = Procesoop::withCount('productos')
            ->orderBy('nombre')
            ->get();

        // Vínculos proceso↔producto: cada fila es un proceso aplicado a un
        // producto con su coste (pivot.coste_proceso).
        $vinculos = Procesoop::with(['productos' => fn ($query) => $query->select('productos.id', 'Titulo_Shopify', 'Codigo_interno')])
            ->orderBy('nombre')
            ->get()
            ->flatMap(fn (Procesoop $procesoop) => $procesoop->productos->map(fn ($producto) => [
                'procesoop_id'  => $procesoop->id,
                'proceso'       => $procesoop->nombre,
                'producto_id'   => $producto->id,
                'producto'      => trim(($producto->Codigo_interno ? $producto->Codigo_interno . ' — ' : '') . $producto->Titulo_Shopify) ?: ('Producto #' . $producto->id),
                'coste_proceso' => (float) $producto->pivot->coste_proceso,
            ]))
            ->values();

        return Inertia::render($this->FromController . '/Index', [
            'procesoops'        => $procesoops,
            'vinculos'          => $vinculos,
            'total'             => $procesoops->count(),
            'breadcrumbs'       => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title'             => __('app.label.' . $this->FromController),
            'numberPermissions' => $numberPermissions,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:Procesoops');
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $Procesoop = $this->procesoopService->crear($datos);

        Myhelp::EscribirEnLog($this, 'STORE:Procesoops EXITOSO', 'Procesoop id:' . $Procesoop->id . ' | ' . $Procesoop->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $Procesoop->nombre]));
    }

    public function create() {}
    public function show($id) {}
    public function edit($id) {}

    public function update(Request $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:Procesoops');
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $Procesoop = Procesoop::findOrFail($id);
        $this->procesoopService->actualizar($Procesoop, $datos);

        Myhelp::EscribirEnLog($this, 'UPDATE:Procesoops EXITOSO', 'Procesoop id:' . $Procesoop->id . ' | ' . $Procesoop->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $Procesoop->nombre]));
    }

    public function destroy($Procesoopid): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:Procesoops');
        $Procesoop = Procesoop::find($Procesoopid);
        $elnombre = $Procesoop->nombre;
        $Procesoop->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:Procesoops', 'Procesoop id:' . $Procesoop->id . ' | ' . $Procesoop->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request): RedirectResponse {
        $Procesoop = Procesoop::whereIn('id', $request->id);
        $Procesoop->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
}
