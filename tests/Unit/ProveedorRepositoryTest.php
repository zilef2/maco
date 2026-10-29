<?php

namespace Tests\Unit;

use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProveedorRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_proveedor_successfully(): void
    {
        $repository = new ProveedorRepository();
        $proveedor = $repository->create([
            'codigo_proveedor' => 'PRV-001',
            'nit' => '900123456-1',
            'nombre_comercial' => 'Textiles El Triunfo',
            'razon_social' => 'Textiles El Triunfo S.A.S.',
            'tipo_proveedor' => 'Materia prima',
            'forma_pago' => 'Contado',
            'estado' => 'Activo',
            'clasificacion' => 'proveedor',
        ]);

        $this->assertInstanceOf(Proveedor::class, $proveedor);
        $this->assertDatabaseHas('proveedors', [
            'id' => $proveedor->id,
            'nombre_comercial' => 'Textiles El Triunfo',
            'tipo_proveedor' => 'Materia prima',
        ]);
    }

    public function test_it_paginates_and_filters_proveedores(): void
    {
        Proveedor::factory()->create(['nombre_comercial' => 'Hilados Especiales', 'estado' => 'Activo']);
        Proveedor::factory()->create(['nombre_comercial' => 'Cierres Rápidos', 'estado' => 'Inactivo']);

        $repository = new ProveedorRepository();
        $resultado = $repository->paginate(perPage: 10, search: 'Hilados');

        $this->assertCount(1, $resultado->items());
        $this->assertEquals('Hilados Especiales', $resultado->items()[0]->nombre_comercial);
    }

    public function test_it_updates_a_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre_comercial' => 'Original']);

        $repository = new ProveedorRepository();
        $repository->update($proveedor, ['nombre_comercial' => 'Modificado']);

        $this->assertDatabaseHas('proveedors', [
            'id' => $proveedor->id,
            'nombre_comercial' => 'Modificado',
        ]);
    }

    public function test_it_deletes_a_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create();

        $repository = new ProveedorRepository();
        $eliminado = $repository->delete($proveedor);

        $this->assertTrue($eliminado);
        $this->assertDatabaseMissing('proveedors', ['id' => $proveedor->id]);
    }

    public function test_it_deletes_bulk_proveedores(): void
    {
        $p1 = Proveedor::factory()->create();
        $p2 = Proveedor::factory()->create();

        $repository = new ProveedorRepository();
        $count = $repository->deleteBulk([$p1->id, $p2->id]);

        $this->assertEquals(2, $count);
        $this->assertDatabaseMissing('proveedors', ['id' => $p1->id]);
        $this->assertDatabaseMissing('proveedors', ['id' => $p2->id]);
    }
}

