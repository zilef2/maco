<?php

namespace App\Services;

use App\Models\FichaTecnica;
use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Una ficha técnica agrupa los materiales que consume un producto o un
 * material procesado. Este servicio concentra las opciones del formulario y el
 * guardado, para que el alta se pueda hacer desde la ficha, desde "Nuevo
 * producto" o desde "Nuevo material".
 */
class FichaTecnicaService
{
    /** Estados de OP que todavía permiten editar la ficha del producto. */
    public const ESTADOS_FICHA_EDITABLE = ['Borrador', 'Preparación', 'Cerrada'];

    public function __construct(
        protected ListaDesplegableService $listaDesplegableService,
        protected CostoMaterialOpService $costosMateriales
    ) {}

    /**
     * Opciones para las líneas de material (material, unidad y costo vigente).
     */
    public function opcionesMateriales(): array
    {
        $materiales = Material::with('lastPrice')
            ->orderBy('nombre_base')
            ->get(['id', 'codigo_material', 'nombre_base', 'unidad_inventario'])
            ->map(fn ($m) => [
                'id'    => $m->id,
                'value' => $m->id,
                'name'  => trim(($m->codigo_material ? $m->codigo_material . ' — ' : '') . $m->nombre_base) ?: ('Material #' . $m->id),
                'unidad_inventario' => $m->unidad_inventario,
                'unidad_medida' => $m->unidad_inventario,
                'costo_unitario' => $m->costoUnitarioActual(),
                'precio_registrado' => $m->lastPrice !== null,
            ])->values()->all();

        $opcionesMaterial = $this->listaDesplegableService->getOptionsByCategoriaGrouped('material');
        $unidadesMedida = array_map(fn ($item) => [
            'id'    => $item['nombre'],
            'value' => $item['nombre'],
            'name'  => $item['nombre'],
            'label' => $item['nombre'],
        ], $opcionesMaterial['unidad_inventario'] ?? []);

        return [
            'materiales'    => $materiales,
            'unidad_medida' => $unidadesMedida,
        ];
    }

    /**
     * Opciones completas del formulario de ficha: materiales componentes,
     * unidades, productos y materiales procesados que se pueden fabricar.
     */
    public function opcionesFicha(): array
    {
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

        $materialesProcesados = Material::query()
            ->where('material_procesado', true)
            ->orderBy('nombre_base')
            ->get(['id', 'codigo_material', 'nombre_base'])
            ->map(fn ($m) => $this->opcionMaterialProcesado($m))
            ->values()
            ->all();

        $materialesConFicha = DB::table('ficha_tecnica_material')->pluck('material_id')->all();
        $materialesSinFicha = array_values(array_filter(
            $materialesProcesados,
            fn ($m) => !in_array($m['id'], $materialesConFicha, true)
        ));

        return $this->opcionesMateriales() + [
            'productos'                      => $productos,
            'productos_sin_ficha'            => $productosSinFicha,
            'materiales_procesados'          => $materialesProcesados,
            'materiales_procesados_sin_ficha' => $materialesSinFicha,
        ];
    }

    private function opcionMaterialProcesado(Material $material): array
    {
        $nombre = trim(($material->codigo_material ? $material->codigo_material . ' — ' : '') . $material->nombre_base)
            ?: ('Material #' . $material->id);

        return [
            'id'    => $material->id,
            'value' => $material->id,
            'name'  => $nombre,
            'label' => $nombre,
            'title' => $nombre,
        ];
    }

    /**
     * OPs que impiden editar la ficha de un producto: cualquier OP activa
     * (fuera de Borrador, Preparación o Cerrada).
     *
     * @return array<string, string> número de OP => estado
     */
    public function operacionesQueBloquean(int $productoId): array
    {
        $producto = Producto::find($productoId);
        if (!$producto) {
            return [];
        }

        return $producto->ordenesProduccion()
            ->with('estadoOp:id,nombre')
            ->get()
            ->reject(fn ($op) => in_array($op->estadoOp?->nombre, self::ESTADOS_FICHA_EDITABLE, true))
            ->mapWithKeys(fn ($op) => [
                ($op->numero_op ?? $op->id) => ($op->estadoOp?->nombre ?? 'sin estado'),
            ])
            ->all();
    }

