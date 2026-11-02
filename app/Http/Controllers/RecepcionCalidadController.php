<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\Producto;
use App\Models\RecepcionCalidad;
use App\Services\CostoRecepcionOpService;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecepcionCalidadController extends Controller {
    public array $thisAtributos;
    public string $FromController = 'RecepcionCalidad';


    //<editor-fold desc="Construc | filtro and dependencia">
    public function __construct(protected CostoRecepcionOpService $costosRecepcion) {
//        $this->middleware('permission:create RecepcionCalidad', ['only' => ['create', 'store']]);
//        $this->middleware('permission:read RecepcionCalidad', ['only' => ['index', 'show']]);
//        $this->middleware('permission:update RecepcionCalidad', ['only' => ['edit', 'update']]);
//        $this->middleware('permission:delete RecepcionCalidad', ['only' => ['destroy', 'destroyBulk']]);
        $this->thisAtributos = (new RecepcionCalidad())->getFillable(); //not using
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' RecepcionCalidads '));

        // Listado completo: la agrupación por OP, los filtros y la paginación
        // se resuelven en el frontend, igual que en Productos.
        $RecepcionCalidads = RecepcionCalidad::query()
            ->with([
                'ordenProduccion.estadoOp',
                'productoReferencia.fichaTecnica.materiales',
                'materialReferencia.fichaTecnica.materiales',
            ])
            ->orderByDesc('fecha_recepcion')
            ->orderByDesc('id')
            ->get();

        $datosOrdenes = $this->ordenesConCostos(
            $RecepcionCalidads->pluck('orden_produccion_id')->filter()->unique()->values()->all()
        );

        $recepciones = $RecepcionCalidads
            ->map(fn (RecepcionCalidad $recepcion) => $this->filaRecepcion(
                $recepcion,
                $datosOrdenes['ordenes'],
                $datosOrdenes['costos']
            ))
            ->values();

        $perPage = $request->has('perPage') ? (int) $request->perPage : 10;
        return Inertia::render($this->FromController . '/Index', [
            'recepciones' => $recepciones,
            'ordenes' => $this->opcionesOrdenes(),
            'total' => $recepciones->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'perPage' => $perPage,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $this->Dependencias(),
	        'titulos'           => RecepcionCalidad::getFillableWithTypes(),

        ]);
    }

    /**
     * OPs con los datos y costos que necesita cada recepción.
     *
     * @param  array<int, int>  $ids
     * @return array{ordenes: Collection, costos: array<int, array{productos: array<int, float>, materiales: array<int, float>}>}
     */
    private function ordenesConCostos(array $ids): array {
        if (empty($ids)) {
            return ['ordenes' => new Collection(), 'costos' => []];
        }

        $ordenes = OrdenProduccion::query()
            ->with([
                'estadoOp',
                'productos.fichaTecnica.materiales',
                'productoReferencia.fichaTecnica.materiales',
                'materiales.fichaTecnica.materiales',
                'costosMateriales',
            ])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $costos = [];
        foreach ($ordenes as $id => $op) {
            $costos[$id] = [
                'productos'   => $this->costosRecepcion->unitarios($op),
                'materiales'  => $this->costosRecepcion->unitariosMateriales($op),
            ];
        }

        return ['ordenes' => $ordenes, 'costos' => $costos];
    }

    /**
     * Fila plana que consume la tabla agrupada por OP.
     *
     * @param  Collection  $ordenes
     * @param  array<int, array{productos: array<int, float>, materiales: array<int, float>}>  $costos
     */
    private function filaRecepcion(RecepcionCalidad $recepcion, Collection $ordenes, array $costos): array {
        $op = $ordenes->get($recepcion->orden_produccion_id);
        $producto = $recepcion->productoReferencia;
        $material = $recepcion->materialReferencia;

        $productoId = (int) ($recepcion->producto_referencia_id ?? 0);
        $materialId = (int) ($recepcion->material_referencia_id ?? 0);

        $unitario = 0.0;
        if ($productoId > 0) {
            $unitario = (float) ($costos[$recepcion->orden_produccion_id]['productos'][$productoId] ?? 0);
            if ($unitario <= 0 && $producto) {
                $unitario = $this->costoUnitarioDesdeFicha($producto);
            }
        } elseif ($materialId > 0) {
            $unitario = (float) ($costos[$recepcion->orden_produccion_id]['materiales'][$materialId] ?? 0);
            if ($unitario <= 0 && $material) {
                $unitario = $this->costoUnitarioDesdeFicha($material);
            }
        }

        return [
            'id'                      => $recepcion->id,
            'orden_produccion_id'     => $recepcion->orden_produccion_id,
            'orden_numero'            => $op?->numero_op ?? ('OP #' . $recepcion->orden_produccion_id),
            'orden_estado'            => $op?->estadoOp?->nombre,
            'orden_objetivo'          => $this->objetivoOp($op),
            'producto_referencia_id'  => $recepcion->producto_referencia_id,
            'producto_nombre'         => $this->nombreProducto($producto),
            'producto_codigo'         => $producto?->Codigo_interno,
            'material_referencia_id'  => $recepcion->material_referencia_id,
            'material_nombre'         => $this->nombreMaterial($material),
            'talla'                   => $recepcion->talla,
            'cantidad_esperada'       => $recepcion->cantidad_esperada,
            'cantidad_recibida'       => $recepcion->cantidad_recibida,
            'cantidad_aprobada'       => $recepcion->cantidad_aprobada,
            'cantidad_en_reparacion'  => $recepcion->cantidad_en_reparacion,
            'cantidad_no_recuperable' => $recepcion->cantidad_no_recuperable,
            'fecha_recepcion'         => $recepcion->fecha_recepcion
                ? substr((string) $recepcion->fecha_recepcion, 0, 10)
                : null,
            'observaciones'           => $recepcion->observaciones,
            'fecha_inicio_revision'   => $recepcion->fecha_inicio_revision
                ? substr((string) $recepcion->fecha_inicio_revision, 0, 10)
                : null,
            'fecha_fin_revision'      => $recepcion->fecha_fin_revision
                ? substr((string) $recepcion->fecha_fin_revision, 0, 10)
                : null,
            // Costo de materiales de una unidad (producto o material procesado).
            'costo_unitario'          => round($unitario, 2),
        ];
    }

    /**
     * Opciones del selector: todas las OPs que todavía no están cerradas.
     * Cada opción trae cuántas recepciones acumula, para que el filtro no
     * parezca roto cuando la OP todavía no tiene ninguna.
     */
    private function opcionesOrdenes(): array {
        return OrdenProduccion::query()
            ->with(['estadoOp', 'productos', 'materiales', 'productoReferencia'])
            ->withCount('recepcionesCalidad')
            ->whereDoesntHave('estadoOp', fn ($query) => $query->where('nombre', 'Cerrada'))
            ->get()
            ->sortByDesc(fn (OrdenProduccion $op) => (int) preg_replace('/\D+/', '', (string) $op->numero_op))
            ->map(function (OrdenProduccion $op) {
                $objetivo = $this->objetivoOp($op);
                $numero = $op->numero_op ?: ('OP #' . $op->id);

                return [
                    'value'       => $op->id,
                    'title'       => $objetivo ? $numero . ' — ' . $objetivo : $numero,
                    'estado'      => $op->estadoOp?->nombre,
                    'recepciones' => (int) ($op->recepciones_calidad_count ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function nombreProducto(?Producto $producto): ?string {
        if (!$producto) {
            return null;
        }

        return $producto->Titulo_Shopify ?: ($producto->Codigo_interno ?: 'Producto #' . $producto->id);
    }

    private function nombreMaterial(?Material $material): ?string {
        if (!$material) {
            return null;
        }

        $nombre = trim(
            ($material->codigo_material ? $material->codigo_material . ' — ' : '')
            . ($material->nombre_base ?? '')
        );

        return $nombre !== '' ? $nombre : ('Material #' . $material->id);
    }

    private function objetivoOp(?OrdenProduccion $op): ?string {
        if (!$op) {
            return null;
        }

        $productos = $op->productos
            ->map(fn (Producto $producto) => $this->nombreProducto($producto))
            ->filter()
            ->implode(', ');

        if ($productos !== '') {
            return $productos;
        }

        $materiales = $op->materiales
            ->map(fn (Material $material) => $this->nombreMaterial($material))
            ->filter()
            ->implode(', ');

        if ($materiales !== '') {
            return $materiales;
        }

        return $this->nombreProducto($op->productoReferencia);
    }

    /**
     * Respaldo cuando la OP no tiene guardado el costo del producto o material:
     * usa el costo teórico de su ficha técnica.
     */
    private function costoUnitarioDesdeFicha(?object $entidad): float {
        $ficha = $entidad?->fichaTecnica?->first();
        if (!$ficha) {
            return 0.0;
        }

        $costo = 0.0;
        foreach ($ficha->materiales as $linea) {
            $costo += (float) $linea->consumo_estandar * (float) $linea->costo_asociado_teorico;
        }

        return round($costo, 2);
    }

	public function Dependencias(): array {
		$ordenesProduccion = OrdenProduccion::orderByDesc('updated_at')
			->get(['id', 'numero_op'])
			->map(fn ($op) => [
				'title' => 'OP ' . $op->numero_op,
				'value' => $op->id,
			])->values()->all();

		$productos = Producto::get(['id', 'Titulo_Shopify', 'Codigo_interno'])
			->map(fn ($producto) => [
				'title' => $producto->Titulo_Shopify ?: ($producto->Codigo_interno ?: 'Producto #' . $producto->id),
				'value' => $producto->id,
			])->values()->all();

		return [
			'orden_produccion_id'    => $ordenesProduccion,
			'producto_referencia_id' => $productos,
		];
	}

	public function losSelect(string $modelClass, string $label, string $displayField = 'nombre'): array {		// Verifica que la clase del modelo exista
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

    //</editor-fold>

    public function store(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:RecepcionCalidads');
        DB::beginTransaction();
//        $no_nada = $request->no_nada['id'];
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $RecepcionCalidad = RecepcionCalidad::create($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'STORE:RecepcionCalidads EXITOSO', 'RecepcionCalidad id:' . $RecepcionCalidad->id . ' | ' . $RecepcionCalidad->nombre, false);
        return back()->with('success', __('app.label.created_successfully', ['name' => $RecepcionCalidad->nombre]));
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:RecepcionCalidads');
        DB::beginTransaction();
        $RecepcionCalidad = RecepcionCalidad::findOrFail($id);
//        $request->merge(['no_nada_id' => $request->no_nada['id']]);
        $RecepcionCalidad->update($request->all());

        DB::commit();
        Myhelp::EscribirEnLog($this, 'UPDATE:RecepcionCalidads EXITOSO', 'RecepcionCalidad id:' . $RecepcionCalidad->id . ' | ' . $RecepcionCalidad->nombre, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $RecepcionCalidad->nombre]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($RecepcionCalidadid) {
        $permissions = Myhelp::EscribirEnLog($this, 'DELETE:RecepcionCalidads');
        $RecepcionCalidad = RecepcionCalidad::find($RecepcionCalidadid);
        $elnombre = $RecepcionCalidad->nombre;
        $RecepcionCalidad->delete();
        Myhelp::EscribirEnLog($this, 'DELETE:RecepcionCalidads', 'RecepcionCalidad id:' . $RecepcionCalidad->id . ' | ' . $RecepcionCalidad->nombre . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $elnombre]));
    }

    public function destroyBulk(Request $request) {
        $RecepcionCalidad = RecepcionCalidad::whereIn('id', $request->id);
        $RecepcionCalidad->delete();
        return back()->with('success', __('app.label.deleted_successfully', ['name' => count($request->id) . ' ' . __('app.label.user')]));
    }
    //FIN : STORE - UPDATE - DELETE

}
