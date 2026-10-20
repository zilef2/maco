<?php

namespace Tests\Feature;

use App\Models\EstadoOpMaterial;
use App\Models\EstadoOperacion;
use App\Models\FichaTecnica;
use App\Models\KitProceso;
use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KitProcesoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Al guardar una OP se genera una fila de kit por cada material de la
     * ficha técnica, usando la cantidad real del producto.
     */
    public function test_la_op_genera_un_kit_por_material_de_la_ficha(): void
    {
        [$user, $taller, $estadoMaterial, $producto] = $this->contextoBasico();

        $material = Material::create([
            'codigo_material' => 'MAT-001',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela de prueba',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
        ]);

        $ficha = FichaTecnica::create(['nombre' => 'Ficha de prueba']);
        $ficha->productos()->attach($producto->id);
        $ficha->materiales()->create([
            'material_componente_id' => $material->id,
            'consumo_estandar' => 2,
            'unidad_medida' => 'm',
        ]);

        $response = $this->actingAs($user)->post(route('OrdenProduccion.store'), [
            'productos' => [$producto->id],
            'productos_cantidad' => [$producto->id => 5],
            'cantidad_planificada' => 5,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'user_id' => $user->id,
            'estado_op_material_id' => $estadoMaterial->id,
        ]);

        $response->assertSessionHasNoErrors();

        $op = OrdenProduccion::firstOrFail();
        $kit = KitProceso::firstOrFail();

        $this->assertSame($op->id, $kit->orden_produccion_id);
        $this->assertSame($material->id, $kit->material_id);
        $this->assertNull($kit->estado_requerido);
        $this->assertNull($kit->proveedor_id);
        $this->assertSame('m', $kit->unidad);
        $this->assertEquals(10, (float) $kit->cantidad_requerida);
    }

    /**
     * El index agrupa las variantes de un mismo estado. En el ejemplo,
     * "Terminado" y "Terminada" cuentan como un solo estado.
     */
    public function test_el_index_agrupa_los_estados_requeridos(): void
    {
        [$user, $taller, $estadoMaterial, $producto] = $this->contextoBasico();

        $this->actingAs($user)->post(route('OrdenProduccion.store'), [
            'productos' => [$producto->id],
            'cantidad_planificada' => 5,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'user_id' => $user->id,
            'estado_op_material_id' => $estadoMaterial->id,
        ])->assertSessionHasNoErrors();

        $op = OrdenProduccion::firstOrFail();

        $estados = ['Materia prima', 'Terminado', 'Materia prima', 'Terminada', 'Cortada + intervenida'];
        foreach ($estados as $indice => $estado) {
            $material = Material::create([
                'codigo_material' => 'MAT-' . str_pad((string) ($indice + 1), 3, '0', STR_PAD_LEFT),
                'categoria' => 'Prueba',
                'nombre_base' => 'Material ' . ($indice + 1),
                'unidad_inventario' => 'u',
                'estado' => 'Activo',
            ]);

            KitProceso::create([
                'orden_produccion_id' => $op->id,
                'material_id' => $material->id,
                'estado_requerido' => $estado,
                'unidad' => 'u',
                'cantidad_requerida' => 1,
            ]);
        }

        $response = $this->actingAs($user)->get(route('KitProceso.index', [
            'orden_produccion_id' => $op->id,
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('KitProceso/Index')
            ->where('resumen.registros', 5)
            ->where('resumen.estados', function ($estadosResumen) {
                $conteo = collect($estadosResumen)
                    ->mapWithKeys(fn ($estado) => [$estado['label'] => $estado['count']])
                    ->all();

                return $conteo === [
                    'Materia prima' => 2,
                    'Terminado' => 2,
                    'Cortada + intervenida' => 1,
                ];
            }));
    }

    /**
     * Guardar los kits de una OP calcula la disponibilidad desde el material,
     * el faltante como disponible - preparada y el listo sin preguntarlo.
     */
    public function test_actualizar_kits_de_la_op_calcula_falta_y_listo(): void
    {
        [$user, $taller, $estadoMaterial, $producto] = $this->contextoBasico();

        $this->actingAs($user)->post(route('OrdenProduccion.store'), [
            'productos' => [$producto->id],
            'cantidad_planificada' => 1,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'user_id' => $user->id,
            'estado_op_material_id' => $estadoMaterial->id,
        ])->assertSessionHasNoErrors();

        $op = OrdenProduccion::firstOrFail();

        $material = Material::create([
            'codigo_material' => 'MAT-KIT-001',
            'categoria' => 'Prueba',
            'nombre_base' => 'Material del kit',
            'unidad_inventario' => 'u',
            'estado' => 'Activo',
            'existencia_actual' => 12,
        ]);

        $kit = KitProceso::create([
            'orden_produccion_id' => $op->id,
            'material_id' => $material->id,
            'unidad' => 'u',
            'cantidad_requerida' => 10,
        ]);

        $proveedor = Proveedor::create([
            'nombre' => 'Proveedor de prueba',
            'categoria' => 'Prueba',
        ]);

        $this->actingAs($user)->put(route('KitProceso.update-por-orden', $op->id), [
            'filas' => [[
                'id' => $kit->id,
                'estado_requerido' => 'Materia prima',
                'proveedor_id' => $proveedor->id,
                'cantidad_preparada' => 10,
            ]],
        ])->assertSessionHasNoErrors();

        $kit->refresh();

        $this->assertSame(12.0, (float) $kit->cantidad_disponible);
        $this->assertSame(2.0, (float) $kit->falta);
        $this->assertTrue($kit->listo);

        $this->actingAs($user)->put(route('KitProceso.update-por-orden', $op->id), [
            'filas' => [[
                'id' => $kit->id,
                'estado_requerido' => 'Materia prima',
                'proveedor_id' => $proveedor->id,
                'cantidad_preparada' => 5,
            ]],
        ])->assertSessionHasNoErrors();

        $kit->refresh();

        $this->assertSame(7.0, (float) $kit->falta);
        $this->assertFalse($kit->listo);
    }

    private function contextoBasico(): array
    {
        $rol = new Role();
        $rol->name = 'admin';
        $rol->guard_name = 'web';
        $rol->save();

        $user = User::factory()->create();
        $user->assignRole($rol);

        EstadoOperacion::create(['nombre' => 'Preparación']);
        $estadoMaterial = EstadoOpMaterial::create(['nombre' => 'Pendiente', 'valor' => 'pendiente']);
        $taller = Taller::create(['nombre' => 'Taller de prueba', 'direccion' => 'Calle 1 # 2-3']);

        $producto = Producto::create([
            'Codigo_interno' => 'TEST-KIT-001',
            'ID_producto_Shopify' => 'shopify-kit-001',
            'Categoria_original' => 'Prueba',
            'Familia_normalizada' => 'Prueba',
            'Titulo_Shopify' => 'Producto de prueba del kit',
            'Control_de_variante' => 'Única',
            'Opciones' => 'Única',
            'Estado' => 'Activo',
        ]);

        return [$user, $taller, $estadoMaterial, $producto];
    }
}
