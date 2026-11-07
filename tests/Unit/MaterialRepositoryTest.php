<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Repositories\MaterialRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_material_via_repository(): void
    {
        $repository = new MaterialRepository();
        $material = $repository->create([
            'codigo_material'       => 'TEX-LFI-1',
            'codigo_categoria'      => 'TEX',
            'categoria'             => 'Textil',
            'codigo_base'           => 'LFI',
            'nombre_base'           => 'Lana fibrada',
            'unidad_inventario'     => 'Metros',
            'estado'                => 'Activo',
            'modelo_diseno_acabado' => 'Rasurado',
            'color'                 => 'Rojo, Azul',
            'talla_tamano'          => 'S, M',
        ]);

        $this->assertInstanceOf(Material::class, $material);
        $this->assertEquals('TEX-LFI-1', $material->codigo_material);
        $this->assertEquals('Rasurado', $material->modelo_diseno_acabado);
        $this->assertEquals('Rojo, Azul', $material->color);
        $this->assertEquals('S, M', $material->talla_tamano);
        $this->assertDatabaseHas('materials', [
            'id'                    => $material->id,
            'modelo_diseno_acabado' => 'Rasurado',
            'color'                 => 'Rojo, Azul',
            'talla_tamano'          => 'S, M',
        ]);
    }

    public function test_it_updates_a_material_via_repository(): void
    {
        $repository = new MaterialRepository();
        $material = $repository->create([
            'codigo_material'       => 'TEX-LFI-1',
            'codigo_categoria'      => 'TEX',
            'categoria'             => 'Textil',
            'codigo_base'           => 'LFI',
            'nombre_base'           => 'Lana fibrada',
            'unidad_inventario'     => 'Metros',
            'estado'                => 'Activo',
            'modelo_diseno_acabado' => 'Rasurado',
            'color'                 => 'Rojo',
            'talla_tamano'          => 'S',
        ]);

        $repository->update($material, [
            'modelo_diseno_acabado' => 'Estampado',
            'color'                 => 'Verde, Negro',
            'talla_tamano'          => 'L, XL',
        ]);

        $material->refresh();
        $this->assertEquals('Estampado', $material->modelo_diseno_acabado);
        $this->assertEquals('Verde, Negro', $material->color);
        $this->assertEquals('L, XL', $material->talla_tamano);
    }

    public function test_it_deletes_a_material_via_repository(): void
    {
        $repository = new MaterialRepository();
        $material = $repository->create([
            'codigo_material'   => 'TEX-LFI-1',
            'codigo_categoria'  => 'TEX',
            'categoria'         => 'Textil',
            'codigo_base'       => 'LFI',
            'nombre_base'       => 'Lana fibrada',
            'unidad_inventario' => 'Metros',
            'estado'            => 'Activo',
        ]);

        $id = $material->id;
        $this->assertTrue($repository->delete($material));
        $this->assertDatabaseMissing('materials', ['id' => $id]);
    }
}

