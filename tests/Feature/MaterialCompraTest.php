<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialMovimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialCompraTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioAdmin(): User
    {
        $rol = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    public function test_registra_la_compra_de_varios_materiales_en_el_historial(): void
    {
        $user = $this->usuarioAdmin();

        $tela = Material::create([
            'codigo_material' => 'MAT-COMPRA-001',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela comprada',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 2,
        ]);

        $botones = Material::create([
            'codigo_material' => 'MAT-COMPRA-002',
            'categoria' => 'Insumos',
            'nombre_base' => 'Botones comprados',
            'unidad_inventario' => 'und',
            'estado' => 'Activo',
            'existencia_actual' => 5,
        ]);

        $response = $this->actingAs($user)->post(route('Material.compra'), [
            'materiales' => [
                ['material_id' => $tela->id, 'cantidad' => 3.5, 'precio' => 1200],
                ['material_id' => $tela->id, 'cantidad' => 1.5, 'precio' => 1200],
                ['material_id' => $botones->id, 'cantidad' => 2, 'precio' => 50],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame(7.0, (float) $tela->fresh()->existencia_actual);
        $this->assertSame(7.0, (float) $botones->fresh()->existencia_actual);

        $entradaTela = MaterialMovimiento::where('material_id', $tela->id)->firstOrFail();
        $this->assertSame('entrada', $entradaTela->tipo_movimiento);
        $this->assertSame(5.0, (float) $entradaTela->cantidad);
        $this->assertSame(2.0, (float) $entradaTela->existencia_anterior);
        $this->assertSame(7.0, (float) $entradaTela->existencia_nueva);
        $this->assertSame('Compra de material', $entradaTela->motivo);
        $this->assertSame($user->id, (int) $entradaTela->user_id);

        $entradaBotones = MaterialMovimiento::where('material_id', $botones->id)->firstOrFail();
        $this->assertSame('entrada', $entradaBotones->tipo_movimiento);
        $this->assertSame(2.0, (float) $entradaBotones->cantidad);
        $this->assertSame(5.0, (float) $entradaBotones->existencia_anterior);
        $this->assertSame(7.0, (float) $entradaBotones->existencia_nueva);
        $this->assertSame($user->id, (int) $entradaBotones->user_id);
    }

    public function test_rechaza_una_compra_con_cantidad_invalida_y_no_modifica_existencias(): void
    {
        $user = $this->usuarioAdmin();

        $material = Material::create([
            'codigo_material' => 'MAT-COMPRA-003',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela sin compra',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 5,
        ]);

        $response = $this->actingAs($user)->post(route('Material.compra'), [
            'materiales' => [
                ['material_id' => $material->id, 'cantidad' => 0],
            ],
        ]);

        $response->assertSessionHasErrors('materiales.0.cantidad');
        $this->assertSame(5.0, (float) $material->fresh()->existencia_actual);
        $this->assertDatabaseCount('material_movimientos', 0);
    }
}
