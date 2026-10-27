<?php

namespace Tests\Feature;

use App\Models\FichaTecnica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ContextoMateriales;
use Tests\TestCase;

class FichaTecnicaMaterialesTest extends TestCase
{
    use ContextoMateriales;
    use RefreshDatabase;

    public function test_una_ficha_puede_tener_un_material_procesado_como_objetivo(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-FICHA-COMP', 9);
        $material = $this->materialProcesado('MAT-FICHA-OBJ');

        $response = $this->actingAs($user)->post(route('FichaTecnicaMateriales.store'), [
            'nombre' => 'Ficha del cuero',
            'productos' => [],
            'materiales_procesados' => [$material->id],
            'materiales' => [[
                'material_componente_id' => $componente->id,
                'consumo_estandar' => 2.5,
                'unidad_medida' => 'm',
                'costo_asociado_teorico' => 999,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $ficha = FichaTecnica::where('nombre', 'Ficha del cuero')->firstOrFail();
        $this->assertDatabaseHas('ficha_tecnica_material', [
            'ficha_tecnica_id' => $ficha->id,
            'material_id' => $material->id,
        ]);
        $this->assertDatabaseHas('ficha_tecnica_materiales', [
            'ficha_tecnica_id' => $ficha->id,
            'material_componente_id' => $componente->id,
            'consumo_estandar' => 2.5,
            'unidad_medida' => 'm',
            'costo_asociado_teorico' => 9,
        ]);
    }

    public function test_no_se_puede_asignar_un_material_no_procesado_a_una_ficha(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-FICHA-COMP-REG', 3);
        $material = $this->materialBase('MAT-FICHA-REG');

        $response = $this->actingAs($user)->post(route('FichaTecnicaMateriales.store'), [
            'nombre' => 'Ficha inválida',
            'productos' => [],
            'materiales_procesados' => [$material->id],
            'materiales' => [[
                'material_componente_id' => $componente->id,
                'consumo_estandar' => 1,
                'unidad_medida' => 'm',
            ]],
        ]);

        $response->assertSessionHasErrors('materiales_procesados');
        $this->assertDatabaseMissing('ficha_tecnicas', ['nombre' => 'Ficha inválida']);
    }

    public function test_un_material_procesado_solo_puede_pertenecer_a_una_ficha(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-FICHA-COMP-DUP', 3);
        $material = $this->materialProcesado('MAT-FICHA-DUP');
        $this->fichaParaMaterial($material, [[$componente, 1]], 'Primera ficha');

        $response = $this->actingAs($user)->post(route('FichaTecnicaMateriales.store'), [
            'nombre' => 'Segunda ficha',
            'productos' => [],
            'materiales_procesados' => [$material->id],
            'materiales' => [[
                'material_componente_id' => $componente->id,
                'consumo_estandar' => 2,
                'unidad_medida' => 'm',
            ]],
        ]);

        $response->assertSessionHasErrors('materiales_procesados');
        $this->assertSame(1, FichaTecnica::count());
        $this->assertDatabaseMissing('ficha_tecnicas', ['nombre' => 'Segunda ficha']);
    }

    public function test_actualizar_una_ficha_sincroniza_sus_materiales_procesados(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-FICHA-COMP-SYNC', 6);
        $materialA = $this->materialProcesado('MAT-FICHA-A');
        $materialB = $this->materialProcesado('MAT-FICHA-B');
        $ficha = $this->fichaParaMaterial($materialA, [[$componente, 1]], 'Ficha sincronizable');

        $response = $this->actingAs($user)->put(route('FichaTecnicaMateriales.update', $ficha->id), [
            'nombre' => 'Ficha sincronizada',
            'productos' => [],
            'materiales_procesados' => [$materialB->id],
            'materiales' => [[
                'material_componente_id' => $componente->id,
                'consumo_estandar' => 5,
                'unidad_medida' => 'm',
                'costo_asociado_teorico' => 0,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $ficha->refresh();
        $this->assertSame('Ficha sincronizada', $ficha->nombre);
        $this->assertFalse($ficha->materialesProcesados()->whereKey($materialA->id)->exists());
        $this->assertTrue($ficha->materialesProcesados()->whereKey($materialB->id)->exists());
        $this->assertDatabaseHas('ficha_tecnica_materiales', [
            'ficha_tecnica_id' => $ficha->id,
            'material_componente_id' => $componente->id,
            'consumo_estandar' => 5,
            'costo_asociado_teorico' => 6,
        ]);
    }

    public function test_una_op_activa_bloquea_la_edicion_de_la_ficha(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-FICHA-COMP-BLOCK', 5);
        $material = $this->materialProcesado('MAT-FICHA-BLOCK');
        $ficha = $this->fichaParaMaterial($material, [[$componente, 2]], 'Ficha bloqueada');
        $estado = $this->estadoOperacion('En taller');
        $this->opQueProduce($material, $user, $estado);

        $response = $this->actingAs($user)->put(route('FichaTecnicaMateriales.update', $ficha->id), [
            'nombre' => 'Ficha alterada',
            'productos' => [],
            'materiales_procesados' => [$material->id],
            'materiales' => [[
                'material_componente_id' => $componente->id,
                'consumo_estandar' => 9,
                'unidad_medida' => 'm',
            ]],
        ]);

        $response->assertSessionHasErrors('materiales_procesados');
        $this->assertDatabaseHas('ficha_tecnicas', [
            'id' => $ficha->id,
            'nombre' => 'Ficha bloqueada',
        ]);
        $this->assertDatabaseHas('ficha_tecnica_materiales', [
            'ficha_tecnica_id' => $ficha->id,
            'material_componente_id' => $componente->id,
            'consumo_estandar' => 2,
        ]);
    }

    public function test_no_se_puede_quitar_de_la_ficha_un_material_con_op_activa(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-FICHA-COMP-REMOVE', 4);
        $materialActivo = $this->materialProcesado('MAT-FICHA-ACTIVO');
        $materialLibre = $this->materialProcesado('MAT-FICHA-LIBRE');
        $ficha = $this->fichaParaMaterial($materialActivo, [[$componente, 1]], 'Ficha compartida');
        $ficha->materialesProcesados()->attach($materialLibre->id);
        $estado = $this->estadoOperacion('En taller');
        $this->opQueProduce($materialActivo, $user, $estado);

        $response = $this->actingAs($user)->put(route('FichaTecnicaMateriales.update', $ficha->id), [
            'nombre' => 'Ficha compartida',
            'productos' => [],
            'materiales_procesados' => [$materialLibre->id],
            'materiales' => [[
                'material_componente_id' => $componente->id,
                'consumo_estandar' => 1,
                'unidad_medida' => 'm',
            ]],
        ]);

        $response->assertSessionHasErrors('materiales_procesados');
        $this->assertTrue($ficha->materialesProcesados()->whereKey($materialActivo->id)->exists());
        $this->assertTrue($ficha->materialesProcesados()->whereKey($materialLibre->id)->exists());
    }

    public function test_no_se_puede_eliminar_la_ficha_de_un_material_con_op_activa(): void
    {
        $user = $this->usuarioAdmin();
        $componente = $this->materialBase('MAT-FICHA-COMP-DELETE', 4);
        $material = $this->materialProcesado('MAT-FICHA-DELETE');
        $ficha = $this->fichaParaMaterial($material, [[$componente, 1]], 'Ficha no borrable');
        $estado = $this->estadoOperacion('En taller');
        $this->opQueProduce($material, $user, $estado);

        $response = $this->actingAs($user)->delete(route('FichaTecnicaMateriales.destroy', $ficha->id));

        $response->assertSessionHasErrors('ficha');
        $this->assertDatabaseHas('ficha_tecnicas', [
            'id' => $ficha->id,
            'nombre' => 'Ficha no borrable',
        ]);
        $this->assertDatabaseHas('ficha_tecnica_material', [
            'ficha_tecnica_id' => $ficha->id,
            'material_id' => $material->id,
        ]);
    }
}
