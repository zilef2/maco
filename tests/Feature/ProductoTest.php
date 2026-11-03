<?php

namespace Tests\Feature;

use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ContextoMateriales;
use Tests\TestCase;

class ProductoTest extends TestCase
{
    use ContextoMateriales;
    use RefreshDatabase;

    /**
     * Crear un producto desde el formulario debe guardar sus tallas como
     * distribución (con cantidad inicial 0) y su ficha técnica con el costo
     * unitario vigente del material componente.
     */
    public function test_se_puede_crear_un_producto_con_tallas_y_ficha_tecnica(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-PROD-COMP', 12);

        $response = $this->actingAs($user)->post(route('Producto.store'), [
            'Codigo_interno' => 'PROD-TEST-001',
            'Titulo_Shopify' => 'Producto de prueba',
            'ID_producto_Shopify' => 'shop-prod-001',
            'Categoria_original' => 'Prueba',
            'Familia_normalizada' => 'Prueba',
            'Control_de_variante' => 'Talla',
            'Opciones' => 'S,M',
            'Estado' => 1,
            'precio_venta_producto' => 100,
            'tallas' => ['S', 'M'],
            'materiales' => [[
                'material_componente_id' => $componente->id,
                'consumo_estandar' => 2,
                'unidad_medida' => 'm',
                'costo_asociado_teorico' => 0,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $producto = Producto::where('Codigo_interno', 'PROD-TEST-001')->firstOrFail();
        $this->assertSame('Producto de prueba', $producto->Titulo_Shopify);
        $this->assertSame(2, $producto->distribucionesPorTallas()->count());
        $this->assertEqualsCanonicalizing(
            ['S', 'M'],
            $producto->distribucionesPorTallas()->pluck('talla')->all()
        );
        $this->assertSame(0, (int) $producto->distribucionesPorTallas()->sum('cantidad_planificada'));

        $ficha = $producto->fichaTecnica()->first();
        $this->assertNotNull($ficha);
        $this->assertSame('Producto de prueba', $ficha->nombre);
        $this->assertDatabaseHas('ficha_tecnica_producto', [
            'ficha_tecnica_id' => $ficha->id,
            'producto_id' => $producto->id,
        ]);
        $this->assertDatabaseHas('ficha_tecnica_materiales', [
            'ficha_tecnica_id' => $ficha->id,
            'material_componente_id' => $componente->id,
            'consumo_estandar' => 2,
            'costo_asociado_teorico' => 12,
        ]);
    }
}
