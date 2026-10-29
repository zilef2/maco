<?php

namespace Tests\Unit;

use App\Models\Producto;
use App\Repositories\ProductoRepository;
use App\Services\ProductoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_products_without_dependencies(): void
    {
        $producto = Producto::factory()->create();

        $service = new ProductoService(new ProductoRepository());
        $resultado = $service->eliminarMasivo([$producto->id]);

        $this->assertEquals(1, $resultado['eliminados']);
        $this->assertEmpty($resultado['bloqueados']);
        $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
    }

    public function test_it_blocks_deletion_of_products_with_dependencies(): void
    {
        $producto = Producto::factory()->create();

        $repositoryMock = $this->createMock(ProductoRepository::class);
        $repositoryMock->method('findByIds')
            ->willReturn(new \Illuminate\Database\Eloquent\Collection([$producto]));
        $repositoryMock->method('obtenerDependencias')
            ->willReturn(['1 recepción de calidad vinculada']);
        $repositoryMock->expects($this->never())
            ->method('deleteByIds');

        $service = new ProductoService($repositoryMock);
        $resultado = $service->eliminarMasivo([$producto->id]);

        $this->assertEquals(0, $resultado['eliminados']);
        $this->assertCount(1, $resultado['bloqueados']);
        $this->assertEquals($producto->id, $resultado['bloqueados'][0]['id']);
        $this->assertContains('1 recepción de calidad vinculada', $resultado['bloqueados'][0]['motivos']);
    }
}
