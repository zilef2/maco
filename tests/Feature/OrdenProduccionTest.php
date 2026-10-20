<?php

namespace Tests\Feature;

use App\Models\Distribucion_por_tallas;
use App\Models\EmpresaExterna;
use App\Models\EstadoOpMaterial;
use App\Models\EstadoOperacion;
use App\Models\KitProceso;
use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\Producto;
use App\Models\RecepcionCalidad;
use App\Models\Role;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
        $empresaExterna = EmpresaExterna::create(['nombre' => 'Empresa de prueba', 'actividad' => 'Confección']);

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
            'tipo' => 'externa',
            'taller_id' => $taller->id,
            'empresa_externa_id' => $empresaExterna->id,
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

    /**
     * Al recibir una OP sin pares en reparación, la recepción se guarda y la
     * OP pasa automáticamente de "En taller" a "Pendiente".
     */
    public function test_al_recibir_sin_reparaciones_la_op_pasa_a_pendiente(): void
    {
        [$user, $op, $estados, $tallaS, $tallaM] = $this->contextoRecepcion('En taller');

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Recibiendo']->id,
            'recepciones' => [
                ['distribucion_id' => $tallaS->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
                ['distribucion_id' => $tallaM->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame($estados['Pendiente']->id, (int) $op->estado_op_id);
        $this->assertDatabaseCount('recepcion_calidads', 2);
        $this->assertDatabaseHas('recepcion_calidads', [
            'orden_produccion_id' => $op->id,
            'talla' => 'S',
            'cantidad_en_reparacion' => 0,
        ]);
    }

    /**
     * Si quedan pares en reparación, la OP permanece en "Recibiendo".
     */
    public function test_al_recibir_con_reparaciones_la_op_queda_en_recibiendo(): void
    {
        [$user, $op, $estados, $tallaS, $tallaM] = $this->contextoRecepcion('En taller');

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Recibiendo']->id,
            'recepciones' => [
                ['distribucion_id' => $tallaS->id, 'cantidad_aprobada' => 1, 'cantidad_en_reparacion' => 1, 'cantidad_no_recuperable' => 0],
                ['distribucion_id' => $tallaM->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame($estados['Recibiendo']->id, (int) $op->estado_op_id);
        $this->assertNotNull($op->recepcion_desde);
        $this->assertDatabaseHas('recepcion_calidads', [
            'orden_produccion_id' => $op->id,
            'talla' => 'S',
            'cantidad_en_reparacion' => 1,
        ]);
    }

    /**
     * Una OP que ya está en "Recibiendo" puede abrir la recepción, corregir
     * las cantidades y, si ya no queda nada en reparación, pasa a "Pendiente".
     */
    public function test_desde_recibiendo_actualiza_la_recepcion_y_pasa_a_pendiente(): void
    {
        [$user, $op, $estados, $tallaS, $tallaM] = $this->contextoRecepcion('Recibiendo');

        // Simula una entrada anterior a recepción: al pasar a Pendiente debe limpiarse.
        $op->forceFill(['recepcion_desde' => now()->subDay()])->save();

        RecepcionCalidad::create([
            'orden_produccion_id' => $op->id,
            'producto_referencia_id' => $op->producto_referencia_id,
            'talla' => 'S',
            'cantidad_esperada' => 2,
            'cantidad_recibida' => 2,
            'cantidad_aprobada' => 1,
            'cantidad_en_reparacion' => 1,
            'cantidad_no_recuperable' => 0,
            'fecha_recepcion' => now(),
        ]);
        RecepcionCalidad::create([
            'orden_produccion_id' => $op->id,
            'producto_referencia_id' => $op->producto_referencia_id,
            'talla' => 'M',
            'cantidad_esperada' => 2,
            'cantidad_recibida' => 2,
            'cantidad_aprobada' => 2,
            'cantidad_en_reparacion' => 0,
            'cantidad_no_recuperable' => 0,
            'fecha_recepcion' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Recibiendo']->id,
            'recepciones' => [
                ['distribucion_id' => $tallaS->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
                ['distribucion_id' => $tallaM->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame($estados['Pendiente']->id, (int) $op->estado_op_id);
        $this->assertNull($op->recepcion_desde);
        $this->assertDatabaseHas('recepcion_calidads', [
            'orden_produccion_id' => $op->id,
            'talla' => 'S',
            'cantidad_aprobada' => 2,
            'cantidad_en_reparacion' => 0,
        ]);
    }

    /**
     * El index entrega la recepción registrada para que el modal pueda
     * precargarla cuando la OP está en "Recibiendo".
     */
    public function test_el_index_entrega_las_recepciones_para_precargar_el_modal(): void
    {
        [$user, $op, $estados, $tallaS] = $this->contextoRecepcion('Recibiendo');

        $op->forceFill(['recepcion_desde' => now()])->save();

        RecepcionCalidad::create([
            'orden_produccion_id' => $op->id,
            'producto_referencia_id' => $op->producto_referencia_id,
            'talla' => 'S',
            'cantidad_esperada' => 2,
            'cantidad_recibida' => 2,
            'cantidad_aprobada' => 1,
            'cantidad_en_reparacion' => 1,
            'cantidad_no_recuperable' => 0,
            'fecha_recepcion' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('OrdenProduccion.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrdenProduccion/Index')
            ->where('ordenes.0.recepciones_detalle.0.talla', 'S')
            ->where('ordenes.0.recepciones_detalle.0.cantidad_en_reparacion', 1)
            ->whereNotNull('ordenes.0.recepcion_desde'));
    }

    /**
     * El index entrega los materiales totales (para "Preparación"), los
     * faltantes (para "Esperando materiales") y las tallas por producto.
     */
    public function test_el_index_entrega_materiales_y_tallas_para_el_detalle_del_estado(): void
    {
        [$user, $op] = $this->contextoRecepcion('Preparación');

        $material = Material::create([
            'codigo_material' => 'MAT-001',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela de prueba',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 3,
        ]);

        KitProceso::create([
            'orden_produccion_id' => $op->id,
            'material_id' => $material->id,
            'unidad' => 'm',
            'cantidad_requerida' => 5,
            'cantidad_disponible' => 3,
            'cantidad_preparada' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('OrdenProduccion.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrdenProduccion/Index')
            ->where('ordenes.0.materiales_detalle.0.nombre', 'MAT-001 — Tela de prueba')
            ->where('ordenes.0.materiales_detalle.0.requerida', fn ($valor) => (float) $valor === 5.0)
            ->where('ordenes.0.materiales_detalle.0.disponible', fn ($valor) => (float) $valor === 3.0)
            ->where('ordenes.0.materiales_faltantes.0.faltante', fn ($valor) => (float) $valor === 2.0)
            ->where('ordenes.0.distribuciones_detalle.0.talla', 'S')
            ->where('ordenes.0.distribuciones_detalle.1.talla', 'M'));
    }

    /**
     * Contexto mínimo para probar la recepción de calidad: estados del flujo,
     * una OP con dos tallas y su producto.
     *
     * @return array{0: User, 1: OrdenProduccion, 2: array<string, EstadoOperacion>, 3: Distribucion_por_tallas, 4: Distribucion_por_tallas}
     */
    private function contextoRecepcion(string $estadoInicial): array
    {
        $rol = new Role();
        $rol->name = 'admin';
        $rol->guard_name = 'web';
        $rol->save();

        $user = User::factory()->create();
        $user->assignRole($rol);

        $definiciones = [
            'Preparación' => 'Esperando materiales / Lista para taller',
            'En taller' => 'Recibiendo / Pendiente / Cerrada',
            'Recibiendo' => 'Pendiente / Cerrada',
            'Pendiente' => 'Cerrada / Recibiendo',
            'Cerrada' => null,
        ];
        $estados = [];
        foreach ($definiciones as $nombre => $puedePasarA) {
            $estados[$nombre] = EstadoOperacion::create([
                'nombre' => $nombre,
                'puede_pasar_a' => $puedePasarA,
            ]);
        }

        $estadoMaterial = EstadoOpMaterial::create(['nombre' => 'Pendiente', 'valor' => 'pendiente']);
        $taller = Taller::create(['nombre' => 'Taller de prueba', 'direccion' => 'Calle 1 # 2-3']);
        $producto = Producto::create([
            'Codigo_interno' => 'TEST-RECEPCION-001',
            'ID_producto_Shopify' => 'shopify-recepcion-001',
            'Categoria_original' => 'Prueba',
            'Familia_normalizada' => 'Prueba',
            'Titulo_Shopify' => 'Producto de prueba de recepción',
            'Control_de_variante' => 'Única',
            'Opciones' => 'Única',
            'Estado' => 'Activo',
        ]);

        $op = OrdenProduccion::create([
            'numero_op' => 'OP-100',
            'producto_referencia_id' => $producto->id,
            'cantidad_planificada' => 4,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'estado_op_id' => $estados[$estadoInicial]->id,
            'user_id' => $user->id,
            'estado_op_material_id' => $estadoMaterial->id,
        ]);
        $op->productos()->attach($producto->id);

        $tallaS = Distribucion_por_tallas::create([
            'producto_id' => $producto->id,
            'talla' => 'S',
            'cantidad_planificada' => 2,
        ]);
        $tallaM = Distribucion_por_tallas::create([
            'producto_id' => $producto->id,
            'talla' => 'M',
            'cantidad_planificada' => 2,
        ]);
        $op->distribucionesPorTallas()->attach([$tallaS->id, $tallaM->id]);

        return [$user, $op, $estados, $tallaS, $tallaM];
    }
}
