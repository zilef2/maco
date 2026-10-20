<?php

namespace Tests\Feature;

use App\Models\EstadoOpMaterial;
use App\Models\EstadoOperacion;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdenProduccionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Comprueba que una OP nueva se guarda correctamente: número generado en
     * el servidor (OP-100), estado de materiales y marca de externa.
     */
    public function test_se_puede_guardar_una_nueva_op(): void
    {
        $rol = new Role();
        $rol->name = 'admin';
        $rol->guard_name = 'web';
        $rol->save();

        $user = User::factory()->create();
        $user->assignRole($rol);

        $estadoPreparacion = EstadoOperacion::create(['nombre' => 'Preparación']);
        $estadoMaterial = EstadoOpMaterial::create(['nombre' => 'Pendiente', 'valor' => 'pendiente']);
        $taller = Taller::create(['nombre' => 'Taller de prueba', 'direccion' => 'Calle 1 # 2-3']);

        $producto = Producto::create([
            'Codigo_interno' => 'TEST-001',
            'ID_producto_Shopify' => 'shopify-test-001',
            'Categoria_original' => 'Prueba',
            'Familia_normalizada' => 'Prueba',
            'Titulo_Shopify' => 'Producto de prueba',
            'Control_de_variante' => 'Única',
            'Opciones' => 'Única',
            'Estado' => 'Activo',
        ]);

        $response = $this->actingAs($user)->post(route('OrdenProduccion.store'), [
            'productos' => [$producto->id],
            'cantidad_planificada' => 10,
            'taller_id' => $taller->id,
            'user_id' => $user->id,
            'estado_op_material_id' => $estadoMaterial->id,
            'externa' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orden_produccions', 1);
        $this->assertDatabaseHas('orden_produccions', [
            'numero_op' => 'OP-100',
            'estado_op_id' => $estadoPreparacion->id,
            'estado_op_material_id' => $estadoMaterial->id,
            'externa' => 1,
        ]);
    }
}
