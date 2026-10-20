<?php

namespace App\Services;

use App\Models\FichaTecnica;
use App\Models\Material;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Una ficha técnica agrupa los materiales que consume un producto.
 * Este servicio concentra las opciones del formulario y el guardado, para que
 * el alta se pueda hacer tanto desde la ficha como desde "Nuevo producto".
 */
class FichaTecnicaService
{
    public function __construct(
        protected ListaDesplegableService $listaDesplegableService
    ) {}

    /**
     * Opciones para las líneas de material (material + unidad).
     */
    public function opcionesMateriales(): array
    {
        $materiales = Material::orderBy('nombre_base')
            ->get(['id', 'codigo_material', 'nombre_base', 'unidad_inventario'])
            ->map(fn ($m) => [
                'id'    => $m->id,
                'value' => $m->id,
                'name'  => trim(($m->codigo_material ? $m->codigo_material . ' — ' : '') . $m->nombre_base) ?: ('Material #' . $m->id),
                'unidad_inventario' => $m->unidad_inventario,
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
     * Opciones completas del formulario de ficha (materiales, unidades y productos).
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

        return $this->opcionesMateriales() + [
            'productos'           => $productos,
            'productos_sin_ficha' => $productosSinFicha,
        ];
    }

    /**
     * Crea o actualiza la ficha con sus materiales y productos.
     * Un producto solo puede pertenecer a una ficha (ficha única por producto).
     *
     * @param  array{nombre: string, productos?: array, materiales: array}  $datos
     */
    public function guardar(array $datos, ?FichaTecnica $ficha = null): FichaTecnica
    {
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

        // El costo real no se captura por ahora: al editar se conserva el valor previo.
        $costosRealesPrevios = $ficha
            ? $ficha->materiales()->pluck('costo_asociado_real', 'material_componente_id')
            : collect();

        if (!$ficha) {
            $ficha = FichaTecnica::create(['nombre' => $datos['nombre']]);
        } else {
            $ficha->update(['nombre' => $datos['nombre']]);
        }

        $ficha->materiales()->delete();
        foreach ($datos['materiales'] as $fila) {
            $materialId = (int) $fila['material_componente_id'];
            $ficha->materiales()->create([
                'material_componente_id'   => $materialId,
                'consumo_estandar'         => $fila['consumo_estandar'],
                'unidad_medida'            => $fila['unidad_medida'] ?? null,
                'costo_asociado_teorico'   => $fila['costo_asociado_teorico'] ?? 0,
                'costo_asociado_real'      => array_key_exists('costo_asociado_real', $fila)
                    ? $fila['costo_asociado_real']
                    : ($costosRealesPrevios[$materialId] ?? null),
            ]);
        }

        $ficha->productos()->sync($productoIds);

        return $ficha->refresh();
    }
}
