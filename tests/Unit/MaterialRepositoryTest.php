<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Repositories\MaterialRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaterialRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function datosBase(array $extra = []): array
    {
        return array_merge([
            'codigo_material'       => 'TEX-LFI-1',
            'codigo_categoria'      => 'TEX',
            'categoria'             => 'Textil',
            'codigo_base'           => 'LFI',
            'generico'              => false,
            'nombre_base'           => 'Lana fibrada',
            'unidad_inventario'     => 'Metros',
            'estado'                => 'Activo',
        ], $extra);
    }

    public function test_it_creates_a_material_via_repository(): void
    {
        $repository = new MaterialRepository();
        $material = $repository->create($this->datosBase([
            'modelo_diseno_acabado' => 'Rasurado',
            'color'                 => 'Rojo, Azul',
            'talla_tamano'          => 'S, M',
        ]));

        $this->assertInstanceOf(Material::class, $material);
        // El código se canoniza: el número de la familia lleva tres dígitos.
        $this->assertEquals('TEX-LFI-001', $material->codigo_material);
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
        $material = $repository->create($this->datosBase([
            'modelo_diseno_acabado' => 'Rasurado',
            'color'                 => 'Rojo',
            'talla_tamano'          => 'S',
        ]));

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
        $material = $repository->create($this->datosBase());

        $id = $material->id;
        $this->assertTrue($repository->delete($material));
        $this->assertDatabaseMissing('materials', ['id' => $id]);
    }

    public function test_rechaza_un_codigo_con_un_numero_en_cero(): void
    {
        $repository = new MaterialRepository();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Ningún número del código puede valer 0');

        $repository->create($this->datosBase([
            'codigo_material' => 'COM-CAP-000',
        ]));
    }

    public function test_rechaza_un_codigo_que_ya_tiene_otro_material(): void
    {
        $repository = new MaterialRepository();
        $repository->create($this->datosBase());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ya lo tiene el material');

        $repository->create($this->datosBase());
    }
}
