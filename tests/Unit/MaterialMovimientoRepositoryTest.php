<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\MaterialMovimiento;
use App\Repositories\MaterialMovimientoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialMovimientoRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_gets_last_purchase_for_a_material(): void
    {
        $material = Material::factory()->create();

        MaterialMovimiento::create([
            'material_id' => $material->id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => 15,
            'existencia_anterior' => 0,
            'existencia_nueva' => 15,
            'motivo' => 'Compra de material',
        ]);

        MaterialMovimiento::create([
            'material_id' => $material->id,
            'tipo_movimiento' => 'salida',
            'cantidad' => 5,
            'existencia_anterior' => 15,
            'existencia_nueva' => 10,
            'motivo' => 'Salida a taller',
        ]);

        MaterialMovimiento::create([
            'material_id' => $material->id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => 45.5,
            'existencia_anterior' => 10,
            'existencia_nueva' => 55.5,
            'motivo' => 'Compra de material',
        ]);

        $repo = new MaterialMovimientoRepository();
        $ultima = $repo->obtenerUltimaCompraPorMaterial($material->id);

        $this->assertNotNull($ultima);
        $this->assertEquals(45.5, (float) $ultima->cantidad);
    }

    public function test_it_gets_grouped_last_purchases_for_multiple_materials(): void
    {
        $mat1 = Material::factory()->create();
        $mat2 = Material::factory()->create();

        MaterialMovimiento::create([
            'material_id' => $mat1->id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => 10,
            'existencia_anterior' => 0,
            'existencia_nueva' => 10,
            'motivo' => 'Compra de material',
        ]);

        MaterialMovimiento::create([
            'material_id' => $mat2->id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => 25.5,
            'existencia_anterior' => 0,
            'existencia_nueva' => 25.5,
            'motivo' => 'Compra de material',
        ]);

        $repo = new MaterialMovimientoRepository();
        $agrupadas = $repo->obtenerUltimasComprasAgrupadas()->keyBy('material_id');

        $this->assertTrue($agrupadas->has($mat1->id));
        $this->assertTrue($agrupadas->has($mat2->id));
        $this->assertEquals(10, (float) $agrupadas->get($mat1->id)->cantidad);
        $this->assertEquals(25.5, (float) $agrupadas->get($mat2->id)->cantidad);
    }
}
