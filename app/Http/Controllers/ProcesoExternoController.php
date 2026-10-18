<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\ProcesoExterno;
use App\Models\Proveedor;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Traits\MaterialMovimientoTrait;

class ProcesoExternoController extends Controller {
    use MaterialMovimientoTrait;

    public array $thisAtributos;
    public string $FromController = 'ProcesoExterno';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct() {
//        $this->middleware('permission:create ProcesoExterno', ['only' => ['create', 'store']]);
//        $this->middleware('permission:read ProcesoExterno', ['only' => ['index', 'show']]);
//        $this->middleware('permission:update ProcesoExterno', ['only' => ['edit', 'update']]);
//        $this->middleware('permission:delete ProcesoExterno', ['only' => ['destroy', 'destroyBulk']]);
        $this->thisAtributos = (new ProcesoExterno())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' ProcesoExternos '));
        $ProcesoExternos = $this->Filtros($request)
            ->with(['ordenProduccion:id,numero_op', 'materialOrigen:id,nombre_base', 'proveedorDestino:id,nombre'])
            ->get()
            ->map(function ($pe) {
                $pe->orden_numero = $pe->ordenProduccion?->numero_op ? 'OP ' . $pe->ordenProduccion->numero_op : 'Sin OP';
                $pe->material_nombre = $pe->materialOrigen?->nombre_base ?? ($pe->material_origen_id ? '#' . $pe->material_origen_id : '');
                $pe->proveedor_nombre = $pe->proveedorDestino?->nombre ?? '';
                return $pe;
            });
