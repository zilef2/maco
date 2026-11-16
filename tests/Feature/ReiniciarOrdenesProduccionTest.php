<?php

namespace Tests\Feature;

use App\Models\EstadoOperacion;
use App\Models\FichaTecnica;
use App\Models\KitProceso;
use App\Models\Material;
use App\Models\Procesoop;
use App\Models\Producto;
use App\Models\Tercero;
use App\Models\User;
use App\Repositories\OrdenProduccionPruebaRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReiniciarOrdenesProduccionTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_ops_de_prueba_incluyen_producto_materiales_y_procesos(): void
    {
        $estado = EstadoOperacion::create(['nombre' => 'Preparación']);
        User::factory()->create();
        Tercero::create(['tipo' => 'Taller', 'nombre' => 'Taller de prueba']);

        $material = Material::create([
            'codigo_material' => 'TEL-ALG-1',
            'categoria' => 'Tela',
            'nombre_base' => 'Algodón',
            'unidad_inventario' => 'Metros',
            'estado' => 'Activo',
            'existencia_actual' => 50,
        ]);

        $producto = Producto::create([
            'Codigo_interno' => 'PRUEBA-001',
            'ID_producto_Shopify' => 'gid://shopify/Product/PRUEBA-001',
            'Categoria_original' => 'Calzado',
            'Familia_normalizada' => 'Deportivo',
            'Titulo_Shopify' => 'Producto de prueba',
            'Control_de_variante' => 'Única',
            'Opciones' => 'Única',
            'Estado' => 'Activo',
        ]);

        $ficha = FichaTecnica::create(['nombre' => 'Ficha de prueba']);
        $ficha->productos()->sync([$producto->id]);
        $ficha->materiales()->create([
            'material_componente_id' => $material->id,
            'consumo_estandar' => 2,
            'unidad_medida' => 'Metros',
        ]);

        $proceso = Procesoop::create(['nombre' => 'Corte']);
        $producto->procesos()->attach($proceso->id, ['coste_proceso' => 500]);

        $creadas = app(OrdenProduccionPruebaRepository::class)->generarOrdenes($estado->id, 2);

        $this->assertCount(2, $creadas);

        foreach ($creadas as $op) {
            $op->refresh();

            $this->assertSame($producto->id, (int) $op->producto_referencia_id);
            $this->assertSame([$producto->id], $op->productos()->pluck('productos.id')->all());
            $this->assertGreaterThan(0, (int) $op->distribucionesPorTallas()->sum('cantidad_planificada'));
            $this->assertSame(10, (int) $op->cantidad_planificada);

            $kit = KitProceso::where('orden_produccion_id', $op->id)
                ->where('material_id', $material->id)
                ->first();

            $this->assertNotNull($kit);
            $this->assertSame(20.0, (float) $kit->cantidad_requerida);
            $this->assertSame(50.0, (float) $kit->cantidad_disponible);

            // 500 de proceso × 10 unidades planificadas.
            $this->assertSame(5000.0, (float) $op->costo_proceso);
            $this->assertDatabaseHas('orden_produccion_costos_materiales', [
                'orden_produccion_id' => $op->id,
                'material_id' => $material->id,
            ]);
        }
    }
}
