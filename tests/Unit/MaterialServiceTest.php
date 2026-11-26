<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\MaterialVariant;
use App\Repositories\MaterialRepository;
use App\Services\MaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_material_with_variants_and_columns_via_service(): void
    {
        $service = new MaterialService(new MaterialRepository());
        $material = $service->crear(
            [
                'codigo_material'       => 'TEX-LFI-1',
                'codigo_categoria'      => 'TEX',
                'categoria'             => 'Textil',
                'codigo_base'           => 'LFI',
                'generico'              => false,
                'nombre_base'           => 'Lana fibrada',
                'unidad_inventario'     => 'Metros',
                'estado'                => 'Activo',
                'modelo_diseno_acabado' => 'Liso',
            ],
            ['Negro', 'Blanco'],
            ['M', 'L']
        );

        $this->assertInstanceOf(Material::class, $material);
        $this->assertEquals('Liso', $material->modelo_diseno_acabado);
        $this->assertEquals('Negro, Blanco', $material->color);
        $this->assertEquals('M, L', $material->talla_tamano);
        $this->assertTrue((bool) $material->maneja_color);
        $this->assertTrue((bool) $material->maneja_otra_variante);

        $this->assertDatabaseHas('material_variants', [
            'material_id' => $material->id,
            'nombre'      => 'Negro',
            'tipo'        => MaterialVariant::TIPO_COLOR,
        ]);
        $this->assertDatabaseHas('material_variants', [
            'material_id' => $material->id,
            'nombre'      => 'M',
            'tipo'        => MaterialVariant::TIPO_VARIANTE,
        ]);
    }

    public function test_it_updates_material_and_syncs_variants_via_service(): void
    {
        $service = new MaterialService(new MaterialRepository());
        $material = $service->crear(
            [
                'codigo_material'       => 'TEX-LFI-1',
                'codigo_categoria'      => 'TEX',
                'categoria'             => 'Textil',
                'codigo_base'           => 'LFI',
                'generico'              => false,
                'nombre_base'           => 'Lana fibrada',
                'unidad_inventario'     => 'Metros',
                'estado'                => 'Activo',
                'modelo_diseno_acabado' => 'Liso',
            ],
            ['Azul'],
            ['S']
        );

        $actualizado = $service->actualizar(
            $material->id,
            [
                'modelo_diseno_acabado' => 'Estampado',
            ],
            ['Verde', 'Amarillo'],
            ['XL']
        );

        $this->assertEquals('Estampado', $actualizado->modelo_diseno_acabado);
        $this->assertEquals('Verde, Amarillo', $actualizado->color);
        $this->assertEquals('XL', $actualizado->talla_tamano);

        $this->assertDatabaseHas('material_variants', [
            'material_id' => $material->id,
            'nombre'      => 'Verde',
            'tipo'        => MaterialVariant::TIPO_COLOR,
        ]);
        $this->assertDatabaseMissing('material_variants', [
            'material_id' => $material->id,
            'nombre'      => 'Azul',
        ]);
    }
}