    /**
     * Mapa producto_id => [número de OP => estado] con las OPs que bloquean
     * la ficha. Se usa en el listado para no consultar producto por producto.
     *
     * @return array<int, array<string, string>>
     */
    public function bloqueosPorProducto(): array
    {
        $bloqueos = [];

        OrdenProduccion::with(['productos:id', 'estadoOp:id,nombre'])
            ->get()
            ->each(function ($op) use (&$bloqueos) {
                if (in_array($op->estadoOp?->nombre, self::ESTADOS_FICHA_EDITABLE, true)) {
                    return;
                }

                foreach ($op->productos as $producto) {
                    $bloqueos[$producto->id][$op->numero_op ?? $op->id] = $op->estadoOp?->nombre ?? 'sin estado';
                }
            });

        return $bloqueos;
    }

    /**
     * OPs que impiden editar la ficha de un material procesado.
     *
     * @return array<string, string> número de OP => estado
     */
    public function operacionesQueBloqueanMaterial(int $materialId): array
    {
        $material = Material::find($materialId);
        if (!$material) {
            return [];
        }

        return $material->ordenesProduccion()
            ->with('estadoOp:id,nombre')
            ->get()
            ->reject(fn ($op) => in_array($op->estadoOp?->nombre, self::ESTADOS_FICHA_EDITABLE, true))
            ->mapWithKeys(fn ($op) => [
                ($op->numero_op ?? $op->id) => ($op->estadoOp?->nombre ?? 'sin estado'),
            ])
            ->all();
    }

    /**
     * Mapa material_id => [número de OP => estado] con las OPs que bloquean
     * la ficha de un material procesado.
     *
     * @return array<int, array<string, string>>
     */
    public function bloqueosPorMaterial(): array
    {
        $bloqueos = [];

        OrdenProduccion::with(['materiales:id', 'estadoOp:id,nombre'])
            ->get()
            ->each(function ($op) use (&$bloqueos) {
                if (in_array($op->estadoOp?->nombre, self::ESTADOS_FICHA_EDITABLE, true)) {
                    return;
                }

                foreach ($op->materiales as $material) {
                    $bloqueos[$material->id][$op->numero_op ?? $op->id] = $op->estadoOp?->nombre ?? 'sin estado';
                }
            });

        return $bloqueos;
    }

