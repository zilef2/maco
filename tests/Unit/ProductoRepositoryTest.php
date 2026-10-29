<?php

namespace Tests\Unit;

use App\Models\Producto;
use App\Repositories\ProductoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_products_by_ids(): void
    {
        $producto1 = Producto::factory()->create();
        $producto2 = Producto::factory()->create();

        $repository = new ProductoRepository();
        $resultado = $repository->findByIds([$producto1->id, $producto2->id]);

        $this->assertCount(2, $resultado);
    }

    public function test_it_deletes_products_by_ids(): void
    {
        $producto = Producto::factory()->create();

        $repository = new ProductoRepository();
        $eliminados = $repository->deleteByIds([$producto->id]);

        $this->assertEquals(1, $eliminados);
        $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
    }

    public function test_it_returns_empty_when_product_has_no_dependencies(): void
    {
        $producto = Producto::factory()->create();

        $repository = new ProductoRepository();
        $dependencias = $repository->obtenerDependencias($producto->id);

        $this->assertIsArray($dependencias);
        $this->assertEmpty($dependencias);
    }
}
