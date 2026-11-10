<?php

namespace Tests\Unit;

use App\Models\ListaDesplegable;
use App\Models\Proveedor;
use App\Models\Tercero;
use App\Repositories\ListaDesplegableRepository;
use App\Repositories\ProveedorRepository;
use App\Services\ListaDesplegableService;
use App\Services\ProveedorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProveedorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_proveedor_via_service(): void
    {
        $service = $this->crearServicio();
        $proveedor = $service->crear([
            'codigo' => 'PRV-100',
            'nit' => '800555666-2',
            'nombre_comercial' => 'Botones del Valle',
            'razon_social' => 'Botones del Valle Ltda',
            'tipo_proveedor' => 'Materia prima',
            'forma_pago' => 'Crédito 15 días',
            'estado' => 'Activo',
        ]);

        $this->assertInstanceOf(Tercero::class, $proveedor);
        $this->assertEquals('Botones del Valle', $proveedor->nombre_comercial);
        $this->assertEquals('Botones del Valle', $proveedor->nombre);
        $this->assertDatabaseHas('proveedores_maco', ['id' => $proveedor->id]);
    }

    public function test_it_updates_a_proveedor_via_service(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre_comercial' => 'Antes']);

        $service = $this->crearServicio();
        $actualizado = $service->actualizar($proveedor->id, ['nombre_comercial' => 'Despues']);

        $this->assertEquals('Despues', $actualizado->nombre_comercial);
        $this->assertDatabaseHas('proveedores_maco', [
            'id' => $proveedor->id,
            'nombre_comercial' => 'Despues',
        ]);
    }

    public function test_it_deletes_a_proveedor_via_service(): void
    {
        $proveedor = Proveedor::factory()->create();

        $service = $this->crearServicio();
        $eliminado = $service->eliminar($proveedor->id);

        $this->assertTrue($eliminado);
        $this->assertDatabaseMissing('proveedores_maco', ['id' => $proveedor->id]);
    }

    public function test_it_returns_select_options(): void
    {
        ListaDesplegable::create([
            'nombre' => 'Bogotá',
            'categoria' => 'ciudad',
            'subcategoria' => 'general',
        ]);
        ListaDesplegable::create([
            'nombre' => 'Ciudad no general',
            'categoria' => 'ciudad',
            'subcategoria' => 'otra',
        ]);

        // Se resuelve desde el contenedor para comprobar también que la
        // dependencia de listas se inyecta correctamente.
        $opciones = app(ProveedorService::class)->obtenerOpcionesSelect();

        $this->assertArrayHasKey('tipo_proveedor', $opciones);
        $this->assertArrayHasKey('estado', $opciones);
        $this->assertArrayHasKey('forma_pago', $opciones);
        $this->assertContains('Materia prima', $opciones['tipo_proveedor']);
        $this->assertContains('Activo', $opciones['estado']);
        $this->assertContains('Contado', $opciones['forma_pago']);
        $this->assertSame(['Bogotá'], $opciones['ciudad']);
    }

    private function crearServicio(): ProveedorService
    {
        return new ProveedorService(
            new ProveedorRepository,
            new ListaDesplegableService(new ListaDesplegableRepository),
        );
    }
}
