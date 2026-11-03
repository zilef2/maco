<?php

namespace App\Http\Controllers;

use App\Models\Procesoop;
use App\Models\Producto;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProcesoopController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'Procesoop';

    public function __construct() {
        $this->thisAtributos = (new Procesoop())->getFillable();
    }

    public function index(Request $request): Response {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' Procesoops '));
        $procesoops = Procesoop::with('producto')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($p) {
                $p->producto_nombre = $p->producto?->Titulo_Shopify ?? '';
                $p->producto_codigo = $p->producto?->Codigo_interno ?? '';
                return $p;
            });

        return Inertia::render($this->FromController . '/Index', [
            'procesoops'        => $procesoops,
            'total'             => $procesoops->count(),
            'breadcrumbs'       => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title'             => __('app.label.' . $this->FromController),
            'numberPermissions' => $numberPermissions,
            'productosSelect'   => Producto::orderBy('Titulo_Shopify')
                ->get(['id', 'Titulo_Shopify', 'Codigo_interno'])
                ->map(fn($p) => [
                    'value' => $p->id,
                    'label' => $p->Titulo_Shopify . ($p->Codigo_interno ? ' (' . $p->Codigo_interno . ')' : ''),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:Procesoops');
        DB::beginTransaction();
        $Procesoop = Procesoop::create($request->all());
        DB::commit();
        Myhelp::EscribirEnLog($this, 'STORE:Procesoops EXITOSO', 'Procesoop id:' . $Procesoop->id . ' | ' . $Procesoop->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $Procesoop->nombre]));
    }

    public function create() {}
    public function show($id) {}
    public function edit($id) {}

    public function update(Request $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:Procesoops');
        DB::beginTransaction();
        $Procesoop = Procesoop::findOrFail($id);
        $Procesoop->update($request->all());
        DB::commit();
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
