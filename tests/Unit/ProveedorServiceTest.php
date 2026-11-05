<?php

namespace Tests\Unit;

use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use App\Services\ProveedorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProveedorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_proveedor_via_service(): void
    {
        $service = new ProveedorService(new ProveedorRepository());
        $proveedor = $service->crear([
            'codigo_proveedor' => 'PRV-100',
            'nit' => '800555666-2',
            'nombre_comercial' => 'Botones del Valle',
            'razon_social' => 'Botones del Valle Ltda',
            'tipo_proveedor' => 'Materia prima',
            'forma_pago' => 'Crédito 15 días',
            'estado' => 'Activo',
        ]);

        $this->assertInstanceOf(Proveedor::class, $proveedor);
        $this->assertEquals('Botones del Valle', $proveedor->nombre_comercial);
        $this->assertEquals('Botones del Valle', $proveedor->nombre);
        $this->assertDatabaseHas('proveedors', ['id' => $proveedor->id]);
    }

    public function test_it_updates_a_proveedor_via_service(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre_comercial' => 'Antes']);

        $service = new ProveedorService(new ProveedorRepository());
        $actualizado = $service->actualizar($proveedor->id, ['nombre_comercial' => 'Despues']);

        $this->assertEquals('Despues', $actualizado->nombre_comercial);
        $this->assertDatabaseHas('proveedors', [
            'id' => $proveedor->id,
            'nombre_comercial' => 'Despues',
        ]);
    }

    public function test_it_deletes_a_proveedor_via_service(): void
    {
        $proveedor = Proveedor::factory()->create();

        $service = new ProveedorService(new ProveedorRepository());
        $eliminado = $service->eliminar($proveedor->id);

        $this->assertTrue($eliminado);
        $this->assertDatabaseMissing('proveedors', ['id' => $proveedor->id]);
    }

    public function test_it_returns_select_options(): void
    {
        $service = new ProveedorService(new ProveedorRepository());
        $opciones = $service->obtenerOpcionesSelect();

        $this->assertArrayHasKey('tipo_proveedor', $opciones);
        $this->assertArrayHasKey('estado', $opciones);
        $this->assertArrayHasKey('forma_pago', $opciones);
        $this->assertContains('Materia prima', $opciones['tipo_proveedor']);
        $this->assertContains('Activo', $opciones['estado']);
        $this->assertContains('Contado', $opciones['forma_pago']);
    }
}