//        $losSelect = $this->Dependencias();


        $perPage = $request->has('perPage') ? $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'fromController' => $this->PerPageAndPaginate($request, $ProcesoExternos),
            'total' => $ProcesoExternos->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'filters' => $request->all(['search', 'field', 'order']),
            'perPage' => (int)$perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->obtenerSelects(),
	        'titulos'           => ProcesoExterno::getFillableWithTypes(),

        ]);
    }

    public function obtenerSelects(): array {
        $ordenes = OrdenProduccion::orderByDesc('id')
            ->get(['id', 'numero_op'])
            ->map(function ($op) {
                $nombre = 'OP ' . $op->numero_op;
                return [
                    'id'    => $op->id,
                    'value' => $op->id,
                    'name'  => $nombre,
                    'label' => $nombre,
                    'title' => $nombre,
                ];
            });

        $materiales = Material::orderBy('nombre_base')
            ->get(['id', 'categoria', 'nombre_base'])
            ->map(function ($material) {
                $nombre_base = $material->nombre_base ?: ($material->categoria ?: 'Material #' . $material->id);
                return [
                    'id'    => $material->id,
                    'value' => $material->id,
                    'name'  => $nombre_base,
                    'label' => $nombre_base,
                    'title' => $nombre_base,
                ];
            });

        $proveedores = Proveedor::orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(function ($proveedor) {
                return [
                    'id'    => $proveedor->id,
                    'value' => $proveedor->id,
                    'name'  => $proveedor->nombre,
                    'label' => $proveedor->nombre,
                    'title' => $proveedor->nombre,
                ];
            });

        return [
            'orden_produccion_id'  => $ordenes,
            'material_origen_id'   => $materiales,
            'proveedor_destino_id' => $proveedores,
        ];
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
        $ProcesoExternos = ProcesoExterno::query();
        if ($request->has('search')) {
            $ProcesoExternos = $ProcesoExternos->where(function ($query) use ($request) {
                $query->where('etapa_componente', 'LIKE', "%" . $request->search . "%")
                    ->orWhere('servicio_proceso', 'LIKE', "%" . $request->search . "%")
                    ->orWhere('bodega_origen', 'LIKE', "%" . $request->search . "%")
                ;
            });
        }

        if ($request->has(['field', 'order'])) {
            $ProcesoExternos = $ProcesoExternos->orderBy($request->field, $request->order);
        } else
            $ProcesoExternos = $ProcesoExternos->orderBy('orden_produccion_id', 'ASC')->orderBy('id', 'DESC');
        return $ProcesoExternos;
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

    public function PerPageAndPaginate($request, $ProcesoExternos) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $ProcesoExternos->forPage($page, $perPage),
            $ProcesoExternos->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:ProcesoExternos');
        DB::beginTransaction();
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $ProcesoExterno = ProcesoExterno::create($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'STORE:ProcesoExternos EXITOSO', 'ProcesoExterno id:' . $ProcesoExterno->id . ' | ' . $ProcesoExterno->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $ProcesoExterno->nombre]));
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:ProcesoExternos');
        DB::beginTransaction();
        $ProcesoExterno = ProcesoExterno::findOrFail($id);
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $ProcesoExterno->update($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE:ProcesoExternos EXITOSO', 'ProcesoExterno id:' . $ProcesoExterno->id . ' | ' . $ProcesoExterno->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $ProcesoExterno->nombre]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($ProcesoExternoid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:ProcesoExternos');
        $ProcesoExterno = ProcesoExterno::find($ProcesoExternoid);
        $elnombre = $ProcesoExterno->nombre;
        $ProcesoExterno->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:ProcesoExternos', 'ProcesoExterno id:' . $ProcesoExterno->id . ' | ' . $ProcesoExterno->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $ProcesoExterno = ProcesoExterno::whereIn('id', $request->id);
        $ProcesoExterno->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

    /**
     * Materiales que una OP requiere según sus fichas técnicas (ficha × cantidad planificada),
     * con la existencia actual de cada uno para validar la salida al taller.
     */
    public function materialesOp($orden): JsonResponse {
        $op = OrdenProduccion::with([
            'productos.fichaTecnica.materiales.material',
            'taller:id,nombre',
        ])->findOrFail($orden);

        $cantidadTotal = (float) ($op->cantidad_planificada ?? 0);
        $requeridos = [];
        foreach ($op->productos as $producto) {
            foreach ($producto->fichaTecnica as $ficha) {
                foreach ($ficha->materiales as $fila) {
                    $material = $fila->material;
                    if (!$material) continue;
                    $necesita = (float) ($fila->consumo_estandar ?? 0) * $cantidadTotal;
                    if ($necesita <= 0) continue;
                    $row = $requeridos[$material->id] ?? [
                        'material_origen_id' => $material->id,
                        'material_nombre'    => $material->nombre_base ?: ('Material #' . $material->id),
                        'unidad_medida'      => $fila->unidad_medida ?: ($material->unidad_inventario ?? ''),
                        'cantidad_salida'    => 0.0,
                        'existencia_actual'  => (float) ($material->existencia_actual ?? 0),
                    ];
                    $row['cantidad_salida'] += $necesita;
                    $requeridos[$material->id] = $row;
                }
            }
        }

        $items = collect($requeridos)->map(function ($r) {
            $r['cantidad_salida'] = round($r['cantidad_salida'], 2);
            $r['faltante'] = round(max(0, $r['cantidad_salida'] - $r['existencia_actual']), 2);
            return $r;
        })->values();

        $tallerNombre = trim($op->taller?->nombre ?? '');
        $proveedorSugerido = $tallerNombre !== ''
            ? Proveedor::whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($tallerNombre)])->value('id')
            : null;

        return response()->json([
            'orden_produccion_id'  => $op->id,
            'numero_op'            => $op->numero_op,
            'taller_nombre'        => $tallerNombre,
            'cantidad_planificada' => $cantidadTotal,
            'proveedor_sugerido_id' => $proveedorSugerido,
            'proveedores'          => Proveedor::orderBy('nombre')->get(['id', 'nombre']),
            'items'                => $items,
        ]);
    }

    /**
     * Registra la salida de materiales de una OP hacia un taller/proveedor:
     * un ProcesoExterno por material, descuenta el inventario y deja MaterialMovimiento 'salida'.
     * Falla sin tocar nada si algún material no tiene stock suficiente.
     */
    public function salidaOp(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin SALIDA-OP:ProcesoExternos');
        $data = $request->validate([
            'orden_produccion_id'      => ['required', 'integer', 'exists:orden_produccions,id'],
            'proveedor_destino_id'     => ['required', 'integer', 'exists:proveedors,id'],
            'etapa_componente'         => ['nullable', 'string', 'max:255'],
            'bodega_origen'            => ['nullable', 'string', 'max:255'],
            'servicio_proceso'         => ['nullable', 'string', 'max:255'],
            'fecha_salida'             => ['required', 'date'],
            'fecha_prevista_regreso'   => ['nullable', 'date'],
            'resultado_observaciones'  => ['nullable', 'string'],
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.material_origen_id' => ['required', 'integer', 'exists:materials,id'],
            'items.*.cantidad_salida'  => ['required', 'numeric', 'gt:0'],
            'items.*.unidad_medida'    => ['nullable', 'string', 'max:255'],
        ]);

        $op = OrdenProduccion::findOrFail($data['orden_produccion_id']);
        $motivo = 'Salida de materiales a taller — OP ' . ($op->numero_op ?? $op->id);

        DB::beginTransaction();
        $sinStock = [];
        $creados = 0;
        try {
            foreach ($data['items'] as $fila) {
                $material = Material::whereKey((int) $fila['material_origen_id'])->lockForUpdate()->first();
                $cantidad = round((float) $fila['cantidad_salida'], 2);
                $existencia = (float) ($material->existencia_actual ?? 0);
                if ($existencia < $cantidad) {
                    $sinStock[] = ($material->nombre_base ?: 'Material #' . $material->id)
                        . ': se necesitan ' . $cantidad . ($material->unidad_inventario ? ' ' . $material->unidad_inventario : '')
                        . ', solo hay ' . $existencia;
                    continue;
                }

                $nueva = round($existencia - $cantidad, 2);
                $material->update(['existencia_actual' => $nueva]);

                ProcesoExterno::create([
                    'orden_produccion_id'     => $op->id,
                    'etapa_componente'        => $data['etapa_componente'] ?? null,
                    'material_origen_id'      => $material->id,
                    'cantidad_salida'         => $cantidad,
                    'unidad_medida'           => $fila['unidad_medida'] ?? null,
                    'bodega_origen'           => $data['bodega_origen'] ?? null,
                    'proveedor_destino_id'    => $data['proveedor_destino_id'],
                    'servicio_proceso'        => $data['servicio_proceso'] ?? null,
                    'fecha_salida'            => $data['fecha_salida'],
                    'fecha_prevista_regreso'  => $data['fecha_prevista_regreso'] ?? null,
                    'resultado_observaciones' => $data['resultado_observaciones'] ?? null,
                ]);
                $creados++;

                $this->registrarMovimientoExistencia($material, $existencia, $nueva, 'salida', $motivo);
            }

            if (!empty($sinStock)) {
                DB::rollBack();
                throw ValidationException::withMessages([
                    'items' => 'Inventario insuficiente, no se registró la salida — ' . implode(' | ', $sinStock),
                ]);
            }

            DB::commit();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        Cache::forget('op_selects');
        Myhelp::EscribirEnLog($this, 'SALIDA-OP:ProcesoExternos EXITOSO', 'OP ' . $op->numero_op . ' | ' . $creados . ' procesos creados', false);
        return back()->with('success', 'Salida registrada: ' . $creados . ' materiales enviados al taller.');
    }

}
