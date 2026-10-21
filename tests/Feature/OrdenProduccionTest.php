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
use App\Models\ProcesoExterno;
use App\Models\Proveedor;
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
     * Una OP con pares en reparación no puede pasar a "Cerrada".
     */
    public function test_no_se_puede_cerrar_la_op_con_pares_en_reparacion(): void
    {
        [$user, $op, $estados, $tallaS, $tallaM] = $this->contextoRecepcion('Recibiendo');

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Cerrada']->id,
            'recepciones' => [
                ['distribucion_id' => $tallaS->id, 'cantidad_aprobada' => 1, 'cantidad_en_reparacion' => 1, 'cantidad_no_recuperable' => 0],
                ['distribucion_id' => $tallaM->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
            ],
        ]);

        $response->assertSessionHasErrors('estado_op_id');
        $this->assertSame($estados['Recibiendo']->id, (int) $op->fresh()->estado_op_id);
    }

    /**
     * Tampoco puede cerrarse desde otro estado si quedan reparaciones
     * guardadas de la recepción anterior.
     */
    public function test_no_se_puede_cerrar_la_op_desde_pendiente_si_quedan_reparaciones(): void
    {
        [$user, $op, $estados] = $this->contextoRecepcion('Pendiente');

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

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Cerrada']->id,
        ]);

        $response->assertSessionHasErrors('estado_op_id');
        $this->assertSame($estados['Pendiente']->id, (int) $op->fresh()->estado_op_id);
    }

    /**
     * Sin pares en reparación el cierre sí está permitido.
     */
    public function test_se_puede_cerrar_la_op_cuando_no_quedan_pares_en_reparacion(): void
    {
        [$user, $op, $estados, $tallaS, $tallaM] = $this->contextoRecepcion('Recibiendo');

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Cerrada']->id,
            'recepciones' => [
                ['distribucion_id' => $tallaS->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
                ['distribucion_id' => $tallaM->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
            ],
            'cierre' => [
                'materiales_sobrantes' => 0,
                'perdidas_mermas' => 0,
                'costo_real_final' => 100,
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $op->refresh();
        $this->assertSame($estados['Cerrada']->id, (int) $op->estado_op_id);
        $this->assertNull($op->recepcion_desde);
    }

    /**
     * Al entrar a "Procesos externos" se registra en proceso_externos cada
     * material del kit de la OP, con la fecha de salida actual.
     */
    public function test_al_pasar_a_procesos_externos_registra_los_materiales_del_kit(): void
    {
        [$user, $op, $estados] = $this->contextoRecepcion('Preparación');

        $material = Material::create([
            'codigo_material' => 'MAT-EXT-001',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela para proceso externo',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 20,
        ]);

        KitProceso::create([
            'orden_produccion_id' => $op->id,
            'material_id' => $material->id,
            'unidad' => 'm',
            'cantidad_requerida' => 5.5,
            'cantidad_disponible' => 20,
            'cantidad_preparada' => 0,
        ]);

        $proveedor = Proveedor::create(['nombre' => 'Bordados de prueba', 'categoria' => 'Prueba']);

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Procesos externos']->id,
            'proceso_externo' => [
                'proveedor_destino_id' => $proveedor->id,
                'etapa_componente' => 'Bordado',
                'fecha_prevista_regreso' => '2026-11-05',
                'items' => [
                    [
                        'material_origen_id' => $material->id,
                        'cantidad_salida' => 5.5,
                        'unidad_medida' => 'm',
                    ],
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame($estados['Procesos externos']->id, (int) $op->estado_op_id);

        $proceso = ProcesoExterno::where('orden_produccion_id', $op->id)->firstOrFail();
        $this->assertSame($material->id, (int) $proceso->material_origen_id);
        $this->assertSame($proveedor->id, (int) $proceso->proveedor_destino_id);
        $this->assertSame('Bordado', $proceso->etapa_componente);
        $this->assertSame(5.5, $proceso->cantidad_salida);
        $this->assertSame('m', $proceso->unidad_medida);
        $this->assertSame('2026-11-05', $proceso->fecha_prevista_regreso->toDateString());
        $this->assertNotNull($proceso->fecha_salida);
    }

    /**
     * No se puede entrar a "Procesos externos" sin registrar el proveedor y
     * los materiales que salen.
     */
    public function test_no_pasa_a_procesos_externos_sin_registrar_materiales(): void
    {
        [$user, $op, $estados] = $this->contextoRecepcion('Preparación');

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Procesos externos']->id,
        ]);

        $response->assertSessionHasErrors('proceso_externo');
        $this->assertSame($estados['Preparación']->id, (int) $op->fresh()->estado_op_id);
        $this->assertDatabaseCount('proceso_externos', 0);
    }

    /**
     * Al pasar a "Lista para taller" se pregunta y guarda la fecha real de
     * regreso de los procesos externos pendientes.
     */
    public function test_lista_para_taller_registra_la_fecha_real_de_regreso(): void
    {
        [$user, $op, $estados] = $this->contextoRecepcion('Procesos externos');

        $material = Material::create([
            'codigo_material' => 'MAT-EXT-002',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela que regresa',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 10,
        ]);

        $proveedor = Proveedor::create(['nombre' => 'Bordados de prueba', 'categoria' => 'Prueba']);

        $proceso = ProcesoExterno::create([
            'orden_produccion_id' => $op->id,
            'etapa_componente' => 'Bordado',
            'material_origen_id' => $material->id,
            'cantidad_salida' => 2,
            'unidad_medida' => 'm',
            'proveedor_destino_id' => $proveedor->id,
            'fecha_salida' => now()->subDays(3),
            'fecha_prevista_regreso' => now()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Lista para taller']->id,
            'fecha_regreso_real' => '2026-11-02',
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame($estados['Lista para taller']->id, (int) $op->estado_op_id);
        $this->assertSame('2026-11-02', $proceso->fresh()->fecha_regreso_real->toDateString());
    }

    /**
     * Con procesos externos pendientes no se puede pasar a "Lista para taller"
     * sin indicar la fecha real de regreso.
     */
    public function test_no_pasa_a_lista_para_taller_sin_fecha_de_regreso(): void
    {
        [$user, $op, $estados] = $this->contextoRecepcion('Procesos externos');

        $material = Material::create([
            'codigo_material' => 'MAT-EXT-003',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela pendiente',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 10,
        ]);

        $proveedor = Proveedor::create(['nombre' => 'Bordados de prueba', 'categoria' => 'Prueba']);

        ProcesoExterno::create([
            'orden_produccion_id' => $op->id,
            'etapa_componente' => 'Bordado',
            'material_origen_id' => $material->id,
            'cantidad_salida' => 2,
            'unidad_medida' => 'm',
            'proveedor_destino_id' => $proveedor->id,
            'fecha_salida' => now()->subDays(3),
        ]);

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Lista para taller']->id,
        ]);

        $response->assertSessionHasErrors('fecha_regreso_real');
        $this->assertSame($estados['Procesos externos']->id, (int) $op->fresh()->estado_op_id);
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
            'Preparación' => 'Esperando materiales / Procesos externos / Lista para taller',
            'Esperando materiales' => 'Preparación / Lista para taller',
            'Procesos externos' => 'Lista para taller / Preparación',
            'Lista para taller' => 'En taller / Recibiendo',
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
