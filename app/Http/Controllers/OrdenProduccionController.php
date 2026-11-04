<?php

namespace App\Http\Controllers;

use App\Models\Distribucion_por_tallas;
use App\Models\CierreOrdenProduccion;
use App\Models\CompromisoReparacion;
use App\Models\GrupoTalla;
use App\Models\KitProceso;
use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\Parametro;
use App\Models\User;
use App\Models\EstadoOperacion;
use App\Models\EstadoOpMaterial;
use App\Models\Producto;
use App\Models\ProductoMovimiento;
use App\Models\EmpresaExterna;
use App\Models\ProcesoExterno;
use App\Models\Proveedor;
use App\Models\RecepcionCalidad;
use App\Models\Taller;
use App\Http\Requests\StoreOrdenProduccionRequest;
use App\Services\Bitacora;
use App\Services\CostoMaterialOpService;
use App\Services\CostoRecepcionOpService;
use App\Services\MermaRecepcionService;
use App\Traits\MaterialMovimientoTrait;
use App\helpers\Myhelp;
use App\helpers\MyModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrdenProduccionController extends Controller {
    use MaterialMovimientoTrait;

    public array $thisAtributos;
    public string $FromController = 'OrdenProduccion';

    public function __construct(
        protected CostoMaterialOpService $costosMateriales,
        protected CostoRecepcionOpService $costosRecepcion,
        protected MermaRecepcionService $mermasRecepcion
    ) {
        $this->thisAtributos = (new OrdenProduccion())->getFillable();
    }

    public function index(Request $request) {
        $numberPermissions = MyModels::getPermissionToNumber(Myhelp::EscribirEnLog($this, ' OrdenProduccions '));

        // Listado completo: la tabla filtra, ordena y pagina en el frontend.
        // La clave solo depende de la versión, que se incrementa al escribir.
        $claveListado = 'op_index_v11_' . $this->versionCache();
        $OrdenProduccions = Cache::remember($claveListado, now()->addMinutes(10), function () {
            return OrdenProduccion::query()
                ->with(['productoReferencia.fichaTecnica.materiales', 'taller', 'empresaExterna', 'estadoOp', 'responsable', 'distribucionesPorTallas.producto', 'distribucionesPorTallas.material', 'productos.fichaTecnica.materiales', 'productos.procesos', 'materiales.fichaTecnica.materiales', 'recepcionesCalidad.materialReferencia', 'compromisosReparacion.materialReferencia', 'kitProcesos.material', 'procesosExternos.materialOrigen', 'procesosExternos.proveedorDestino', 'cierres', 'costosMateriales.material', 'costosCambios.material', 'costosCambios.usuario'])
                ->orderByDesc('updated_at')
                ->get()
                ->map(function ($op) {
                    $producto = $op->productoReferencia;
                    $nombreProducto = fn ($p) => $p
                        ? ($p->Titulo_Shopify ?: ($p->Codigo_interno ?: 'Producto #' . $p->id))
                        : null;
                    $nombreMaterial = fn ($m) => $m
                        ? (trim(($m->codigo_material ? $m->codigo_material . ' — ' : '') . $m->nombre_base)
                            ?: ('Material #' . $m->id))
                        : null;

                    $op->productos_nombres = $op->productos
                        ->map(fn ($p) => $nombreProducto($p))
                        ->implode(', ');
                    $op->materiales_nombres = $op->materiales
                        ->map(fn ($m) => $nombreMaterial($m))
                        ->implode(', ');
                    $op->producto_nombre = $nombreProducto($producto)
                        ?? ($op->producto_referencia_id ? '#' . $op->producto_referencia_id : '')
                        ?: $op->materiales_nombres;
                    $op->es_op_material = $op->materiales->isNotEmpty();
                    $op->objetivo_nombre = $op->materiales_nombres ?: $op->productos_nombres ?: $op->producto_nombre;
                    $op->materiales_objetivo = $op->materiales->map(fn ($m) => [
                        'id' => (int) $m->id,
                        'nombre' => $nombreMaterial($m),
                        'cantidad' => (int) ($m->pivot->cantidad ?? 0),
                    ])->values()->all();

                    // Nombres por producto para las tablas de detalle (tallas y recepción).
                    $nombresProductos = $op->productos->mapWithKeys(
                        fn ($p) => [$p->id => $nombreProducto($p)]
                    );
                    $nombresMaterialesObjetivo = $op->materiales->mapWithKeys(
                        fn ($m) => [$m->id => $nombreMaterial($m)]
                    );

                    // Relación material -> productos que lo usan según la ficha
                    // técnica de cada producto. Permite mostrar a qué producto
                    // pertenece cada material faltante.
                    $productosPorMaterial = [];
                    foreach ($op->productos as $productoKit) {
                        $ficha = $productoKit->fichaTecnica->first();
                        if (!$ficha) {
                            continue;
                        }

                        $nombreKit = $nombreProducto($productoKit) ?: ('Producto #' . $productoKit->id);
                        foreach ($ficha->materiales as $filaMaterial) {
                            $materialId = $filaMaterial->material_componente_id;
                            if (!$materialId) {
                                continue;
                            }

                            $productosPorMaterial[$materialId][$productoKit->id] = $nombreKit;
                        }
                    }

                    foreach ($op->materiales as $materialKit) {
                        $ficha = $materialKit->fichaTecnica->first();
                        if (!$ficha) {
                            continue;
                        }

                        $nombreKit = $nombreMaterial($materialKit) ?: ('Material #' . $materialKit->id);
                        foreach ($ficha->materiales as $filaMaterial) {
                            $materialId = $filaMaterial->material_componente_id;
                            if (!$materialId) {
                                continue;
                            }

                            $productosPorMaterial[$materialId][$materialKit->id] = $nombreKit;
                        }
                    }

                    // Detalle por talla con su producto o material, para la
                    // recepción de calidad.
                    $op->distribuciones_detalle = $op->distribucionesPorTallas->map(function ($d) use ($nombreProducto, $nombresProductos, $nombreMaterial, $nombresMaterialesObjetivo) {
                        return [
                            'id'                   => $d->id,
                            'talla'                => $d->talla,
                            'cantidad_planificada' => $d->cantidad_planificada,
                            'producto_id'          => $d->producto_id,
                            'producto_nombre'      => $nombreProducto($d->producto)
                                ?? $nombresProductos[$d->producto_id]
                                ?? null,
                            'material_id'          => $d->material_id,
                            'material_nombre'      => $nombreMaterial($d->material)
                                ?? $nombresMaterialesObjetivo[$d->material_id]
                                ?? null,
                        ];
                    })->values()->all();

                    // Materiales del kit, con la necesidad total y la existencia
                    // actual. Se usan en el detalle al hacer clic en el estado.
                    $op->materiales_detalle = $op->kitProcesos->map(function ($kit) use ($productosPorMaterial) {
                        $requerida = (float) ($kit->cantidad_requerida ?? 0);
                        $disponible = (float) ($kit->material?->existencia_actual ?? 0);
                        $nombre = trim(
                            ($kit->material?->codigo_material ? $kit->material->codigo_material . ' — ' : '')
                            . ($kit->material?->nombre_base ?? '')
                        );

                        return [
                            'material_id' => $kit->material_id,
                            'nombre'      => $nombre !== '' ? $nombre : ('Material #' . $kit->material_id),
                            'unidad'      => $kit->unidad ?: ($kit->material?->unidad_inventario ?: 'u'),
                            'requerida'   => round($requerida, 4),
                            'disponible'  => round($disponible, 4),
                            'proveedor_id' => $kit->proveedor_id,
                            'productos'   => array_values($productosPorMaterial[$kit->material_id] ?? []),
                        ];
                    })->values()->all();

                    $op->materiales_faltantes = collect($op->materiales_detalle)
                        ->map(fn ($fila) => $fila + [
                            'faltante' => round(max(0, (float) $fila['requerida'] - (float) $fila['disponible']), 4),
                        ])
                        ->filter(fn ($fila) => $fila['faltante'] > 0)
                        ->values()->all();

                    // Costo de los materiales de la OP. El precio unitario de
                    // cada material queda guardado en la OP según el parámetro
                    // costos_promedio; si la OP aún no tiene su precio guardado
                    // se calcula en vivo con ese mismo método.
                    $op->costo_fichas = $op->costoFichasTecnicas();
                    $op->costo_proceso = round((float) ($op->costo_proceso ?? 0), 2);
                    $op->costo_total = round($op->costo_fichas + $op->costo_proceso, 2);

                    $nombresMateriales = $op->materiales_detalle;
                    $nombrePorMaterial = collect($nombresMateriales)
                        ->mapWithKeys(fn ($fila) => [$fila['material_id'] => $fila['nombre']]);

                    // Precio guardado por material, para el detalle de la OP.
                    $op->costos_detalle = $op->costosMateriales
                        ->sortBy('material_id')
                        ->map(function ($fila) use ($nombrePorMaterial) {
                            return [
                                'material_id' => $fila->material_id,
                                'nombre' => $nombrePorMaterial[$fila->material_id]
                                    ?? trim(($fila->material?->codigo_material ? $fila->material->codigo_material . ' — ' : '') . $fila->material?->nombre_base)
                                    ?: ('Material #' . $fila->material_id),
                                'metodo_costo' => (int) $fila->metodo_costo,
                                'precio_unitario' => (float) $fila->precio_unitario,
                                'cantidad' => (float) $fila->cantidad,
                                'costo_total' => (float) $fila->costo_total,
                            ];
                        })->values()->all();

                    // Historial de los precios que cambió el material en esta OP.
                    $op->costos_cambios_detalle = $op->costosCambios
                        ->sortByDesc('id')
                        ->map(function ($cambio) use ($nombrePorMaterial) {
                            return [
                                'id' => $cambio->id,
                                'material_id' => $cambio->material_id,
                                'nombre' => $nombrePorMaterial[$cambio->material_id]
                                    ?? trim(($cambio->material?->codigo_material ? $cambio->material->codigo_material . ' — ' : '') . $cambio->material?->nombre_base)
                                    ?: ('Material #' . $cambio->material_id),
                                'metodo_costo' => (int) $cambio->metodo_costo,
                                'precio_anterior' => $cambio->precio_anterior !== null ? (float) $cambio->precio_anterior : null,
                                'precio_nuevo' => (float) $cambio->precio_nuevo,
                                'usuario' => $cambio->usuario?->name,
                                'fecha' => $cambio->created_at ? substr((string) $cambio->created_at, 0, 16) : null,
                            ];
                        })->values()->all();

                    // Costo de materiales de una unidad de cada producto de la OP,
                    // con los precios guardados. Lo usa el formulario de recepción
                    // para costear aprobados y no recuperables.
                    $op->costo_unitario_productos = $this->costosRecepcion->unitarios($op);
                    $op->costo_unitario_materiales = $this->costosRecepcion->unitariosMateriales($op);

                    // Recepciones ya registradas, para precargar el formulario
                    // de calidad y para el detalle de solo lectura del estado.
                    $op->recepciones_detalle = $op->recepcionesCalidad->map(fn ($r) => [
                        'id'                      => $r->id,
                        'producto_id'             => $r->producto_referencia_id,
                        'producto_nombre'         => $nombresProductos[$r->producto_referencia_id]
                            ?? ((int) $r->producto_referencia_id === (int) $producto?->id ? $nombreProducto($producto) : null),
                        'material_id'             => $r->material_referencia_id,
                        'material_nombre'         => $nombreMaterial($r->materialReferencia)
                            ?? $nombresMaterialesObjetivo[$r->material_referencia_id]
                            ?? null,
                        'talla'                   => $r->talla,
                        'cantidad_esperada'       => $r->cantidad_esperada,
                        'cantidad_recibida'       => $r->cantidad_recibida,
                        'cantidad_aprobada'       => $r->cantidad_aprobada,
                        'cantidad_en_reparacion'  => $r->cantidad_en_reparacion,
                        'cantidad_no_recuperable' => $r->cantidad_no_recuperable,
                        'fecha_recepcion'         => $r->fecha_recepcion
                            ? substr((string) $r->fecha_recepcion, 0, 10)
                            : null,
                    ])->values()->all();

                    // Compromisos de reparación sin resolver: precargan la
                    // fecha de compromiso pendiente en el formulario de recepción.
                    $op->compromisos_detalle = $op->compromisosReparacion
                        ->reject(fn ($c) => $c->atendido)
                        ->map(fn ($c) => [
                            'id'                     => $c->id,
                            'producto_id'            => $c->producto_referencia_id,
                            'producto_nombre'        => $nombresProductos[$c->producto_referencia_id]
                                ?? ((int) $c->producto_referencia_id === (int) $producto?->id ? $nombreProducto($producto) : null),
                            'material_id'            => $c->material_referencia_id,
                            'material_nombre'        => $nombreMaterial($c->materialReferencia)
                                ?? $nombresMaterialesObjetivo[$c->material_referencia_id]
                                ?? null,
                            'talla'                  => $c->talla,
                            'cantidad_en_reparacion' => $c->cantidad_en_reparacion,
                            'fecha_compromiso'       => $c->fecha_compromiso?->toDateString(),
                        ])->values()->all();

                    // Procesos externos que todavía no han regresado: se usan
                    // al pasar la OP a "Lista para taller".
                    $op->procesos_externos_pendientes = $op->procesosExternos
                        ->filter(fn ($pe) => $pe->fecha_regreso_real === null)
                        ->map(fn ($pe) => [
                            'id'                      => $pe->id,
                            'material_nombre'         => $pe->materialOrigen?->nombre_base
                                ?: ('Material #' . $pe->material_origen_id),
                            'cantidad_salida'         => (float) ($pe->cantidad_salida ?? 0),
                            'unidad_medida'           => $pe->unidad_medida,
                            'proveedor_nombre'        => $pe->proveedorDestino?->nombre,
                            'fecha_salida'            => $pe->fecha_salida?->toDateTimeString(),
                            'fecha_prevista_regreso'  => $pe->fecha_prevista_regreso?->toDateString(),
                        ])->values()->all();

                    // Último cierre registrado, para mostrarlo en el detalle de
                    // la OP y en el resumen de cierre.
                    $cierre = $op->cierres->sortByDesc('id')->first();
                    $op->cierre_detalle = $cierre ? [
                        'id'                  => $cierre->id,
                        'perdidas_mermas'     => $cierre->perdidas_mermas !== null ? (float) $cierre->perdidas_mermas : null,
                        'costo_real_final'    => $cierre->costo_real_final !== null ? (float) $cierre->costo_real_final : null,
                        'completado'          => (bool) $cierre->completado,
                    ] : null;

                    $op->taller_nombre = $op->taller?->nombre ?? ($op->taller_id ? '#' . $op->taller_id : '');
                    $op->empresa_externa_nombre = $op->empresaExterna?->nombre ?? ($op->empresa_externa_id ? '#' . $op->empresa_externa_id : '');
                    $op->estado_nombre = $op->estadoOp?->nombre ?? ($op->estado_op_id ? '#' . $op->estado_op_id : '');
                    $op->estado_secundario_nombre = $op->estadoOpSecundario?->nombre ?? null;
                    $op->responsable_nombre = $op->responsable?->name ?? ($op->user_id ? '#' . $op->user_id : '');
                    return $op;
                })
                ->values();
        });

        // Selects para los formularios (productos, talleres, estados, usuarios, grupos, siguiente OP)
        $losSelect = $this->obtenerSelects();

        // Último borrador pendiente (guardado con "Terminar después") para el botón "Continuar última OP"
        $borradorId = OrdenProduccion::where('estado_op_id', EstadoOperacion::where('nombre', 'Borrador')->value('id'))
            ->latest('updated_at')->value('id');
        $borradorUltimo = $borradorId
            ? OrdenProduccion::with(['productos:id,Titulo_Shopify,Codigo_interno', 'materiales:id,codigo_material,nombre_base', 'distribucionesPorTallas'])->find($borradorId)
            : null;

        return Inertia::render($this->FromController . '/Index', [
            'ordenes' => $OrdenProduccions,
            'total' => $OrdenProduccions->count(),
            'breadcrumbs' => [['label' => __('app.label.' . $this->FromController), 'href' => route($this->FromController . '.index')]],
            'title' => __('app.label.' . $this->FromController),
            'perPage' => 10,
            'numberPermissions' => $numberPermissions,
            'losSelect'         => $losSelect,
	        'titulos'           => OrdenProduccion::getFillableWithTypes(),
            'borradorUltimo'    => $borradorUltimo,
            // Si es verdadero, el cambio de estado a «En taller» muestra
            // cuánto se resta de inventario por material.
            'mostrarRestaMateriales' => Parametro::bool(Parametro::MOSTRAR_RESTA_MATERIALES_TALLER, true),
            // Cómo se tomó el precio de los materiales guardado en las OPs.
            'metodoCostos' => $this->costosMateriales->metodo(),
            'metodoCostosLabel' => $this->costosMateriales->labelMetodo(),

        ]);
    }

    /**
     * Versión del cache del listado: al incrementarse, todas las claves
     * anteriores dejan de usarse y se reconstruyen con datos frescos.
     */
    private function versionCache(): int {
        Cache::add('op_index_version', 1, now()->addDays(7));
        return (int) Cache::get('op_index_version', 1);
    }

    /**
     * Invalida el listado y los selects tras una escritura (create/update/delete).
     * Los cambios de estado descuentan inventario, así que el listado de
     * materiales también debe refrescarse.
     */
    private function invalidarCache(): void {
        Cache::increment('op_index_version');
        Cache::increment('material_index_version');
        Cache::forget('op_selects');
    }

    /**
     * El campo `externa` (boolean) se mantiene sincronizado con `tipo`:
     * 'externa' => true y taller_id libre; 'taller' => false y sin empresa.
     * Si el request no envía tipo, los datos se dejan intactos.
     */
    private function normalizarTipo(array $data): array {
        if (!array_key_exists('tipo', $data) || !in_array($data['tipo'], ['taller', 'externa'], true)) {
            return $data;
        }

        $data['externa'] = $data['tipo'] === 'externa';
        if ($data['tipo'] === 'externa') {
            $data['taller_id'] = null;
            $data['empresa_externa_id'] = is_numeric($data['empresa_externa_id'] ?? null) ? (int) $data['empresa_externa_id'] : null;
        } else {
            $data['empresa_externa_id'] = null;
        }

        return $data;
    }

    public function obtenerSelects(): array {
        $usuarios = User::where('name', '!=', 'Superadmin')
            ->where('name', '!=', 'superadmin')
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'superadmin');
            })
            ->get(['id', 'name'])
            ->map(function ($user) {
                return [
                    'id'    => $user->id,
                    'value' => $user->id,
                    'name'  => $user->name,
                    'label' => $user->name,
                    'title' => $user->name,
                ];
            });

        $estadoOperaciones = EstadoOperacion::all(['id', 'nombre', 'puede_pasar_a', 'regla_clave'])
            ->map(function ($estado) {
                return [
                    'id'    => $estado->id,
                    'value' => $estado->id,
                    'name'  => $estado->nombre,
                    'label' => $estado->nombre,
                    'title' => $estado->nombre,
                    'permitidos' => $estado->idsPuedePasarA(),
                    'regla_clave' => $estado->regla_clave,
                ];
            });

        // Precio que tendrá cada material en la OP según el parámetro
        // costos_promedio, para que la vista previa coincida con lo guardado.
        $metodoCostos = $this->costosMateriales->metodo();
        $preciosPorMaterial = Material::query()
            ->with('lastPrice')
            ->withAvg('prices', 'precio')
            ->get()
            ->mapWithKeys(fn ($material) => [
                $material->id => $this->costosMateriales->precioUnitario($material, $metodoCostos),
            ]);

        $productos = Producto::with(['fichaTecnica.materiales.material', 'distribucionesPorTallas', 'procesos'])->get(['id', 'Titulo_Shopify', 'Codigo_interno', 'Opciones'])
            ->map(function ($producto) use ($preciosPorMaterial, $metodoCostos) {
                $nombre = $producto->Titulo_Shopify ?: ($producto->Codigo_interno ?: 'Producto #' . $producto->id);
                $ficha = $producto->fichaTecnica->first();
                return [
                    'id'    => $producto->id,
                    'value' => $producto->id,
                    'name'  => $nombre,
                    'label' => $nombre,
                    'title' => $nombre,
                    'Opciones' => $producto->Opciones,
                    'distribuciones_por_tallas' => $producto->distribucionesPorTallas->map(fn ($d) => [
                        'id' => $d->id,
                        'grupo' => $d->grupo,
                        'talla' => $d->talla,
                        'cantidad_planificada' => $d->cantidad_planificada,
                    ])->values()->all(),
                    'ficha' => $ficha ? ['nombre' => $ficha->nombre] : null,
                    // Procesos del producto: el costo de proceso de la OP se
                    // deriva de su suma por cada unidad planificada.
                    'procesos' => $producto->procesos
                        ->sortBy('id')
                        ->map(fn ($p) => [
                            'nombre'        => $p->nombre,
                            'coste_proceso' => (float) $p->coste_proceso,
                        ])->values()->all(),
                    'costo_proceso_total' => round((float) $producto->procesos->sum('coste_proceso'), 2),
                    'ficha_materiales' => $ficha?->materiales->map(fn ($fila) => [
                        'material_id'       => $fila->material_componente_id,
                        'nombre'            => trim(($fila->material?->codigo_material ? $fila->material->codigo_material . ' — ' : '') . $fila->material?->nombre_base),
                        'consumo_estandar'  => (float) $fila->consumo_estandar,
                        'unidad_medida'     => $fila->unidad_medida,
                        'costo_asociado_teorico' => (float) $fila->costo_asociado_teorico,
                        // Precio usado por el parámetro costos_promedio.
                        'precio_unitario'   => (float) ($preciosPorMaterial[$fila->material_componente_id] ?? 0),
                        'metodo_costos'     => $metodoCostos,
                        'existencia_actual' => (float) ($fila->material?->existencia_actual ?? 0),
                    ])->values()->all() ?? [],
                ];
            });

        $materialesProcesados = Material::with(['fichaTecnica.materiales.material', 'lastPrice'])
            ->where('material_procesado', true)
            ->orderBy('nombre_base')
            ->get(['id', 'codigo_material', 'nombre_base', 'unidad_inventario'])
            ->map(function ($material) use ($preciosPorMaterial, $metodoCostos) {
                $nombre = trim(($material->codigo_material ? $material->codigo_material . ' — ' : '') . $material->nombre_base)
                    ?: ('Material #' . $material->id);
                $ficha = $material->fichaTecnica->first();

                return [
                    'id'    => $material->id,
                    'value' => $material->id,
                    'name'  => $nombre,
                    'label' => $nombre,
                    'title' => $nombre,
                    'unidad_inventario' => $material->unidad_inventario,
                    'es_material' => true,
                    'ficha' => $ficha ? ['nombre' => $ficha->nombre] : null,
                    'ficha_materiales' => $ficha?->materiales->map(fn ($fila) => [
                        'material_id'       => $fila->material_componente_id,
                        'nombre'            => trim(($fila->material?->codigo_material ? $fila->material->codigo_material . ' — ' : '') . $fila->material?->nombre_base),
                        'consumo_estandar'  => (float) $fila->consumo_estandar,
                        'unidad_medida'     => $fila->unidad_medida,
                        'costo_asociado_teorico' => (float) $fila->costo_asociado_teorico,
                        'precio_unitario'   => (float) ($preciosPorMaterial[$fila->material_componente_id] ?? 0),
                        'metodo_costos'     => $metodoCostos,
                        'existencia_actual' => (float) ($fila->material?->existencia_actual ?? 0),
                    ])->values()->all() ?? [],
                ];
            })->values()->all();

        $siguienteNumeroOp = (int) str_replace('OP-', '', OrdenProduccion::siguienteNumeroOp());

        $estadosMateriales = EstadoOpMaterial::all(['id', 'nombre'])
            ->map(function ($estado) {
                return [
                    'id'    => $estado->id,
                    'value' => $estado->id,
                    'name'  => $estado->nombre,
                    'label' => $estado->nombre,
                    'title' => $estado->nombre,
                ];
            });

        $talleres = Taller::all(['id', 'nombre', 'nombre_comercial'])
            ->map(function ($taller) {
                // Los desplegables muestran el nombre comercial del taller.
                $nombre = $taller->nombre_comercial ?: $taller->nombre;
                return [
                    'id'    => $taller->id,
                    'value' => $taller->id,
                    'name'  => $nombre,
                    'label' => $nombre,
                    'title' => $nombre,
                ];
            });

        $empresasExternas = EmpresaExterna::all(['id', 'nombre', 'nombre_comercial'])
            ->map(function ($empresa) {
                $nombre = $empresa->nombre_comercial ?: $empresa->nombre;
                return [
                    'id'    => $empresa->id,
                    'value' => $empresa->id,
                    'name'  => $nombre,
                    'label' => $nombre,
                    'title' => $nombre,
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

        $gruposTallas = GrupoTalla::whereNotNull('valor')
            ->orderBy('valor')
            ->orderBy('id')
            ->get(['id', 'nombre', 'valor'])
            ->groupBy('valor')
            ->map(function ($filas, $valor) {
                $nombres = $filas->pluck('nombre')->filter()->implode(', ');
                $etiqueta = 'Grupo ' . $valor . ($nombres ? ' (' . $nombres . ')' : '');
                return [
                    'id'     => (int) $valor,
                    'value'  => (int) $valor,
                    'name'   => $etiqueta,
                    'label'  => $etiqueta,
                    'title'  => $etiqueta,
                    'tallas' => $filas->map(fn ($fila) => [
                        'id'                   => $fila->id,
                        'talla'                => $fila->nombre,
                        'cantidad_planificada' => null,
                    ])->values()->all(),
                ];
            })->values()->all();

        return [
            // 'responsable_id'         => $usuarios,
            // 'responsable'            => $usuarios,
            // 'estado_op_id'           => $estadoOperaciones,
            // 'estado_op'              => $estadoOperaciones,
            // 'producto_referencia_id' => $productos,
            // 'producto_referencia'    => $productos,
            // 'producto'               => $productos,
            // 'taller'                 => $talleres,
            // 'siguiente_numero_op'    => $siguienteNumeroOp,
            'responsable_id'         => $usuarios,
            'user_id'                => $usuarios,
            'estado_op_id'           => $estadoOperaciones,
            'producto_referencia_id' => $productos,
            'producto_id'            => $productos,
            'materiales_procesados'  => $materialesProcesados,
            'taller_id'              => $talleres,
            'tipo'                   => [
                ['id' => 'taller', 'value' => 'taller', 'name' => 'Taller', 'label' => 'Taller', 'title' => 'Taller'],
                ['id' => 'externa', 'value' => 'externa', 'name' => 'Externa', 'label' => 'Externa', 'title' => 'Externa'],
            ],
            'empresa_externa_id'     => $empresasExternas,
            'proveedor_destino_id'   => $proveedores,
            'grupos_tallas'          => $gruposTallas,
            'estado_op_material_id'  => $estadosMateriales,
            'siguiente_numero_op'    => $siguienteNumeroOp,
        ];
    }

	public function losSelect(string $modelClass, string $label, string $displayField = 'nombre'): array {
		if (!class_exists($modelClass)) {
			return [];
		}
		
		$modelCollection = call_user_func([$modelClass, 'all']);
		
		if (!$modelCollection instanceof Collection) {
			return [];
		}
		
		return [
			$label => Myhelp::NEW_turnInSelectID($modelCollection, $label.' ', $displayField),
		];
	}

    public function Filtros($request): Builder {
        $OrdenProduccions = OrdenProduccion::query()->with(['productoReferencia', 'taller', 'empresaExterna', 'estadoOp', 'estadoOpSecundario', 'responsable', 'distribucionesPorTallas', 'productos']);
        if ($request->has('search')) {
            $OrdenProduccions = $OrdenProduccions->where(function ($query) use ($request) {
                $query->where('numero_op', 'LIKE', "%" . $request->search . "%");
            });
        }

        if ($request->has(['field', 'order'])) {
            $OrdenProduccions = $OrdenProduccions->orderBy($request->field, $request->order);
        } else {
            $OrdenProduccions = $OrdenProduccions->orderBy('updated_at', 'DESC');
        }
        return $OrdenProduccions;
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

    public function PerPageAndPaginate($request, $OrdenProduccions) {
        $perPage = $request->has('perPage') ? $request->perPage : 10;
        $page = request('page', 1); // Current page number
        $paginated = new LengthAwarePaginator(
            $OrdenProduccions->forPage($page, $perPage),
            $OrdenProduccions->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
        return $paginated;
    }

    /**
     * Materiales seleccionados y su cantidad (una unidad si el formulario no
     * la envía). El pivote es la fuente para costos, kit y recepción.
     *
     * @return array<int, array{cantidad: int}>
     */
    private function materialesPivotDesdeRequest(Request $request): array
    {
        return collect((array) $request->input('materiales', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->mapWithKeys(fn ($id) => [
                $id => ['cantidad' => max(1, (int) $request->input('materiales_cantidad.' . $id, 1))],
            ])
            ->all();
    }

    public function store(StoreOrdenProduccionRequest $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin STORE:OrdenProduccions');
        DB::beginTransaction();

        $materialesPivot = $this->materialesPivotDesdeRequest($request);
        $esModoMaterial = !empty($materialesPivot);

        // El costo de proceso no se digita: se deriva de los procesos de los
        // productos al recalcular los costos de la OP.
        $data = $request->only(['cantidad_planificada', 'fecha_prevista_envio_taller', 'taller_id', 'tipo', 'empresa_externa_id', 'id_shopify_producto', 'observaciones', 'estado_op_material_id', 'externa']);
        $data['user_id'] = $request->user()?->id;
        $data['producto_referencia_id'] = $request->filled('producto_referencia_id') ? $request->input('producto_referencia_id') : null;
        if ($esModoMaterial) {
            // La cantidad planificada de una OP de materiales es la suma de las
            // unidades de sus materiales procesados.
            $data['cantidad_planificada'] = array_sum(array_map(
                fn ($fila) => (int) $fila['cantidad'],
                $materialesPivot
            ));
            $data['producto_referencia_id'] = null;
        }
        $data['fecha_prevista_envio_taller'] = ($data['fecha_prevista_envio_taller'] ?? '') === '' ? null : $data['fecha_prevista_envio_taller'];
        $data['estado_op_material_id'] = ($data['estado_op_material_id'] ?? '') === '' ? null : $data['estado_op_material_id'];
        $data = $this->normalizarTipo($data);

        $data['estado_op_id'] = EstadoOperacion::where('nombre', 'Preparación')->value('id');

        // El número de OP se calcula siempre en el servidor a partir del mayor
        // registrado (+1) para impedir que dos OP compartan el mismo código.
        $data['numero_op'] = OrdenProduccion::siguienteNumeroOp();

        $OrdenProduccion = new OrdenProduccion();
        $OrdenProduccion->forceFill($data);
        $OrdenProduccion->save();

        $grupoTallas = $request->input('grupo_tallas');
        // Las tallas pueden venir de la plantilla del producto (con id) o ser
        // nuevas. En ambos casos se guarda la cantidad planificada que digitó
        // el usuario, para que la OP y su kit no queden en cero.
        if (!$esModoMaterial) {
            $this->sincronizarTallas(
                $OrdenProduccion,
                $request->input('distribucion_tallas', []),
                true,
                $grupoTallas
            );
        }

        $productosOp = $esModoMaterial ? [] : array_map('intval', (array) $request->input('productos', []));
        $OrdenProduccion->productos()->sync($productosOp);
        $OrdenProduccion->materiales()->sync($materialesPivot);

        if ($esModoMaterial) {
            $this->sincronizarDistribucionMateriales($OrdenProduccion, $materialesPivot);
        }

        $cantidadesMateriales = array_map(fn ($fila) => (float) $fila['cantidad'], $materialesPivot);

        // Aviso de materiales insuficientes según la ficha técnica de cada
        // producto o material procesado.
        $faltantes = $this->faltantesDeMateriales(
            $productosOp,
            (int) ($data['cantidad_planificada'] ?? 0),
            array_map('floatval', (array) $request->input('productos_cantidad', [])),
            array_keys($materialesPivot),
            $cantidadesMateriales
        );
        $avisoFaltantes = null;
        if (!empty($faltantes)) {
            $detalle = collect($faltantes)
                ->map(fn ($f) => $f['nombre'] . ': faltan ' . round($f['faltante'], 2) . ' (necesita ' . round($f['necesita'], 2) . ', hay ' . round($f['hay'], 2) . ')')
                ->implode(' | ');
            $avisoFaltantes = ($data['numero_op'] ?? ('OP #' . $OrdenProduccion->id))
                . ': material insuficiente — ' . $detalle;

            // El creador debe enterarse de inmediato aunque haya asignado la OP
            // a otro responsable; el responsable también recibe el aviso.
            $notificaciones = app(\Alejo\Notificaciones\NotificacionesManager::class);
            $destinatarios = array_values(array_unique(array_filter([
                $request->user()?->id,
                $data['user_id'] ?? null,
            ])));

            if (empty($destinatarios)) {
                $notificaciones->crear($avisoFaltantes, 'alta');
            } else {
                foreach ($destinatarios as $destinatario) {
                    $notificaciones->crear($avisoFaltantes, 'alta', (int) $destinatario);
                }
            }
        }

        // El kit de procesos se genera automáticamente con la OP;
        // estado_requerido queda vacío hasta que el usuario lo digite.
        $this->sincronizarKitProceso(
            $OrdenProduccion,
            $productosOp,
            (int) ($data['cantidad_planificada'] ?? 0),
            array_map('floatval', (array) $request->input('productos_cantidad', [])),
            array_keys($materialesPivot),
            $cantidadesMateriales
        );

        $this->guardarCostosMateriales($OrdenProduccion);

        DB::commit();
        $this->invalidarCache();
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'STORE:OrdenProduccions EXITOSO', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador, false);
        $respuesta = back()->with('success', __('app.label.created_successfully', ['name' => $identificador]));

        // Además de la campana, el aviso se muestra de inmediato al terminar
        // de crear la OP: si hay faltantes y solo se guarda la notificación,
        // el usuario no lo nota hasta que abre la campana.
        if ($avisoFaltantes) {
            $respuesta->with('warning', 'Material insuficiente en ' . $identificador . ': ' . collect($faltantes)
                ->map(fn ($f) => $f['nombre'] . ' (faltan ' . round($f['faltante'], 2) . ')')
                ->implode(' · ') . '. Revisa el resumen de materiales faltantes.');
        }

        return $respuesta;
    }

    /**
     * Guarda la OP como Borrador ("Terminar después"): acepta datos parciales,
     * crea la orden si no existe id (con el número OP calculado en el servidor).
     */
    public function guardarBorrador(Request $request): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin BORRADOR:OrdenProduccions');
        $request->validate([
            'id' => ['nullable', 'integer', 'exists:orden_produccions,id'],
            'numero_op' => ['nullable', 'string', 'regex:/^OP-\d+$/'],
            'cantidad_planificada' => ['nullable', 'numeric', 'min:0'],
            'costo_proceso' => ['nullable', 'numeric', 'min:0'],
            'fecha_prevista_envio_taller' => ['nullable', 'date'],
            'taller_id' => ['nullable', 'integer', 'exists:tallers,id'],
            'tipo' => ['nullable', 'in:taller,externa'],
            'empresa_externa_id' => ['nullable', 'integer', 'exists:empresa_externas,id'],
            'id_shopify_producto' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'estado_op_material_id' => ['nullable', 'integer', 'exists:estado_op_materials,id'],
            'externa' => ['nullable', 'boolean'],
            'productos' => ['nullable', 'array'],
            'productos.*' => ['integer', 'exists:productos,id'],
            'materiales' => ['nullable', 'array'],
            'materiales.*' => ['integer', 'exists:materials,id'],
            'materiales_cantidad' => ['nullable', 'array'],
            'materiales_cantidad.*' => ['integer', 'min:1'],
            'distribucion_tallas' => ['nullable', 'array'],
            'distribucion_tallas.*.id' => ['nullable', 'integer'],
            'distribucion_tallas.*.talla' => ['nullable', 'string', 'max:255'],
            'distribucion_tallas.*.cantidad_planificada' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();

        $materialesPivot = $this->materialesPivotDesdeRequest($request);
        $esModoMaterial = $request->has('materiales')
            ? !empty($materialesPivot)
            : ($request->filled('id') && OrdenProduccion::find((int) $request->input('id'))?->materiales()->exists());

        $data = $request->only(['numero_op', 'cantidad_planificada', 'fecha_prevista_envio_taller', 'taller_id', 'tipo', 'empresa_externa_id', 'id_shopify_producto', 'observaciones', 'estado_op_material_id', 'externa']);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data = $this->normalizarTipo($data);
        $data['estado_op_id'] = EstadoOperacion::where('nombre', 'Borrador')->value('id');
        if ($esModoMaterial) {
            $data['cantidad_planificada'] = array_sum(array_map(
                fn ($fila) => (int) $fila['cantidad'],
                $materialesPivot
            ));
            $data['producto_referencia_id'] = null;
        } elseif (empty($data['cantidad_planificada'])) {
            $data['cantidad_planificada'] = 0;
        }
        $OrdenProduccion = $request->filled('id')
            ? OrdenProduccion::findOrFail((int) $request->input('id'))
            : new OrdenProduccion();

        if (!$OrdenProduccion->exists) {
            // El responsable de una OP nueva es siempre el usuario logeado.
            $data['user_id'] = $request->user()?->id;
            // OP nueva: el número se calcula en el servidor (mayor registrado + 1)
            // y no se confía en el valor del formulario, para no repetir códigos.
            $data['numero_op'] = OrdenProduccion::siguienteNumeroOp();
        } elseif (!empty($data['numero_op'])
            && $data['numero_op'] !== $OrdenProduccion->numero_op
            && OrdenProduccion::where('numero_op', $data['numero_op'])->whereKeyNot($OrdenProduccion->id)->exists()) {
            DB::rollBack();
            Bitacora::rechazo(
                'OrdenProduccion',
                'numeroDuplicado',
                'El número '.$data['numero_op'].' ya está asignado a otra OP.',
                'OrdenProduccion',
                $OrdenProduccion->id,
            );

            return back()->withErrors(['numero_op' => 'Ya existe una OP con el número ' . $data['numero_op'] . '.']);
        }

        $OrdenProduccion->forceFill($data);
        $OrdenProduccion->save();

        if (!$esModoMaterial && $request->has('distribucion_tallas')) {
            $this->sincronizarTallas($OrdenProduccion, $request->input('distribucion_tallas', []), true, $request->input('grupo_tallas'));
        }

        $productosOp = $esModoMaterial
            ? []
            : array_map('intval', (array) $request->input('productos', []));

        if ($request->has('productos')) {
            $OrdenProduccion->productos()->sync($productosOp);
        }

        if ($request->has('materiales')) {
            $OrdenProduccion->materiales()->sync($materialesPivot);
        }

        if ($esModoMaterial) {
            $this->sincronizarDistribucionMateriales($OrdenProduccion, $materialesPivot);
        }

        $cantidadesMateriales = array_map(fn ($fila) => (float) $fila['cantidad'], $materialesPivot);
        $this->sincronizarKitProceso(
            $OrdenProduccion,
            $productosOp,
            (int) ($data['cantidad_planificada'] ?? 0),
            array_map('floatval', (array) $request->input('productos_cantidad', [])),
            array_keys($materialesPivot),
            $cantidadesMateriales
        );

        $this->guardarCostosMateriales($OrdenProduccion);

        DB::commit();
        $this->invalidarCache();
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'BORRADOR:OrdenProduccions EXITOSO', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador, false);
        return back()->with('success', 'Borrador guardado: ' . $identificador . '. Puede continuarla desde el botón "Continuar última OP".');
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
        $permissions = Myhelp::EscribirEnLog($this, ' Begin UPDATE:OrdenProduccions');
        DB::beginTransaction();

        $request->validate([
            'costo_proceso' => ['nullable', 'numeric', 'min:0'],
            'materiales' => ['nullable', 'array'],
            'materiales.*' => ['integer', 'exists:materials,id'],
            'materiales_cantidad' => ['nullable', 'array'],
            'materiales_cantidad.*' => ['integer', 'min:1'],
        ]);

        $OrdenProduccion = OrdenProduccion::findOrFail($id);

        $materialesPivot = $this->materialesPivotDesdeRequest($request);
        $tieneMaterialesEnviados = $request->has('materiales');
        $esModoMaterial = $tieneMaterialesEnviados
            ? !empty($materialesPivot)
            : $OrdenProduccion->materiales()->exists();

        if (!$tieneMaterialesEnviados && $esModoMaterial) {
            $materialesPivot = $OrdenProduccion->materiales()
                ->get()
                ->mapWithKeys(fn ($material) => [
                    $material->id => ['cantidad' => max(1, (int) ($material->pivot->cantidad ?? 1))],
                ])
                ->all();
        }

        if ($esModoMaterial) {
            $elegibles = Material::query()
                ->whereIn('id', array_keys($materialesPivot))
                ->where('material_procesado', true)
                ->whereHas('fichaTecnica')
                ->pluck('id')
                ->map(fn ($materialId) => (int) $materialId)
                ->all();

            if (count($elegibles) !== count($materialesPivot)) {
                DB::rollBack();
                throw ValidationException::withMessages([
                    'materiales' => 'Solo se pueden producir materiales con proceso y ficha técnica.',
                ]);
            }
        }

        $data = $request->only(['numero_op', 'cantidad_planificada', 'fecha_prevista_envio_taller', 'taller_id', 'tipo', 'empresa_externa_id', 'id_shopify_producto', 'observaciones', 'estado_op_material_id', 'externa']);
        $data['producto_referencia_id'] = $request->filled('producto_referencia_id') ? $request->input('producto_referencia_id') : null;
        if ($esModoMaterial) {
            $data['cantidad_planificada'] = array_sum(array_map(
                fn ($fila) => (int) $fila['cantidad'],
                $materialesPivot
            ));
            $data['producto_referencia_id'] = null;
        }
        $data['fecha_prevista_envio_taller'] = ($data['fecha_prevista_envio_taller'] ?? '') === '' ? null : $data['fecha_prevista_envio_taller'];
        $data['estado_op_material_id'] = ($data['estado_op_material_id'] ?? '') === '' ? null : $data['estado_op_material_id'];
        $data = $this->normalizarTipo($data);

        if (!empty($data['numero_op'])
            && $data['numero_op'] !== $OrdenProduccion->numero_op
            && OrdenProduccion::where('numero_op', $data['numero_op'])->whereKeyNot($OrdenProduccion->id)->exists()) {
            DB::rollBack();
            Bitacora::rechazo(
                'OrdenProduccion',
                'numeroDuplicado',
                'El número '.$data['numero_op'].' ya está asignado a otra OP.',
                'OrdenProduccion',
                $OrdenProduccion->id,
            );

            return back()->withErrors(['numero_op' => 'Ya existe una OP con el número ' . $data['numero_op'] . '.']);
        }

        $eraBorrador = $OrdenProduccion->estadoOp?->nombre === 'Borrador';

        $OrdenProduccion->forceFill($data);
        $OrdenProduccion->save();

        // Al continuar un borrador guardado con "Terminar después", la orden pasa a Preparación
        if ($eraBorrador) {
            $OrdenProduccion->forceFill([
                'estado_op_id' => EstadoOperacion::where('nombre', 'Preparación')->value('id'),
            ])->save();
        }

        if (!$esModoMaterial && $request->has('distribucion_tallas')) {
            $this->sincronizarTallas($OrdenProduccion, $request->input('distribucion_tallas', []), true, $request->input('grupo_tallas'));
        }

        $productosOp = $esModoMaterial
            ? []
            : ($request->has('productos')
                ? array_map('intval', (array) $request->input('productos', []))
                : $OrdenProduccion->productos()->pluck('productos.id')->all());

        if ($request->has('productos')) {
            $OrdenProduccion->productos()->sync($productosOp);
        }

        if ($tieneMaterialesEnviados || $esModoMaterial) {
            $OrdenProduccion->materiales()->sync($materialesPivot);
        }

        if ($esModoMaterial) {
            $this->sincronizarDistribucionMateriales($OrdenProduccion, $materialesPivot);
        }

        $cantidadesMateriales = array_map(fn ($fila) => (float) $fila['cantidad'], $materialesPivot);

        // Si la OP cambia de productos o cantidades, el kit se mantiene
        // sincronizado: crea las filas faltantes y actualiza las cantidades.
        $this->sincronizarKitProceso(
            $OrdenProduccion,
            $productosOp,
            (int) ($data['cantidad_planificada'] ?? $OrdenProduccion->cantidad_planificada ?? 0),
            array_map('floatval', (array) $request->input('productos_cantidad', [])),
            array_keys($materialesPivot),
            $cantidadesMateriales
        );

        $this->guardarCostosMateriales($OrdenProduccion);

        DB::commit();
        $this->invalidarCache();
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'UPDATE:OrdenProduccions EXITOSO', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $identificador]));
    }

    /**
     * Renueva y guarda en la OP el precio de sus materiales, según el método
     * vigente del parámetro costos_promedio. Se vuelve a cargar la orden para
     * que las cantidades recién sincronizadas sean las que se costean.
     */
    private function guardarCostosMateriales(OrdenProduccion $op): void {
        $recargada = $op->fresh([
            'productos.fichaTecnica.materiales',
            'productoReferencia.fichaTecnica.materiales',
            'materiales.fichaTecnica.materiales',
            'productos.procesos',
            'productoReferencia.procesos',
            'distribucionesPorTallas',
            'costosMateriales',
        ]);

        if ($recargada) {
            $this->costosMateriales->recalcular($recargada);
        }
    }

    /**
     * Necesidad agregada de materiales: consumo_estandar de la ficha × cantidad
     * del producto o material procesado. Si no viene el mapa de cantidades por
     * objetivo se usa la cantidad planificada total de la OP.
     *
     * @param  array<int, float>  $cantidadesPorProducto
     * @param  array<int, float>  $cantidadesPorMaterial
     * @return array<int, array{nombre:string, unidad:string, necesita:float, hay:float}>
     */
    private function necesidadMateriales(
        array $productoIds,
        int $cantidadPlanificada,
        array $cantidadesPorProducto = [],
        array $materialIds = [],
        array $cantidadesPorMaterial = []
    ): array {
        if (empty($productoIds) && empty($materialIds)) {
            return [];
        }

        $necesidad = [];
        $productos = Producto::with('fichaTecnica.materiales.material')
            ->whereIn('id', $productoIds)
            ->get();

        $objetivos = collect();
        foreach ($productos as $producto) {
            $objetivos->push([
                'id' => (int) $producto->id,
                'cantidad' => array_key_exists($producto->id, $cantidadesPorProducto)
                    ? (float) $cantidadesPorProducto[$producto->id]
                    : ($cantidadesPorProducto ? 0 : $cantidadPlanificada),
                'ficha' => $producto->fichaTecnica->first(),
            ]);
        }

        if (!empty($materialIds)) {
            $materiales = Material::with('fichaTecnica.materiales.material')
                ->whereIn('id', $materialIds)
                ->get();

            foreach ($materiales as $material) {
                $objetivos->push([
                    'id' => (int) $material->id,
                    'cantidad' => array_key_exists($material->id, $cantidadesPorMaterial)
                        ? (float) $cantidadesPorMaterial[$material->id]
                        : ($cantidadesPorMaterial ? 0 : $cantidadPlanificada),
                    'ficha' => $material->fichaTecnica->first(),
                ]);
            }
        }

        foreach ($objetivos as $objetivo) {
            if ($objetivo['cantidad'] <= 0 || !$objetivo['ficha']) {
                continue;
            }

            foreach ($objetivo['ficha']->materiales as $fila) {
                $id = $fila->material_componente_id;
                if (!$id) {
                    continue;
                }
                if (!isset($necesidad[$id])) {
                    $necesidad[$id] = [
                        'nombre'   => trim(($fila->material?->codigo_material ? $fila->material->codigo_material . ' — ' : '') . $fila->material?->nombre_base),
                        'unidad'   => $fila->unidad_medida ?: ($fila->material?->unidad_inventario ?: 'u'),
                        'necesita' => 0,
                        'hay'      => (float) ($fila->material?->existencia_actual ?? 0),
                    ];
                }
                $necesidad[$id]['necesita'] += (float) $fila->consumo_estandar * $objetivo['cantidad'];
            }
        }

        return $necesidad;
    }

    /**
     * Materiales cuya necesidad supera la existencia actual en inventario.
     */
    private function faltantesDeMateriales(
        array $productoIds,
        int $cantidadPlanificada,
        array $cantidadesPorProducto = [],
        array $materialIds = [],
        array $cantidadesPorMaterial = []
    ): array {
        return collect($this->necesidadMateriales(
            $productoIds,
            $cantidadPlanificada,
            $cantidadesPorProducto,
            $materialIds,
            $cantidadesPorMaterial
        ))
            ->map(fn ($fila) => $fila + ['faltante' => max(0, $fila['necesita'] - $fila['hay'])])
            ->filter(fn ($fila) => $fila['faltante'] > 0)
            ->values()->all();
    }

    /**
     * Sincroniza el KitProceso de la OP: una fila por material de la ficha
     * técnica, con estado_requerido y proveedor vacíos hasta que el usuario
     * los defina. Al editar una OP se actualizan las cantidades y se crean
     * las filas que falten, sin pisar el trabajo ya registrado.
     */
    private function sincronizarKitProceso(
        OrdenProduccion $op,
        array $productoIds,
        int $cantidadPlanificada,
        array $cantidadesPorProducto = [],
        array $materialIds = [],
        array $cantidadesPorMaterial = []
    ): void {
        if (empty($productoIds) && empty($materialIds)) {
            return;
        }

        $necesidad = $this->necesidadMateriales(
            $productoIds,
            $cantidadPlanificada,
            $cantidadesPorProducto,
            $materialIds,
            $cantidadesPorMaterial
        );
        if (empty($necesidad)) {
            return;
        }

        foreach ($necesidad as $materialId => $fila) {
            $kit = KitProceso::firstOrNew([
                'orden_produccion_id' => $op->id,
                'material_id' => $materialId,
            ]);

            $kit->unidad = $fila['unidad'];
            $kit->cantidad_requerida = round($fila['necesita'], 4);

            // La disponibilidad siempre refleja la existencia actual del
            // material; el faltante lo calcula el modelo al guardar.
            $kit->cantidad_disponible = (float) ($fila['hay'] ?? 0);

            if (!$kit->exists) {
                $kit->estado_requerido = null;
                $kit->proveedor_id = null;
                $kit->listo = false;
            }

            $kit->save();
        }

        // Si la OP dejó de requerir un material, se elimina su fila solo
        // cuando todavía no tiene trabajo registrado.
        $sobrantes = KitProceso::where('orden_produccion_id', $op->id)
            ->whereNotIn('material_id', array_keys($necesidad))
            ->get();

        foreach ($sobrantes as $kit) {
            if (empty($kit->estado_requerido)
                && empty($kit->proveedor_id)
                && empty($kit->cantidad_preparada)
                && empty($kit->observaciones)) {
                $kit->delete();
            }
        }
    }

    /**
     * Cambia exclusivamente el estado de una OP, validando la transición
     * contra la columna puede_pasar_a del estado actual.
     *
     * Al pasar a "Recibiendo" (o al actualizar una OP que ya está en ese estado)
     * se genera/actualiza un registro de recepción por cada talla: la talla, su
     * producto y lo esperado salen de la OP, y la suma de aprobada + en
     * reparación + no recuperable debe cuadrar con esa cantidad (esa suma es la
     * cantidad recibida).
     *
     * "Cerrada" no se elige a mano: cuando la recepción deja cada talla completa
     * y sin pares en reparación, el sistema cierra la OP en la misma operación.
     * Todos los pares que quedan en reparación comparten una única fecha de
     * compromiso pendiente y generan los avisos programados (dos días antes y
     * el día del compromiso).
     */
    /** Único par de estados que puede coexistir en una OP al mismo tiempo. */
    private const PAR_COEXISTENCIA = ['Esperando materiales', 'Procesos externos'];

    public function cambiarEstado(Request $request, $id): RedirectResponse {
        $datos = $request->validate([
            'estado_op_id' => ['required', 'integer', 'exists:estado_operacions,id'],
            'estado_op_secundario_id' => ['nullable', 'integer', 'exists:estado_operacions,id', 'different:estado_op_id'],
            'fecha_recepcion' => ['nullable', 'date'],
            'fecha_compromiso' => ['nullable', 'date'],
            'recepciones' => ['nullable', 'array'],
            'recepciones.*.distribucion_id' => ['required', 'integer'],
            'recepciones.*.cantidad_aprobada' => ['nullable', 'integer', 'min:0'],
            'recepciones.*.cantidad_en_reparacion' => ['nullable', 'integer', 'min:0'],
            'recepciones.*.cantidad_no_recuperable' => ['nullable', 'integer', 'min:0'],
            'recepciones.*.fecha_compromiso' => ['nullable', 'date'],
            'proceso_externo' => ['nullable', 'array'],
            'proceso_externo.proveedor_destino_id' => ['nullable', 'integer', 'exists:proveedors,id'],
            'proceso_externo.etapa_componente' => ['nullable', 'string', 'max:255'],
            'proceso_externo.bodega_origen' => ['nullable', 'string', 'max:255'],
            'proceso_externo.servicio_proceso' => ['nullable', 'string', 'max:255'],
            'proceso_externo.fecha_prevista_regreso' => ['nullable', 'date'],
            'proceso_externo.resultado_observaciones' => ['nullable', 'string'],
            'proceso_externo.items' => ['nullable', 'array'],
            'proceso_externo.items.*.material_origen_id' => ['required', 'integer', 'exists:materials,id'],
            'proceso_externo.items.*.cantidad_salida' => ['nullable', 'numeric', 'gt:0'],
            'proceso_externo.items.*.unidad_medida' => ['nullable', 'string', 'max:255'],
            'fecha_regreso_real' => ['nullable', 'date'],
        ]);

        // Si la petición no envía fecha, se registra la fecha del guardado.
        $fechaRecepcion = $datos['fecha_recepcion'] ?? now()->toDateString();

        $OrdenProduccion = OrdenProduccion::findOrFail($id);
        $estadoActual = $OrdenProduccion->estadoOp;
        $permitidos = $estadoActual?->idsPuedePasarA() ?? [];
        $estadoSolicitadoId = (int) $datos['estado_op_id'];
        $nuevoEstado = EstadoOperacion::find($estadoSolicitadoId);

        // Reenviar el estado actual es válido: permite ajustar solo el estado secundario.
        $esMismoEstado = $estadoSolicitadoId === (int) $OrdenProduccion->estado_op_id;

        if (! $esMismoEstado && ! in_array($estadoSolicitadoId, $permitidos, true)) {
            Bitacora::rechazo(
                'OrdenProduccion',
                'transicionNoPermitida',
                "'{$estadoActual?->nombre}' → '{$nuevoEstado?->nombre}' no es una transición permitida.",
                'OrdenProduccion',
                $OrdenProduccion->id,
            );

            return back()->withErrors([
                'estado_op_id' => "Transición no permitida desde el estado '{$estadoActual?->nombre}'.",
            ]);
        }

        $secundarioId = $datos['estado_op_secundario_id'] ?? null;
        $secundarioNombre = $secundarioId
            ? EstadoOperacion::whereKey($secundarioId)->value('nombre')
            : null;

        // Coexistencia: solo "Esperando materiales" + "Procesos externos".
        if ($secundarioId) {
            if (! in_array($nuevoEstado?->nombre, self::PAR_COEXISTENCIA, true)
                || ! in_array($secundarioNombre, self::PAR_COEXISTENCIA, true)
                || $secundarioNombre === $nuevoEstado?->nombre) {
                Bitacora::rechazo(
                    'OrdenProduccion',
                    'estadosIncompatibles',
                    "'{$nuevoEstado?->nombre}' + '{$secundarioNombre}' no pueden coexistir.",
                    'OrdenProduccion',
                    $OrdenProduccion->id,
                );

                return back()->withErrors([
                    'estado_op_secundario_id' => 'Solo «Esperando materiales» y «Procesos externos» pueden coexistir como estados simultáneos.',
                ]);
            }
        }

        // Se registra el proceso externo solo al entrar a ese estado, no en
        // cada edición posterior de una OP que ya está en procesos externos.
        $estadoSecundarioActualNombre = $OrdenProduccion->estadoOpSecundario?->nombre;
        $estabaEnProcesoExterno = $estadoActual?->nombre === 'Procesos externos'
            || $estadoSecundarioActualNombre === 'Procesos externos';
        $estaraEnProcesoExterno = $nuevoEstado?->nombre === 'Procesos externos'
            || $secundarioNombre === 'Procesos externos';
        $entraProcesoExterno = $estaraEnProcesoExterno && ! $estabaEnProcesoExterno;

        $procesoExternoDatos = $datos['proceso_externo'] ?? [];
        $itemsProceso = collect($procesoExternoDatos['items'] ?? [])
            ->filter(fn ($item) => !empty($item['material_origen_id']))
            ->unique(fn ($item) => (int) $item['material_origen_id'])
            ->values();

        if ($entraProcesoExterno) {
            if (empty($procesoExternoDatos['proveedor_destino_id']) || $itemsProceso->isEmpty()) {
                Bitacora::rechazo(
                    'OrdenProduccion',
                    'procesoExternoIncompleto',
                    'Falta el proveedor destino o los materiales de la salida.',
                    'OrdenProduccion',
                    $OrdenProduccion->id,
                );

                return back()->withErrors([
                    'proceso_externo' => 'Registre el proveedor destino y al menos un material para el proceso externo.',
                ]);
            }

            if (empty(trim((string) ($procesoExternoDatos['etapa_componente'] ?? '')))) {
                Bitacora::rechazo(
                    'OrdenProduccion',
                    'procesoExternoIncompleto',
                    'Falta la etapa o componente del proceso externo.',
                    'OrdenProduccion',
                    $OrdenProduccion->id,
                );

                return back()->withErrors([
                    'proceso_externo.etapa_componente' => 'Indique la etapa o componente del proceso externo.',
                ]);
            }
        }

        // La recepción se muestra/actualiza cuando la OP ya está recibiendo o
        // cuando se selecciona "Recibiendo" desde cualquier estado permitido.
        $esRecepcion = $estadoActual?->nombre === 'Recibiendo'
            || $nuevoEstado?->nombre === 'Recibiendo';

        $recepciones = $esRecepcion
            ? $this->recepcionesDeCalidad(
                $OrdenProduccion,
                $datos['recepciones'] ?? [],
                $datos['fecha_compromiso'] ?? null
            )
            : [];

        $totalEnReparacion = collect($recepciones)
            ->sum(fn ($fila) => (int) ($fila['cantidad_en_reparacion'] ?? 0));

        // Si la OP no se está recibiendo en este momento, los pares en
        // reparación se consultan en las recepciones ya guardadas.
        $totalEnReparacionEfectivo = $esRecepcion && !empty($recepciones)
            ? (int) $totalEnReparacion
            : (int) $OrdenProduccion->recepcionesCalidad()
                ->where('cantidad_en_reparacion', '>', 0)
                ->sum('cantidad_en_reparacion');

        // Una OP no puede cerrarse mientras queden pares en reparación.
        if ($nuevoEstado?->nombre === 'Cerrada' && $totalEnReparacionEfectivo > 0) {
            Bitacora::rechazo(
                'OrdenProduccion',
                'cierreBloqueado',
                "Quedan {$totalEnReparacionEfectivo} par(es) en reparación.",
                'OrdenProduccion',
                $OrdenProduccion->id,
            );

            return back()->withErrors([
                'estado_op_id' => "No se puede cerrar la OP: aún hay {$totalEnReparacionEfectivo} par(es) en reparación.",
            ]);
        }

        // Con todas las tallas completas y sin pares en reparación la OP ya no
        // tiene nada pendiente: el sistema la cierra, sin que el usuario elija
        // "Cerrada".
        $estadoFinal = $nuevoEstado;
        $seCierraAutomatica = false;
        if ($esRecepcion && $nuevoEstado?->nombre === 'Recibiendo' && $this->recepcionCompleta($recepciones)) {
            $estadoCerrada = EstadoOperacion::where('nombre', 'Cerrada')->first();
            if ($estadoCerrada) {
                $estadoFinal = $estadoCerrada;
                $seCierraAutomatica = true;
            }
        }

        $estadoFinalId = (int) ($estadoFinal?->id ?? $estadoSolicitadoId);
        $estadoFinalNombre = $estadoFinal?->nombre ?? $nuevoEstado?->nombre;
        $recepcionesGeneradas = 0;
        $mermasRegistradas = 0;
        $procesosCreados = 0;
        $materialesDescontados = 0;
        $productosIncrementados = 0;
        $compromisosAvisados = 0;

        // Entrar a "En taller" saca el kit de bodega. Aplica también a las OPs
        // externas: el material sale de inventario al entregarse al taller.
        $entraEnTaller = $estadoFinalNombre === 'En taller'
            && $estadoActual?->nombre !== 'En taller';

        // El cierre no se digita: las pérdidas y el costo real final se
        // calculan con los productos recibidos y el costo guardado en la OP.
        $esCierre = $estadoFinalNombre === 'Cerrada';
        $cierreDatos = [];

        if ($esCierre) {
            $filasCierre = $recepciones;

            if (empty($filasCierre)) {
                $filasCierre = $OrdenProduccion->recepcionesCalidad()
                    ->get()
                    ->map(fn ($recepcion) => [
                        'producto_id' => $recepcion->producto_referencia_id,
                        'material_id' => $recepcion->material_referencia_id,
                        'cantidad_esperada' => $recepcion->cantidad_esperada,
                        'cantidad_aprobada' => $recepcion->cantidad_aprobada,
                        'cantidad_no_recuperable' => $recepcion->cantidad_no_recuperable,
                    ])
                    ->all();
            }

            $cierreDatos = $this->costosRecepcion->resumenCierre($OrdenProduccion, $filasCierre);
        }

        // Materiales del kit que se registrarán como salida al proceso externo.
        $kitsProceso = $entraProcesoExterno
            ? $OrdenProduccion->kitProcesos()->with('material')->get()
                ->keyBy(fn ($kit) => (int) $kit->material_id)
            : collect();

        // "Lista para taller" cierra los procesos externos pendientes: es
        // obligatorio indicar cuándo regresó el material.
        if ($estadoFinalNombre === 'Lista para taller'
            && empty($datos['fecha_regreso_real'])
            && ProcesoExterno::where('orden_produccion_id', $OrdenProduccion->id)
                ->whereNull('fecha_regreso_real')
                ->exists()) {
            Bitacora::rechazo(
                'OrdenProduccion',
                'faltaFechaRegreso',
                'Hay procesos externos sin fecha real de regreso.',
                'OrdenProduccion',
                $OrdenProduccion->id,
            );

            return back()->withErrors([
                'fecha_regreso_real' => 'Indique la fecha real de regreso de los procesos externos.',
            ]);
        }

        // "Desde cuándo está en recepción": se fija al entrar y se conserva
        // mientras la OP siga en ese estado; se limpia al salir.
        $recepcionDesde = $estadoFinal?->nombre === 'Recibiendo'
            ? ($OrdenProduccion->recepcion_desde ?: now())
            : null;

        DB::transaction(function () use (
            $OrdenProduccion,
            $estadoFinalId,
            $secundarioId,
            $recepciones,
            $fechaRecepcion,
            $recepcionDesde,
            $entraProcesoExterno,
            $procesoExternoDatos,
            $itemsProceso,
            $kitsProceso,
            $estadoFinalNombre,
            $datos,
            $entraEnTaller,
            $esCierre,
            $cierreDatos,
            &$recepcionesGeneradas,
            &$mermasRegistradas,
            &$procesosCreados,
            &$materialesDescontados,
            &$productosIncrementados,
            &$compromisosAvisados
        ) {
            if ($entraEnTaller) {
                $materialesDescontados = $this->descontarMaterialesTaller($OrdenProduccion);
            }

            $OrdenProduccion->forceFill([
                'estado_op_id' => $estadoFinalId,
                'estado_op_secundario_id' => $secundarioId,
                'recepcion_desde' => $recepcionDesde,
            ])->save();

            if ($esCierre) {
                CierreOrdenProduccion::updateOrCreate(
                    ['orden_produccion_id' => $OrdenProduccion->id],
                    [
                        'perdidas_mermas'      => round((float) $cierreDatos['perdidas_mermas'], 2),
                        'costo_real_final'     => round((float) $cierreDatos['costo_real_final'], 2),
                        'dato_esperado'        => $cierreDatos['dato_esperado'],
                        'dato_real'            => $cierreDatos['dato_real'],
                        'dato_no_recuperable'  => $cierreDatos['dato_no_recuperable'],
                        'diferencia'           => $cierreDatos['diferencia'],
                        'completado'           => true,
                        'estado_accion'        => 'cerrado',
                        'control_cierre'       => 'Cierre de OP',
                        'responsable_id'       => Auth::id() ?: $OrdenProduccion->user_id,
                        'fecha_cierre'         => now(),
                    ]
                );
            }

            if (!empty($recepciones)) {
                $recepcionesGeneradas = RecepcionCalidad::generarDesdeOrden($OrdenProduccion, $recepciones, $fechaRecepcion);
                $mermasRegistradas = $this->mermasRecepcion->sincronizar($OrdenProduccion, Auth::id());
                $productosIncrementados = $this->incrementarProductos($OrdenProduccion, $recepciones);
                $compromisosAvisados = $this->registrarCompromisosReparacion($OrdenProduccion, $recepciones);
            }

            if ($entraProcesoExterno) {
                $fechaSalida = now();

                foreach ($itemsProceso as $item) {
                    $materialId = (int) $item['material_origen_id'];
                    $kit = $kitsProceso->get($materialId);

                    if (!$kit) {
                        throw ValidationException::withMessages([
                            'proceso_externo' => 'Uno de los materiales seleccionados no pertenece al kit de la OP.',
                        ]);
                    }

                    // La cantidad sale de la OP (kit), no de lo que digite el usuario.
                    $cantidad = round((float) ($kit->cantidad_requerida ?? 0), 4);
                    if ($cantidad <= 0) {
                        continue;
                    }

                    ProcesoExterno::create([
                        'orden_produccion_id'     => $OrdenProduccion->id,
                        'etapa_componente'        => $procesoExternoDatos['etapa_componente'] ?? null,
                        'material_origen_id'      => $materialId,
                        'cantidad_salida'         => $cantidad,
                        'unidad_medida'           => $item['unidad_medida']
                            ?? $kit->unidad
                            ?? $kit->material?->unidad_inventario,
                        'proveedor_destino_id'    => (int) $procesoExternoDatos['proveedor_destino_id'],
                        'bodega_origen'           => $procesoExternoDatos['bodega_origen'] ?? null,
                        'servicio_proceso'        => $procesoExternoDatos['servicio_proceso'] ?? null,
                        'fecha_salida'            => $fechaSalida,
                        'fecha_prevista_regreso'  => $procesoExternoDatos['fecha_prevista_regreso'] ?? null,
                        'resultado_observaciones' => $procesoExternoDatos['resultado_observaciones'] ?? null,
                    ]);

                    $procesosCreados++;
                }

                if ($procesosCreados === 0) {
                    throw ValidationException::withMessages([
                        'proceso_externo' => 'Los materiales seleccionados no tienen cantidad pendiente para salir.',
                    ]);
                }
            }

            if ($estadoFinalNombre === 'Lista para taller' && !empty($datos['fecha_regreso_real'])) {
                ProcesoExterno::where('orden_produccion_id', $OrdenProduccion->id)
                    ->whereNull('fecha_regreso_real')
                    ->update(['fecha_regreso_real' => $datos['fecha_regreso_real']]);
            }
        });

        $this->invalidarCache();

        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'CAMBIAR-ESTADO:OrdenProduccions', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador . ' | ' . ($estadoActual?->nombre ?? '?') . ' -> ' . ($estadoFinalNombre ?? '?') . ($seCierraAutomatica ? ' (cierre automático)' : ''), false);

        $mensaje = __('app.label.updated_successfully2', ['nombre' => $identificador]);
        if ($recepcionesGeneradas > 0) {
            $mensaje .= ' Se generaron ' . $recepcionesGeneradas . ' registros de recepción de calidad.';
        }
        if ($mermasRegistradas > 0) {
            $mensaje .= ' ' . $mermasRegistradas . ' par(es) no recuperable(s) quedaron registrados como merma.';
        }
        if ($procesosCreados > 0) {
            $mensaje .= ' Se registraron ' . $procesosCreados . ' materiales en proceso externo.';
        }
        if ($materialesDescontados > 0) {
            $mensaje .= ' Se descontaron ' . $materialesDescontados . ' materiales del inventario para el taller.';
        }
        if ($productosIncrementados > 0) {
            $mensaje .= ' Se incrementó el inventario de ' . $productosIncrementados
                . ' objetivo(s) recibidos por la OP.';
        }
        if ($esCierre) {
            $mensaje .= $seCierraAutomatica
                ? ' La recepción quedó completa: la OP se cerró automáticamente con su cierre registrado.'
                : ' La OP quedó cerrada con su cierre registrado.';
        }
        if ($compromisosAvisados > 0) {
            $mensaje .= ' Se crearon los avisos de ' . $compromisosAvisados
                . ' fecha(s) de compromiso pendiente.';
        }

        return back()->with('success', $mensaje);
    }

    /**
     * Guarda un borrador de la recepción de calidad (Aprobada / En reparación /
     * No recuperable) sin exigir que la suma cuadre exactamente con la cantidad
     * esperada. Permite al usuario guardar lo que lleva hasta el momento y
     * terminarlo después. Si queda al menos un producto recibido (en el estado
     * que sea), la OP pasa a «Recibiendo».
     */
    public function guardarRecepcionBorrador(Request $request, $id): RedirectResponse {
        $datos = $request->validate([
            'fecha_recepcion'                        => ['nullable', 'date'],
            'fecha_compromiso'                       => ['nullable', 'date'],
            'recepciones'                            => ['nullable', 'array'],
            'recepciones.*.distribucion_id'          => ['required', 'integer'],
            'recepciones.*.cantidad_aprobada'        => ['nullable', 'integer', 'min:0'],
            'recepciones.*.cantidad_en_reparacion'   => ['nullable', 'integer', 'min:0'],
            'recepciones.*.cantidad_no_recuperable'  => ['nullable', 'integer', 'min:0'],
        ]);

        $orden = OrdenProduccion::findOrFail($id);
        $fechaRecepcion = $datos['fecha_recepcion'] ?? now()->toDateString();
        $fechaCompromiso = $datos['fecha_compromiso'] ?? null;

        $distribuciones = $orden->distribucionesPorTallas()->get()->keyBy('id');

        $productosOp = $orden->productos()->pluck('productos.id')->map(fn ($pid) => (int) $pid)->all();

        $guardadas = 0;
        $paresRecibidos = 0;
        $filasCompromiso = [];
        foreach ($datos['recepciones'] ?? [] as $fila) {
            $distribucion = $distribuciones->get((int) ($fila['distribucion_id'] ?? 0));
            if (!$distribucion) {
                continue;
            }

            $aprobada      = isset($fila['cantidad_aprobada'])      ? (int) $fila['cantidad_aprobada']      : null;
            $reparacion    = isset($fila['cantidad_en_reparacion'])  ? (int) $fila['cantidad_en_reparacion'] : null;
            $noRecuperable = isset($fila['cantidad_no_recuperable']) ? (int) $fila['cantidad_no_recuperable']: null;

            $suma     = ($aprobada ?? 0) + ($reparacion ?? 0) + ($noRecuperable ?? 0);
            $esperada = (int) $distribucion->cantidad_planificada;

            if ($suma > $esperada) {
                Bitacora::rechazo(
                    'OrdenProduccion',
                    'recepcionFueraDePlan',
                    "Talla {$distribucion->talla}: {$suma} recibido(s) contra {$esperada} planificado(s).",
                    'OrdenProduccion',
                    $orden->id,
                );

                return back()->withErrors([
                    'recepciones' => "Talla {$distribucion->talla}: la suma ({$suma}) supera la cantidad esperada ({$esperada}).",
                ]);
            }

            $paresRecibidos += $suma;

            $materialId = (int) ($distribucion->material_id ?? 0) ?: null;
            $productoId = $materialId
                ? null
                : ((int) ($distribucion->producto_id ?? 0)
                    ?: (int) ($orden->producto_referencia_id ?? 0)
                    ?: (count($productosOp) === 1 ? (int) $productosOp[0] : 0));

            RecepcionCalidad::updateOrCreate(
                [
                    'orden_produccion_id'     => $orden->id,
                    'producto_referencia_id'  => $productoId ?: null,
                    'material_referencia_id'  => $materialId,
                    'talla'                   => $distribucion->talla,
                ],
                [
                    'cantidad_esperada'       => $esperada,
                    'cantidad_recibida'       => $suma,
                    'cantidad_aprobada'       => $aprobada,
                    'cantidad_en_reparacion'  => $reparacion,
                    'cantidad_no_recuperable' => $noRecuperable,
                    'fecha_recepcion'         => $fechaRecepcion,
                ]
            );

            $filasCompromiso[] = [
                'producto_id'            => $productoId ?: null,
                'material_id'            => $materialId,
                'talla'                  => $distribucion->talla,
                'cantidad_en_reparacion' => $reparacion ?? 0,
                'fecha_compromiso'       => $fechaCompromiso,
            ];

            $guardadas++;
        }

        // El borrador también conserva la fecha de compromiso pendiente, para
        // que al reabrir el formulario no se pierda lo ya digitado.
        $compromisosAvisados = $this->registrarCompromisosReparacion($orden, $filasCompromiso);

        // Lo digitado como no recuperable ya es pérdida: se registra como merma
        // aunque la recepción todavía esté a medias.
        $mermasRegistradas = $guardadas > 0
            ? $this->mermasRecepcion->sincronizar($orden, Auth::id())
            : 0;

        // Una OP con al menos un producto recibido ya está «Recibiendo», sin
        // importar en qué quedó cada par (aprobado, en reparación o no
        // recuperable). No se toca una OP ya cerrada.
        $pasoARecibiendo = false;
        $estadoActualNombre = $orden->estadoOp?->nombre;
        if ($paresRecibidos > 0 && !in_array($estadoActualNombre, ['Recibiendo', 'Cerrada'], true)) {
            $estadoRecibiendo = EstadoOperacion::where('nombre', 'Recibiendo')->first();
            if ($estadoRecibiendo) {
                $orden->forceFill([
                    'estado_op_id'    => $estadoRecibiendo->id,
                    'recepcion_desde' => $orden->recepcion_desde ?: now(),
                ])->save();
                $pasoARecibiendo = true;
            }
        }

        $this->invalidarCache();

        $identificador = $orden->numero_op ?? $orden->id;
        Myhelp::EscribirEnLog($this, 'RECEPCION-BORRADOR:OrdenProduccions', 'OrdenProduccion id:' . $orden->id . ' | ' . $identificador . ' | ' . $guardadas . ' fila(s)' . ($pasoARecibiendo ? ' | pasó a Recibiendo' : ''), false);

        $mensaje = 'Avance de recepción guardado. Los valores se conservarán al terminar.';
        if ($mermasRegistradas > 0) {
            $mensaje .= ' ' . $mermasRegistradas . ' par(es) no recuperable(s) quedaron registrados como merma.';
        }
        if ($pasoARecibiendo) {
            $mensaje .= ' La OP pasó a «Recibiendo».';
        }
        if ($compromisosAvisados > 0) {
            $mensaje .= ' Se crearon los avisos de ' . $compromisosAvisados
                . ' fecha(s) de compromiso pendiente.';
        }

        return back()->with('success', $mensaje);
    }

    /**
     * Al pasar a «En taller» el kit sale de bodega: se exige que lo preparado
     * sea igual a lo requerido, se valida la existencia y se descuenta el
     * inventario dejando un MaterialMovimiento de salida por material.
     *
     * @throws ValidationException si alguna cantidad no cuadra o falta stock
     */
    private function descontarMaterialesTaller(OrdenProduccion $orden): int {
        $kits = $orden->kitProcesos()->with('material')->orderBy('id')->get();
        if ($kits->isEmpty()) {
            return 0;
        }

        $descuadres = [];
        foreach ($kits as $kit) {
            $requerida = round((float) ($kit->cantidad_requerida ?? 0), 2);
            // La cantidad preparada es opcional. Si no está registrada se
            // asume que se preparó la cantidad requerida.
            $preparada = $kit->cantidad_preparada === null
                ? $requerida
                : round((float) $kit->cantidad_preparada, 2);

            if (abs($preparada - $requerida) > 0.004) {
                $nombre = $kit->material?->nombre_base ?: ('Material #' . $kit->material_id);
                $descuadres[] = $nombre . ' (requerida ' . $requerida . ', preparada ' . $preparada . ')';
            }
        }

        if (!empty($descuadres)) {
            throw ValidationException::withMessages([
                'estado_op_id' => 'No se puede pasar a «En taller»: la cantidad preparada debe ser igual a la requerida — '
                    . implode(' | ', $descuadres),
            ]);
        }

        $motivo = 'Salida de materiales a taller — OP ' . ($orden->numero_op ?? $orden->id);
        $sinStock = [];
        $descontados = 0;

        foreach ($kits as $kit) {
            $cantidad = round((float) ($kit->cantidad_requerida ?? 0), 2);
            if ($cantidad <= 0) {
                continue;
            }

            $material = Material::whereKey($kit->material_id)->lockForUpdate()->first();
            if (!$material) {
                $sinStock[] = 'Material #' . $kit->material_id . ' no existe';
                continue;
            }

            $existencia = (float) ($material->existencia_actual ?? 0);
            if ($existencia < $cantidad) {
                $sinStock[] = ($material->nombre_base ?: 'Material #' . $material->id)
                    . ': se necesitan ' . $cantidad . ($material->unidad_inventario ? ' ' . $material->unidad_inventario : '')
                    . ', solo hay ' . $existencia;
                continue;
            }

            $nueva = round($existencia - $cantidad, 2);
            $material->update(['existencia_actual' => $nueva]);

            $this->registrarMovimientoExistencia($material, $existencia, $nueva, 'salida', $motivo);
            $descontados++;
        }

        if (!empty($sinStock)) {
            throw ValidationException::withMessages([
                'estado_op_id' => 'No se puede pasar a «En taller»: inventario insuficiente — '
                    . implode(' | ', $sinStock)
                    . '. Registra la compra desde la vista de Materiales.',
            ]);
        }

        return $descontados;
    }

    /**
     * Es la otra mitad del movimiento de inventario: lo que «En taller» sacó de
     * bodega vuelve a entrar como unidad producida cuando la OP se recibe.
     *
     * Al pasar a «Recibiendo» (o al cerrar) solo lo aprobado por el control de
     * calidad aumenta el inventario del objetivo de la OP: en un material
     * procesado sube `existencia_actual` y deja su MaterialMovimiento, y en un
     * producto sube la cantidad recibida de su talla y deja su ProductoMovimiento.
     *
     * Cada OP entra como diferencia contra lo que ella misma ya había metido,
     * así que reabrir la recepción y corregirla no duplica la cantidad.
     *
     * @param  array<int, array<string, mixed>>  $recepciones
     */
    private function incrementarProductos(OrdenProduccion $orden, array $recepciones): int {
        $identificador = $orden->numero_op ?? $orden->id;
        $entradas = 0;

        foreach ($recepciones as $fila) {
            $aprobada = max(0, (int) ($fila['cantidad_aprobada'] ?? 0));
            $distribucion = Distribucion_por_tallas::whereKey($fila['distribucion_id'] ?? null)
                ->lockForUpdate()
                ->first();
            if (!$distribucion) {
                continue;
            }

            // Material procesado: la fila de distribución es de la OP, así que
            // su cantidad recibida ya dice cuánto metió esta OP en bodega.
            if ($distribucion->material_id) {
                $diferencia = $aprobada - (int) ($distribucion->cantidad_recibida_buena ?? 0);
                if ($diferencia === 0) {
                    continue;
                }

                $material = Material::whereKey($distribucion->material_id)->lockForUpdate()->first();
                if ($material) {
                    $existencia = round((float) ($material->existencia_actual ?? 0), 2);
                    $nueva = round($existencia + $diferencia, 2);
                    $material->update(['existencia_actual' => $nueva]);

                    $this->registrarMovimientoExistencia(
                        $material,
                        $existencia,
                        $nueva,
                        $diferencia > 0 ? 'entrada' : 'salida',
                        ($diferencia > 0 ? 'Entrada de material producido' : 'Corrección de la recepción')
                            . ' — OP ' . $identificador
                    );
                }

                $distribucion->update([
                    'cantidad_recibida_buena' => $aprobada,
                    'cantidad_pendiente_reparacion' => max(0, (int) ($fila['cantidad_en_reparacion'] ?? 0)),
                ]);

                $entradas++;
                continue;
            }

            if (!$distribucion->producto_id) {
                continue;
            }

            // Producto: la fila de la talla la comparten todas las OPs que la
            // producen, así que lo que esta OP ya metió se mide contra su
            // propio histórico de movimientos, no contra la fila.
            $diferencia = $aprobada - ProductoMovimiento::unidadesDeOrden(
                (int) $distribucion->producto_id,
                (string) $distribucion->talla,
                (int) $orden->id
            );
            if ($diferencia === 0) {
                continue;
            }

            $anterior = (int) ($distribucion->cantidad_recibida_buena ?? 0);
            $nueva = max(0, $anterior + $diferencia);

            $distribucion->update(['cantidad_recibida_buena' => $nueva]);

            ProductoMovimiento::registrar(
                (int) $distribucion->producto_id,
                (string) $distribucion->talla,
                $anterior,
                $nueva,
                (int) $orden->id,
                ($diferencia > 0 ? 'Recepción de calidad' : 'Corrección de la recepción')
                    . ' — OP ' . $identificador
            );

            $entradas++;
        }

        return $entradas;
    }

    /**
     * Valida y normaliza las filas de recepción enviadas desde el modal.
     * Cada fila corresponde a una talla distribuida en la OP; la suma de las
     * tres cantidades de calidad debe ser igual a la cantidad esperada de esa
     * talla, y el producto debe pertenecer a la OP.
     *
     * Si se recibe una fecha de compromiso general, se aplica a todas las
     * tallas con pares en reparación para que compartan la misma fecha.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recepcionesDeCalidad(
        OrdenProduccion $orden,
        array $filas,
        ?string $fechaCompromisoGeneral = null
    ): array {
        $distribuciones = $orden->distribucionesPorTallas()->with('material')->get()->keyBy('id');
        if ($distribuciones->isEmpty()) {
            return [];
        }

        $productosOp = $orden->productos()->pluck('productos.id')->map(fn ($id) => (int) $id)->all();
        $normalizadas = [];
        $vistas = [];

        foreach ($filas as $fila) {
            $distribucion = $distribuciones->get((int) ($fila['distribucion_id'] ?? 0));
            if (!$distribucion) {
                throw ValidationException::withMessages([
                    'recepciones' => 'Una de las filas de recepción no pertenece a esta orden de producción.',
                ]);
            }

            // El objetivo sale de la distribución: producto en una OP normal,
            // material procesado en una OP de materiales.
            $materialId = (int) ($distribucion->material_id ?? 0) ?: null;
            $productoId = $materialId
                ? null
                : ((int) ($distribucion->producto_id ?? 0)
                    ?: (int) ($orden->producto_referencia_id ?? 0)
                    ?: (count($productosOp) === 1 ? (int) $productosOp[0] : 0));

            if (!$materialId && !empty($productosOp) && !in_array($productoId, $productosOp, true)) {
                throw ValidationException::withMessages([
                    'recepciones' => "La talla {$distribucion->talla} no tiene un producto asociado en la OP.",
                ]);
            }

            $etiqueta = $materialId
                ? ('Material ' . ($distribucion->material?->nombre_base ?? '#' . $materialId))
                : ('Talla ' . $distribucion->talla);

            $aprobada = (int) ($fila['cantidad_aprobada'] ?? 0);
            $reparacion = (int) ($fila['cantidad_en_reparacion'] ?? 0);
            $noRecuperable = (int) ($fila['cantidad_no_recuperable'] ?? 0);
            // Una sola fecha de compromiso para todo lo que queda en reparación.
            $fechaCompromiso = $fechaCompromisoGeneral ?: ($fila['fecha_compromiso'] ?? null);
            $suma = $aprobada + $reparacion + $noRecuperable;
            $esperada = (int) $distribucion->cantidad_planificada;

            if ($suma > $esperada) {
                throw ValidationException::withMessages([
                    'recepciones' => "{$etiqueta}: la suma ({$suma}) supera la cantidad esperada ({$esperada}).",
                ]);
            }

            // Lo que se va a reparación necesita una fecha de compromiso pendiente.
            if ($reparacion > 0 && empty($fechaCompromiso)) {
                throw ValidationException::withMessages([
                    'recepciones' => "{$etiqueta}: indique la fecha de compromiso pendiente para los {$reparacion} par(es) en reparación.",
                ]);
            }

            $clave = ($materialId ? 'material:' . $materialId : 'producto:' . $productoId) . '|' . $distribucion->talla;
            if (isset($vistas[$clave])) {
                throw ValidationException::withMessages([
                    'recepciones' => "{$etiqueta} está repetido en la recepción.",
                ]);
            }
            $vistas[$clave] = true;

            $normalizadas[$distribucion->id] = [
                'distribucion_id' => $distribucion->id,
                'producto_id' => $productoId ?: null,
                'material_id' => $materialId,
                'talla' => $distribucion->talla,
                'cantidad_esperada' => $esperada,
                'cantidad_recibida' => $suma,
                'cantidad_aprobada' => $aprobada,
                'cantidad_en_reparacion' => $reparacion,
                'cantidad_no_recuperable' => $noRecuperable,
                'fecha_compromiso' => $fechaCompromiso ?: null,
            ];
        }

        foreach ($distribuciones as $distribucion) {
            if (!isset($normalizadas[$distribucion->id])) {
                $etiqueta = $distribucion->material_id
                    ? ('el material ' . ($distribucion->material?->nombre_base ?? '#' . $distribucion->material_id))
                    : ('la talla ' . $distribucion->talla);
                throw ValidationException::withMessages([
                    'recepciones' => "Falta registrar la recepción de {$etiqueta}.",
                ]);
            }
        }

        return array_values($normalizadas);
    }

    /**
     * La recepción está completa cuando cada talla registró exactamente lo
     * esperado y no quedó ningún par en reparación: ya no hay nada pendiente
     * del taller, así que la OP se puede cerrar.
     */
    private function recepcionCompleta(array $recepciones): bool {
        if (empty($recepciones)) {
            return false;
        }

        foreach ($recepciones as $fila) {
            if ((int) ($fila['cantidad_en_reparacion'] ?? 0) > 0) {
                return false;
            }

            if ((int) ($fila['cantidad_recibida'] ?? 0) !== (int) ($fila['cantidad_esperada'] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lleva los compromisos de reparación de la OP: cada talla que queda en
     * reparación abre un episodio con su fecha de compromiso pendiente y cada
     * episodio nuevo (o con fecha cambiada) deja dos avisos programados.
     * Cuando la talla se queda sin pares en reparación, su episodio abierto
     * se marca como atendido.
     *
     * @return int cantidad de compromisos avisados
     */
    private function registrarCompromisosReparacion(OrdenProduccion $orden, array $recepciones): int {
        $avisos = 0;

        foreach ($recepciones as $fila) {
            $enReparacion = (int) ($fila['cantidad_en_reparacion'] ?? 0);
            $productoId = $fila['producto_id'] ? (int) $fila['producto_id'] : null;
            $materialId = !empty($fila['material_id']) ? (int) $fila['material_id'] : null;
            $compromiso = CompromisoReparacion::abiertoPorTalla(
                (int) $orden->id,
                $fila['talla'],
                $productoId,
                $materialId
            );

            if ($enReparacion === 0) {
                $compromiso?->update(['atendido' => true]);
                continue;
            }

            // En un borrador puede no haberse indicado todavía la fecha. No se
            // crea un compromiso sin fecha; si ya existe, se conserva la suya y
            // solo se actualiza la cantidad pendiente.
            if (empty($fila['fecha_compromiso'])) {
                $compromiso?->update(['cantidad_en_reparacion' => $enReparacion]);
                continue;
            }

            $nuevo = $compromiso === null;
            $cambioFecha = ! $nuevo
                && $compromiso->fecha_compromiso?->toDateString() !== (string) $fila['fecha_compromiso'];

            $datos = [
                'orden_produccion_id'    => $orden->id,
                'producto_referencia_id' => $productoId,
                'material_referencia_id' => $materialId,
                'talla'                  => $fila['talla'],
                'cantidad_en_reparacion' => $enReparacion,
                'fecha_compromiso'       => $fila['fecha_compromiso'],
                'atendido'               => false,
            ];

            if ($nuevo) {
                $compromiso = CompromisoReparacion::create($datos);
            } else {
                $compromiso->update($datos);
            }

            if ($nuevo || $cambioFecha) {
                $this->avisarCompromisoReparacion($orden, $compromiso);
                $avisos++;
            }
        }

        return $avisos;
    }

    /**
     * Un aviso dos días antes del compromiso y otro el día del compromiso.
     */
    private function avisarCompromisoReparacion(OrdenProduccion $orden, CompromisoReparacion $compromiso): void {
        if (! $compromiso->fecha_compromiso) {
            return;
        }

        $identificador = $orden->numero_op ?? ('OP #' . $orden->id);
        $detalle = $compromiso->cantidad_en_reparacion . ' par(es) en reparación'
            . ($compromiso->talla ? ' (talla ' . $compromiso->talla . ')' : '');
        $vencimiento = $compromiso->fecha_compromiso->toDateString();
        $recordatorio = $compromiso->fecha_compromiso->copy()->subDays(2)->toDateString();

        $notificaciones = app(\Alejo\Notificaciones\NotificacionesManager::class);
        $notificaciones->crear(
            $identificador . ': faltan 2 días para el compromiso de reparación — ' . $detalle
                . '. Fecha comprometida: ' . $vencimiento . '.',
            'media',
            $orden->user_id,
            $recordatorio
        );
        $notificaciones->crear(
            $identificador . ': vence hoy el compromiso de reparación — ' . $detalle . '.',
            'alta',
            $orden->user_id,
            $vencimiento
        );
    }

    public function syncDistribucion(Request $request, $id): RedirectResponse {
        $permissions = Myhelp::EscribirEnLog($this, ' Begin SYNC-DISTRIBUCION:OrdenProduccions');
        DB::beginTransaction();

        $OrdenProduccion = OrdenProduccion::findOrFail($id);
        $this->sincronizarTallas($OrdenProduccion, $request->input('distribucion_tallas', []), false);

        DB::commit();
        $this->invalidarCache();
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;
        Myhelp::EscribirEnLog($this, 'SYNC-DISTRIBUCION:OrdenProduccions EXITOSO', 'OrdenProduccion id:' . $OrdenProduccion->id . ' | ' . $identificador, false);
        return back()->with('success', __('app.label.updated_successfully2', ['nombre' => $identificador]));
    }

    /**
     * Una OP de materiales no maneja tallas: crea una fila de distribución por
     * material procesado con su cantidad. Esa fila es la que usa la recepción
     * de calidad para registrar lo aprobado, en reparación o no recuperable.
     *
     * @param  array<int, array{cantidad: int}>  $materialesPivot
     */
    private function sincronizarDistribucionMateriales(OrdenProduccion $orden, array $materialesPivot): void
    {
        $existentes = $orden->distribucionesPorTallas()
            ->whereNotNull('material_id')
            ->get()
            ->groupBy('material_id');

        foreach ($materialesPivot as $materialId => $fila) {
            $distribucion = $existentes->get($materialId)?->shift()
                ?? new Distribucion_por_tallas(['material_id' => $materialId]);

            $distribucion->fill([
                'producto_id' => null,
                'material_id' => $materialId,
                'talla' => null,
                'cantidad_planificada' => (int) $fila['cantidad'],
            ]);
            $distribucion->save();

            $orden->distribucionesPorTallas()->syncWithoutDetaching([$distribucion->id]);
        }

        // Si un material dejó de formar parte de la OP, su fila se elimina solo
        // cuando todavía no tiene recepción ni compromiso asociado.
        foreach ($existentes as $materialId => $sobrantes) {
            if (array_key_exists($materialId, $materialesPivot)) {
                continue;
            }

            foreach ($sobrantes as $distribucion) {
                $tieneHistorial = $distribucion->cantidad_recibida_buena !== null
                    || $distribucion->cantidad_pendiente_reparacion !== null
                    || RecepcionCalidad::where('orden_produccion_id', $orden->id)
                        ->where('material_referencia_id', $materialId)
                        ->exists()
                    || CompromisoReparacion::where('orden_produccion_id', $orden->id)
                        ->where('material_referencia_id', $materialId)
                        ->exists();

                if ($tieneHistorial) {
                    continue;
                }

                $orden->distribucionesPorTallas()->detach($distribucion->id);
                $distribucion->delete();
            }
        }
    }

    /**
     * Sincroniza las tallas de una orden vía pivote: actualiza las que traen id,
     * crea+vincula las nuevas y desvincula (sin borrar) las que ya no vienen.
     * Con $conPlanificada en true también actualiza cantidad_planificada.
     */
    private function sincronizarTallas(OrdenProduccion $orden, array $tallas, bool $conPlanificada, $grupoNuevo = null): array {
        $conservados = [];
        $aEnteroONulo = function ($valor) {
            if ($valor === '' || $valor === null) {
                return null;
            }
            return (int) $valor;
        };
        foreach ($tallas as $talla) {
            if (!empty($talla['id'])) {
                $distribucion = Distribucion_por_tallas::find($talla['id']);
                if ($distribucion) {
                    $datos = [
                        'talla' => $talla['talla'] ?? $distribucion->talla,
                        'cantidad_recibida_buena' => $aEnteroONulo($talla['cantidad_recibida_buena'] ?? null),
                        'cantidad_pendiente_reparacion' => $aEnteroONulo($talla['cantidad_pendiente_reparacion'] ?? null),
                    ];
                    if ($conPlanificada && array_key_exists('cantidad_planificada', $talla)) {
                        $datos['cantidad_planificada'] = (int) ($talla['cantidad_planificada'] ?? 0);
                    }
                    if (empty($distribucion->producto_id) && !empty($talla['producto_id'])) {
                        $datos['producto_id'] = (int) $talla['producto_id'];
                    }
                    if (empty($distribucion->material_id) && !empty($talla['material_id'])) {
                        $datos['material_id'] = (int) $talla['material_id'];
                    }
                    $recibidaAnterior = (int) ($distribucion->cantidad_recibida_buena ?? 0);
                    $distribucion->update($datos);
                    $this->registrarMovimientoDeTallaEditada(
                        $distribucion,
                        $recibidaAnterior,
                        (int) ($datos['cantidad_recibida_buena'] ?? 0),
                        $orden
                    );
                    $conservados[] = $distribucion->id;
                }
                continue;
            }
            if (empty($talla['talla']) && empty($talla['cantidad_planificada']) && empty($talla['cantidad_recibida_buena']) && empty($talla['cantidad_pendiente_reparacion'])) {
                continue;
            }
            $nueva = Distribucion_por_tallas::create([
                'grupo' => $grupoNuevo,
                'producto_id' => $talla['producto_id'] ?? null,
                'material_id' => $talla['material_id'] ?? null,
                'talla' => $talla['talla'] ?? null,
                'cantidad_planificada' => (int) ($talla['cantidad_planificada'] ?? 0),
                'cantidad_recibida_buena' => $aEnteroONulo($talla['cantidad_recibida_buena'] ?? null),
                'cantidad_pendiente_reparacion' => $aEnteroONulo($talla['cantidad_pendiente_reparacion'] ?? null),
            ]);
            $this->registrarMovimientoDeTallaEditada(
                $nueva,
                0,
                (int) ($nueva->cantidad_recibida_buena ?? 0),
                $orden
            );
            $conservados[] = $nueva->id;
        }
        $orden->distribucionesPorTallas()->sync($conservados);
        return $conservados;
    }

    /**
     * El editor de distribución por tallas también puede digitar la cantidad
     * recibida de un producto: ese cambio queda en el histórico igual que el de
     * una recepción, atribuido a la OP que lo editó.
     */
    private function registrarMovimientoDeTallaEditada(
        Distribucion_por_tallas $distribucion,
        int $existenciaAnterior,
        int $existenciaNueva,
        OrdenProduccion $orden
    ): void {
        if (!$distribucion->producto_id || $existenciaAnterior === $existenciaNueva) {
            return;
        }

        ProductoMovimiento::registrar(
            (int) $distribucion->producto_id,
            (string) $distribucion->talla,
            $existenciaAnterior,
            $existenciaNueva,
            (int) $orden->id,
            'Ajuste de la distribución por tallas — OP ' . ($orden->numero_op ?? $orden->id)
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */

    public function destroy($OrdenProduccionid) {
        Myhelp::EscribirEnLog($this, 'DELETE:OrdenProduccions');

        $OrdenProduccion = OrdenProduccion::findOrFail($OrdenProduccionid);
        $identificador = $OrdenProduccion->numero_op ?? $OrdenProduccion->id;

        if (RecepcionCalidad::where('orden_produccion_id', $OrdenProduccion->id)->exists()) {
            return back()->with('error', "No es posible borrar la OP {$identificador} porque tiene recepciones asociadas.");
        }

        // Las filas del kit tienen llave foránea a la OP: se eliminan primero
        // para que la OP pueda borrarse.
        KitProceso::where('orden_produccion_id', $OrdenProduccion->id)->delete();
        $OrdenProduccion->delete();

        $this->invalidarCache();
        Myhelp::EscribirEnLog($this, 'DELETE:OrdenProduccions', 'OrdenProduccion id:' . $OrdenProduccionid . ' | ' . $identificador . ' borrado', false);
        return back()->with('success', __('app.label.deleted_successfully', ['name' => $identificador]));
    }

    public function destroyBulk(Request $request) {
        $ids = (array) $request->input('id', []);

        // Las OPs con recepciones asociadas tienen FK RESTRICT: se excluyen del
        // borrado masivo en lugar de tumbar la operación completa.
        $conRecepciones = RecepcionCalidad::whereIn('orden_produccion_id', $ids)
            ->distinct()
            ->pluck('orden_produccion_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $deletables = array_values(array_diff(array_map('intval', $ids), $conRecepciones));

        if (!$deletables) {
            return back()->with('error', 'No es posible borrar las OPs seleccionadas porque tienen recepciones asociadas.');
        }

        KitProceso::whereIn('orden_produccion_id', $deletables)->delete();
        OrdenProduccion::whereIn('id', $deletables)->delete();

        $this->invalidarCache();

        $nombre = count($deletables) . ' ' . __('app.label.user');
        if ($conRecepciones) {
            return back()->with(
                'warning',
                'Se borraron ' . $nombre . ' orden(es). No es posible borrar ' . count($conRecepciones)
                    . ' OP(s) porque tienen recepciones asociadas.'
            );
        }

        return back()->with('success', __('app.label.deleted_successfully', ['name' => $nombre]));
    }
    //FIN : STORE - UPDATE - DELETE

}
