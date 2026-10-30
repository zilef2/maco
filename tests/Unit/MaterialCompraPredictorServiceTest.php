<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\MaterialMovimiento;
use App\Repositories\MaterialMovimientoRepository;
use App\Services\MaterialCompraPredictorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialCompraPredictorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_predicts_purchase_quantity_for_a_material(): void
    {
        $material = Material::factory()->create();

        MaterialMovimiento::create([
            'material_id' => $material->id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => 32.5,
            'existencia_anterior' => 0,
            'existencia_nueva' => 32.5,
            'motivo' => 'Compra de material',
        ]);

        $service = new MaterialCompraPredictorService(new MaterialMovimientoRepository());
        $prediccion = $service->predecirParaMaterial($material->id);

        $this->assertNotNull($prediccion);
        $this->assertEquals(32.5, $prediccion['cantidad']);
        $this->assertArrayHasKey('fecha', $prediccion);
        $this->assertArrayHasKey('fecha_humana', $prediccion);
    }

    public function test_it_returns_null_when_material_has_no_previous_purchases(): void
    {
        $material = Material::factory()->create();

        $service = new MaterialCompraPredictorService(new MaterialMovimientoRepository());
        $prediccion = $service->predecirParaMaterial($material->id);

        $this->assertNull($prediccion);
    }

    public function test_it_returns_predictions_map_for_materials(): void
    {
        $mat1 = Material::factory()->create();

        MaterialMovimiento::create([
            'material_id' => $mat1->id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => 12.0,
            'existencia_anterior' => 0,
            'existencia_nueva' => 12.0,
            'motivo' => 'Compra de material',
        ]);

        $service = new MaterialCompraPredictorService(new MaterialMovimientoRepository());
        $mapa = $service->obtenerPrediccionesParaMateriales();

        $this->assertArrayHasKey($mat1->id, $mapa);
        $this->assertEquals(12.0, $mapa[$mat1->id]['cantidad']);
    }
}