    /**
     * Crea o actualiza la ficha con sus materiales, productos y materiales
     * procesados. Un producto o material procesado solo puede pertenecer a una
     * ficha. No se puede modificar si el objetivo está en una OP activa.
     *
     * @param  array{nombre: string, productos?: array, materiales_procesados?: array, materiales: array}  $datos
     */
    public function guardar(array $datos, ?FichaTecnica $ficha = null): FichaTecnica
    {
        $productoIds = array_values(array_unique(array_map('intval', $datos['productos'] ?? [])));
        $materialProcesadoIds = array_values(array_unique(array_map('intval', $datos['materiales_procesados'] ?? [])));

        $bloqueos = [];
        foreach ($productoIds as $productoId) {
            $bloqueos += $this->operacionesQueBloquean($productoId);
        }
        foreach ($materialProcesadoIds as $materialId) {
            $bloqueos += $this->operacionesQueBloqueanMaterial($materialId);
        }
        if (!empty($bloqueos)) {
            $detalle = collect($bloqueos)
                ->map(fn ($estado, $numeroOp) => $numeroOp . ' (' . $estado . ')')
                ->implode(', ');
            throw ValidationException::withMessages([
                'materiales_procesados' => 'La ficha técnica no se puede modificar: el objetivo está en la OP ' . $detalle
                    . '. Solo se permite en Borrador, Preparación o Cerrada.',
            ]);
        }

        $enOtraFicha = FichaTecnica::productosConFicha($productoIds, $ficha?->id);
        if (!empty($enOtraFicha)) {
            $nombres = Producto::whereIn('id', $enOtraFicha)->get()
                ->map(fn ($p) => $p->Titulo_Shopify ?: ($p->Codigo_interno ?: 'Producto #' . $p->id))
                ->implode(', ');
            throw ValidationException::withMessages([
                'productos' => __('app.label.ficha_ya_asignada') . ': ' . $nombres,
            ]);
        }

        if (!empty($materialProcesadoIds)) {
            $materialesValidos = Material::whereIn('id', $materialProcesadoIds)
                ->where('material_procesado', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $noProcesados = array_values(array_diff($materialProcesadoIds, $materialesValidos));
            if (!empty($noProcesados)) {
                throw ValidationException::withMessages([
                    'materiales_procesados' => 'Solo los materiales con proceso pueden tener ficha técnica.',
                ]);
            }

            $enOtraFichaMaterial = FichaTecnica::materialesProcesadosConFicha($materialProcesadoIds, $ficha?->id);
            if (!empty($enOtraFichaMaterial)) {
                $nombres = Material::whereIn('id', $enOtraFichaMaterial)->get()
                    ->map(fn ($m) => trim(($m->codigo_material ? $m->codigo_material . ' — ' : '') . $m->nombre_base))
                    ->implode(', ');
                throw ValidationException::withMessages([
                    'materiales_procesados' => 'Estos materiales ya tienen una ficha técnica: ' . $nombres,
                ]);
            }
        }

        // El costo real no se captura por ahora: al editar se conserva el valor previo.
        $costosRealesPrevios = $ficha
            ? $ficha->materiales()->pluck('costo_asociado_real', 'material_componente_id')
            : collect();

        // Unidad y costo unitario son datos del material, no de la ficha.
        $materialesPorId = Material::with('lastPrice')
            ->whereIn(
                'id',
                collect($datos['materiales'])
                    ->pluck('material_componente_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
            )
            ->get()
            ->keyBy('id');

        if (!$ficha) {
            $ficha = FichaTecnica::create(['nombre' => $datos['nombre']]);
        } else {
            $ficha->update(['nombre' => $datos['nombre']]);
        }

        $ficha->materiales()->delete();
        foreach ($datos['materiales'] as $fila) {
            $materialId = (int) $fila['material_componente_id'];
            $material = $materialesPorId->get($materialId);

            $ficha->materiales()->create([
                'material_componente_id'   => $materialId,
                'consumo_estandar'         => $fila['consumo_estandar'],
                // La ficha conserva una copia para el histórico; el origen es el material.
                'unidad_medida'            => $material?->unidad_inventario ?? ($fila['unidad_medida'] ?? null),
                'costo_asociado_teorico'   => $material
                    ? $material->costoUnitarioActual()
                    : ($fila['costo_asociado_teorico'] ?? 0),
                'costo_asociado_real'      => array_key_exists('costo_asociado_real', $fila)
                    ? $fila['costo_asociado_real']
                    : ($costosRealesPrevios[$materialId] ?? null),
            ]);
        }

        // Solo se sincronizan las relaciones que el formulario envió: así el
        // alta desde Producto no desvincula materiales procesados y viceversa.
        if (array_key_exists('productos', $datos)) {
            $ficha->productos()->sync($productoIds);
        }
        if (array_key_exists('materiales_procesados', $datos)) {
            $ficha->materialesProcesados()->sync($materialProcesadoIds);
        }

        // Las OPs abiertas que usan estos objetivos deben costearse con la ficha
        // nueva: su precio guardado y su historial de cambios se renuevan.
        $this->costosMateriales->recalcularPorProductos($productoIds);
        $this->costosMateriales->recalcularPorMateriales($materialProcesadoIds);

        return $ficha->refresh();
    }
}
