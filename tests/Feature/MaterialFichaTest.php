<?php

namespace Tests\Feature;

use App\Models\FichaTecnica;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\ContextoMateriales;
use Tests\TestCase;

class MaterialFichaTest extends TestCase
{
    use ContextoMateriales;
    use RefreshDatabase;

    public function test_crear_un_material_procesado_crea_su_ficha_y_sus_lineas(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-COMP-CREATE', 7);

        $response = $this->actingAs($user)->post(route('Material.store'), [
            'codigo_material' => 'MAT-PRO-CREATE',
            'categoria' => 'Semielaborado',
            'nombre_base' => 'Cuero procesado',
            'unidad_inventario' => 'u',
            'estado' => 'Activo',
            'material_procesado' => true,
            'last_purchase_price' => 15,
            'last_purchase_date' => now()->toDateString(),
            'materiales' => [[
                'material_componente_id' => $componente->id,
                'consumo_estandar' => 3,
                'unidad_medida' => 'm',
                'costo_asociado_teorico' => 0,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $material = Material::where('codigo_material', 'MAT-PRO-CREATE')->firstOrFail();
        $this->assertTrue((bool) $material->material_procesado);

        $ficha = $material->fichaTecnica()->first();
        $this->assertNotNull($ficha);
        $this->assertSame('MAT-PRO-CREATE — Cuero procesado', $ficha->nombre);
        $this->assertDatabaseHas('ficha_tecnica_material', [
            'ficha_tecnica_id' => $ficha->id,
            'material_id' => $material->id,
        ]);
        $this->assertDatabaseHas('ficha_tecnica_materiales', [
            'ficha_tecnica_id' => $ficha->id,
            'material_componente_id' => $componente->id,
            'consumo_estandar' => 3,
            'unidad_medida' => 'm',
            'costo_asociado_teorico' => 7,
        ]);
    }

    public function test_editar_un_material_procesado_reemplaza_las_lineas_de_su_ficha(): void
    {
        $user = $this->usuarioAdmin();
        $componenteA = $this->materialBase('MAT-COMP-A', 5);
        $componenteB = $this->materialBase('MAT-COMP-B', 9);
        $material = $this->materialProcesado('MAT-PRO-EDIT');
        $ficha = $this->fichaParaMaterial($material, [[$componenteA, 2]]);

        $response = $this->actingAs($user)->put(route('Material.update', $material->id), [
            'nombre_base' => 'Cuero procesado editado',
            'material_procesado' => true,
            'materiales' => [[
                'material_componente_id' => $componenteB->id,
                'consumo_estandar' => 4,
                'unidad_medida' => 'm',
                'costo_asociado_teorico' => 0,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $ficha->refresh();
        $this->assertSame(1, $ficha->materiales()->count());
        $this->assertDatabaseHas('ficha_tecnica_materiales', [
            'ficha_tecnica_id' => $ficha->id,
            'material_componente_id' => $componenteB->id,
            'consumo_estandar' => 4,
            'costo_asociado_teorico' => 9,
        ]);
        $this->assertDatabaseMissing('ficha_tecnica_materiales', [
            'ficha_tecnica_id' => $ficha->id,
            'material_componente_id' => $componenteA->id,
        ]);
        $this->assertDatabaseHas('ficha_tecnica_material', [
            'ficha_tecnica_id' => $ficha->id,
            'material_id' => $material->id,
        ]);
    }

    public function test_quitar_la_marca_de_procesado_elimina_la_ficha_sin_objetivos(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-COMP-OFF', 6);
        $material = $this->materialProcesado('MAT-PRO-OFF');
        $ficha = $this->fichaParaMaterial($material, [[$componente, 1]]);

        $response = $this->actingAs($user)->put(route('Material.update', $material->id), [
            'material_procesado' => false,
            'materiales' => [],
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertFalse((bool) $material->fresh()->material_procesado);
        $this->assertDatabaseMissing('ficha_tecnicas', ['id' => $ficha->id]);
        $this->assertDatabaseMissing('ficha_tecnica_material', [
            'ficha_tecnica_id' => $ficha->id,
            'material_id' => $material->id,
        ]);
    }

    public function test_no_se_puede_quitar_la_ficha_de_un_material_en_una_op_activa(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-COMP-BLOCK', 8);
        $material = $this->materialProcesado('MAT-PRO-BLOCK');
        $ficha = $this->fichaParaMaterial($material, [[$componente, 2]]);
        $estado = $this->estadoOperacion('En taller');
        $this->opQueProduce($material, $user, $estado);

        $response = $this->actingAs($user)->put(route('Material.update', $material->id), [
            'material_procesado' => false,
            'materiales' => [],
        ]);

        $response->assertSessionHasErrors('materiales');
        $this->assertTrue((bool) $material->fresh()->material_procesado);
        $this->assertDatabaseHas('ficha_tecnicas', ['id' => $ficha->id]);
        $this->assertDatabaseHas('ficha_tecnica_material', [
            'ficha_tecnica_id' => $ficha->id,
            'material_id' => $material->id,
        ]);
    }

    public function test_guardar_un_material_bloqueado_sin_cambiar_la_ficha_funciona(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-COMP-UNCHANGED', 8);
        $material = $this->materialProcesado('MAT-PRO-UNCHANGED');
        $ficha = $this->fichaParaMaterial($material, [[$componente, 2]]);
        $estado = $this->estadoOperacion('En taller');
        $this->opQueProduce($material, $user, $estado);

        $response = $this->actingAs($user)->put(route('Material.update', $material->id), [
            'nombre_base' => 'Nombre actualizado sin tocar ficha',
            'material_procesado' => true,
            'materiales' => [[
                'material_componente_id' => $componente->id,
                'consumo_estandar' => 2,
                'unidad_medida' => 'm',
                'costo_asociado_teorico' => 8,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame('Nombre actualizado sin tocar ficha', $material->fresh()->nombre_base);
        $this->assertDatabaseHas('ficha_tecnicas', ['id' => $ficha->id]);
        $this->assertDatabaseHas('ficha_tecnica_materiales', [
            'ficha_tecnica_id' => $ficha->id,
            'material_componente_id' => $componente->id,
            'consumo_estandar' => 2,
        ]);
    }

    public function test_el_index_de_materiales_expone_la_ficha_del_material_procesado(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-COMP-IDX', 4);
        $material = $this->materialProcesado('MAT-PRO-IDX');
        $ficha = $this->fichaParaMaterial($material, [[$componente, 2]], 'Ficha visible en index');

        $response = $this->actingAs($user)->get(route('Material.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Material/Index')
            ->where('materiales', function ($materiales) use ($material, $ficha, $componente) {
                $fila = collect($materiales)->firstWhere('id', $material->id);

                return $fila !== null
                    && (int) $fila['ficha_id'] === $ficha->id
                    && $fila['ficha_nombre'] === 'Ficha visible en index'
                    && $fila['ficha_bloqueada'] === false
                    && (int) $fila['ficha_materiales'][0]['material_componente_id'] === $componente->id
                    && (float) $fila['ficha_materiales'][0]['consumo_estandar'] === 2.0;
            }));
    }
}
