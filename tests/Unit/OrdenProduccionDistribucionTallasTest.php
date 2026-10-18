<?php

namespace Tests\Unit;

use App\Models\Distribucion_por_tallas;
use App\Models\EstadoOperacion;
use App\Models\OrdenProduccion;
use App\Models\Producto;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdenProduccionDistribucionTallasTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_product_with_its_tallas_distribution(): void
    {
        $producto = Producto::create([
            'Titulo_Shopify' => 'Camisa Lino Premium',
            'Codigo_interno' => 'CAM-001',
            'Opciones' => 'S, M, L',
        ]);

        $distribucionS = Distribucion_por_tallas::create([
            'talla' => 'S',
            'cantidad_planificada' => 15,
        ]);
        $distribucionM = Distribucion_por_tallas::create([
            'talla' => 'M',
            'cantidad_planificada' => 25,
        ]);

        $producto->distribucionesPorTallas()->attach([$distribucionS->id, $distribucionM->id]);

        $productoCargado = Producto::with('distribucionesPorTallas')->find($producto->id);

        $this->assertCount(2, $productoCargado->distribucionesPorTallas);
        $this->assertEquals('S', $productoCargado->distribucionesPorTallas->first()->talla);
        $this->assertEquals(15, $productoCargado->distribucionesPorTallas->first()->cantidad_planificada);
    }
}
