<?php

namespace Tests\Feature;

use App\Models\CompromisoReparacion;
use App\Models\Distribucion_por_tallas;
use App\Models\EmpresaExterna;
use App\Models\EstadoOpMaterial;
use App\Models\EstadoOperacion;
use App\Models\FichaTecnica;
use App\Models\KitProceso;
use App\Models\Material;
use App\Models\MaterialMovimiento;
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
        $rol = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'admin']);

        $user = User::factory()->create();
        $user->assignRole($rol);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $estadoPreparacion = EstadoOperacion::create(['nombre' => 'Preparación']);
        $this->assertDatabaseHas('estado_operacions', ['nombre' => 'Preparación']); // Verifica nombre de tabla según tu migración

        $estadoMaterial = EstadoOpMaterial::create(['nombre' => 'Pendiente', 'valor' => 'pendiente']);
        $this->assertDatabaseHas('estado_op_materials', ['valor' => 'pendiente']);

        $taller = Taller::create(['nombre' => 'Taller de prueba', 'direccion' => 'Calle 1 # 2-3']);
        $this->assertDatabaseHas('tallers', ['nombre' => 'Taller de prueba']);

        $empresaExterna = EmpresaExterna::create(['nombre' => 'Empresa de prueba', 'actividad' => 'Confección']);
        $this->assertDatabaseHas('empresa_externas', ['nombre' => 'Empresa de prueba']);

        $otroUsuario = User::factory()->create();

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
        $this->assertDatabaseHas('productos', ['Codigo_interno' => 'TEST-001']);

        $response = $this->actingAs($user)->post(route('OrdenProduccion.store'), [
            'productos' => [$producto->id],
            'cantidad_planificada' => 10,
            'costo_proceso' => 150.75,
            'tipo' => 'externa',
            'taller_id' => $taller->id,
            'empresa_externa_id' => $empresaExterna->id,
            'user_id' => $otroUsuario->id,
            'responsable_id' => $otroUsuario->id,
            'estado_op_material_id' => $estadoMaterial->id,
            'externa' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(); // Opcional: asumiendo que redirige tras guardar

        $this->assertDatabaseCount('orden_produccions', 1);
        $this->assertDatabaseHas('orden_produccions', [
            'numero_op' => 'OP-100',
            'estado_op_id' => $estadoPreparacion->id,
            'estado_op_material_id' => $estadoMaterial->id,
            'externa' => 1,
            'taller_id' => null,
            'empresa_externa_id' => $empresaExterna->id,
            'user_id' => $user->id,
            'costo_proceso' => 150.75,
            'costo_total' => 150.75,
        ]);

        $this->assertNotSame($otroUsuario->id, $user->id);
        $this->assertDatabaseMissing('orden_produccions', [
            'numero_op' => 'OP-100',
            'user_id' => $otroUsuario->id,
        ]);
    }

    /**
     * Al recibir una OP sin pares en reparación, la recepción se guarda y la
     * OP se cierra automáticamente con su cierre registrado.
     */
    public function test_al_recibir_sin_reparaciones_la_op_se_cierra(): void
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
        $this->assertSame($estados['Cerrada']->id, (int) $op->estado_op_id);
        $this->assertDatabaseCount('recepcion_calidads', 2);
        $this->assertDatabaseHas('recepcion_calidads', [
            'orden_produccion_id' => $op->id,
            'talla' => 'S',
            'cantidad_en_reparacion' => 0,
        ]);
        // El cierre se crea solo, sin que el usuario digite sus valores.
        $this->assertDatabaseHas('cierre_orden_produccions', [
            'orden_produccion_id' => $op->id,
            'perdidas_mermas' => 0,
            'costo_real_final' => 0,
            'completado' => 1,
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
            'fecha_compromiso' => '2026-11-10',
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
        $this->assertDatabaseHas('compromisos_reparacion', [
            'orden_produccion_id' => $op->id,
            'talla' => 'S',
            'cantidad_en_reparacion' => 1,
            'fecha_compromiso' => '2026-11-10',
        ]);
    }

    /**
     * Una OP que ya está en "Recibiendo" puede abrir la recepción, corregir
     * las cantidades y, si ya no queda nada en reparación, se cierra.
     */
    public function test_desde_recibiendo_actualiza_la_recepcion_y_la_cierra(): void
    {
        [$user, $op, $estados, $tallaS, $tallaM] = $this->contextoRecepcion('Recibiendo');

        // Simula una entrada anterior a recepción: al cerrar debe limpiarse.
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
        $this->assertSame($estados['Cerrada']->id, (int) $op->estado_op_id);
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

        CompromisoReparacion::create([
            'orden_produccion_id' => $op->id,
            'producto_referencia_id' => $op->producto_referencia_id,
            'talla' => 'S',
            'cantidad_en_reparacion' => 1,
            'fecha_compromiso' => '2026-11-10',
            'atendido' => false,
        ]);

        $response = $this->actingAs($user)->get(route('OrdenProduccion.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrdenProduccion/Index')
            ->where('ordenes.0.recepciones_detalle.0.talla', 'S')
            ->where('ordenes.0.recepciones_detalle.0.cantidad_en_reparacion', 1)
            ->where('ordenes.0.compromisos_detalle.0.talla', 'S')
            ->where('ordenes.0.compromisos_detalle.0.fecha_compromiso', '2026-11-10')
            ->where('ordenes.0.compromisos_detalle.0.producto_nombre', fn ($valor) => is_string($valor) && $valor !== '')
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
            'fecha_compromiso' => '2026-11-10',
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

    public function test_cuando_una_op_pasa_a_en_taller_debe_disminuir_las_existencias_de_los_materiales_necesarios(): void
    {
        [$user, $op, $estados] = $this->contextoRecepcion('Lista para taller');

        $materialA = Material::create([
            'codigo_material' => 'MAT-TALLER-001',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela principal',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 20,
        ]);

        $materialB = Material::create([
            'codigo_material' => 'MAT-TALLER-002',
            'categoria' => 'Insumos',
            'nombre_base' => 'Botones',
            'unidad_inventario' => 'und',
            'estado' => 'Activo',
            'existencia_actual' => 50,
        ]);

        KitProceso::create([
            'orden_produccion_id' => $op->id,
            'material_id' => $materialA->id,
            'unidad' => 'm',
            'cantidad_requerida' => 5,
            'cantidad_disponible' => 20,
            'cantidad_preparada' => 5,
        ]);

        KitProceso::create([
            'orden_produccion_id' => $op->id,
            'material_id' => $materialB->id,
            'unidad' => 'und',
            'cantidad_requerida' => 12,
            'cantidad_disponible' => 50,
            'cantidad_preparada' => 12,
        ]);

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['En taller']->id,
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame($estados['En taller']->id, (int) $op->estado_op_id);
        $this->assertSame(15.0, (float) $materialA->fresh()->existencia_actual);
        $this->assertSame(38.0, (float) $materialB->fresh()->existencia_actual);

        $this->assertDatabaseHas('material_movimientos', [
            'material_id' => $materialA->id,
            'tipo_movimiento' => 'salida',
            'cantidad' => 5,
            'existencia_anterior' => 20,
            'existencia_nueva' => 15,
        ]);

        $this->assertDatabaseHas('material_movimientos', [
            'material_id' => $materialB->id,
            'tipo_movimiento' => 'salida',
            'cantidad' => 12,
            'existencia_anterior' => 50,
            'existencia_nueva' => 38,
        ]);
    }

    /**
     * Las OPs externas no descuentan materiales al pasar a «En taller»: el
     * material lo maneja la empresa externa.
     */
    public function test_una_op_externa_no_disminuye_las_existencias_al_pasar_a_en_taller(): void //si deberia disminuir!!!
    {
        [$user, $op, $estados] = $this->contextoRecepcion('Lista para taller');

        $material = Material::create([
            'codigo_material' => 'MAT-EXTERNA-001',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela de OP externa',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            // A propósito no alcanza para el kit: una OP externa no debe
            // bloquearse por inventario ni descontarlo.
            'existencia_actual' => 1,
        ]);

        KitProceso::create([
            'orden_produccion_id' => $op->id,
            'material_id' => $material->id,
            'unidad' => 'm',
            'cantidad_requerida' => 4,
            'cantidad_disponible' => 1,
            'cantidad_preparada' => 4,
        ]);

        $op->forceFill(['tipo' => 'externa', 'externa' => true])->save();

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['En taller']->id,
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame($estados['En taller']->id, (int) $op->estado_op_id);
        $this->assertSame(1.0, (float) $material->fresh()->existencia_actual);
        $this->assertDatabaseCount('material_movimientos', 0);
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
     * Una OP puede producir un material procesado. Su cantidad sale del payload
     * materiales_cantidad, no de cantidad_planificada, y debe crear su fila de
     * distribución, su kit y su costo como una OP de producto.
     */
    public function test_se_puede_crear_una_op_de_materiales_procesados_con_ficha(): void
    {
        $contexto = $this->contextoMaterialProcesado();

        $response = $this->actingAs($contexto['user'])->post(route('OrdenProduccion.store'), [
            'productos' => [],
            'materiales' => [$contexto['material']->id],
            'materiales_cantidad' => [$contexto['material']->id => 3],
            'cantidad_planificada' => 3,
            'costo_proceso' => 25.50,
            'tipo' => 'taller',
            'taller_id' => $contexto['taller']->id,
            'user_id' => $contexto['user']->id,
            'estado_op_material_id' => $contexto['estadoMaterial']->id,
        ]);

        $response->assertSessionHasNoErrors();

        $op = OrdenProduccion::query()
            ->whereHas('materiales', fn ($query) => $query->whereKey($contexto['material']->id))
            ->firstOrFail();

        $this->assertNull($op->producto_referencia_id);
        $this->assertSame(3, (int) $op->cantidad_planificada);
        $this->assertSame(60.0, $op->costoFichasTecnicas());
        $this->assertSame(25.5, (float) $op->costo_proceso);
        $this->assertSame(85.5, (float) $op->costo_total);
        $this->assertDatabaseHas('orden_produccion_material', [
            'orden_produccion_id' => $op->id,
            'material_id' => $contexto['material']->id,
            'cantidad' => 3,
        ]);
        $this->assertDatabaseHas('distribucion_por_tallas', [
            'material_id' => $contexto['material']->id,
            'producto_id' => null,
            'talla' => null,
            'cantidad_planificada' => 3,
        ]);
        $this->assertDatabaseHas('kit_procesos', [
            'orden_produccion_id' => $op->id,
            'material_id' => $contexto['componente']->id,
            'cantidad_requerida' => 6,
        ]);
        $this->assertDatabaseHas('orden_produccion_costos_materiales', [
            'orden_produccion_id' => $op->id,
            'material_id' => $contexto['componente']->id,
            'cantidad' => 6,
            'precio_unitario' => 10,
            'costo_total' => 60,
        ]);

        $distribucion = $op->distribucionesPorTallas()->firstOrFail();
        $this->assertSame($contexto['material']->id, (int) $distribucion->material_id);
        $this->assertNull($distribucion->producto_id);
        $this->assertNull($distribucion->talla);
    }

    /**
     * La actualización de una OP de materiales también recalcula el pivote,
     * la distribución, el kit y los costos.
     */
    public function test_se_puede_actualizar_la_cantidad_de_una_op_de_materiales(): void
    {
        $contexto = $this->contextoMaterialProcesado();
        $op = $this->crearOpMaterial($contexto, 3);

        $response = $this->actingAs($contexto['user'])->put(route('OrdenProduccion.update', $op->id), [
            'materiales' => [$contexto['material']->id],
            'materiales_cantidad' => [$contexto['material']->id => 5],
            'cantidad_planificada' => 5,
            'costo_proceso' => 15.25,
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame(5, (int) $op->cantidad_planificada);
        $this->assertSame(100.0, $op->costoFichasTecnicas());
        $this->assertSame(15.25, (float) $op->costo_proceso);
        $this->assertSame(115.25, (float) $op->costo_total);
        $this->assertDatabaseHas('orden_produccion_material', [
            'orden_produccion_id' => $op->id,
            'material_id' => $contexto['material']->id,
            'cantidad' => 5,
        ]);
        $this->assertDatabaseHas('distribucion_por_tallas', [
            'material_id' => $contexto['material']->id,
            'cantidad_planificada' => 5,
        ]);
        $this->assertDatabaseHas('kit_procesos', [
            'orden_produccion_id' => $op->id,
            'material_id' => $contexto['componente']->id,
            'cantidad_requerida' => 10,
        ]);
        $this->assertDatabaseHas('orden_produccion_costos_materiales', [
            'orden_produccion_id' => $op->id,
            'material_id' => $contexto['componente']->id,
            'cantidad' => 10,
            'costo_total' => 100,
        ]);
    }

    /**
     * Una OP no puede producir productos y materiales procesados al mismo
     * tiempo, y un material sin ficha tampoco es elegible.
     */
    public function test_no_se_pueden_mezclar_productos_y_materiales_en_la_misma_op(): void
    {
        $contexto = $this->contextoMaterialProcesado();
        $producto = $this->crearProducto('TEST-MEZCLA-001');

        $response = $this->actingAs($contexto['user'])->post(route('OrdenProduccion.store'), [
            'productos' => [$producto->id],
            'materiales' => [$contexto['material']->id],
            'materiales_cantidad' => [$contexto['material']->id => 1],
            'cantidad_planificada' => 1,
            'tipo' => 'taller',
            'taller_id' => $contexto['taller']->id,
            'user_id' => $contexto['user']->id,
        ]);

        $response->assertSessionHasErrors('materiales');
        $this->assertDatabaseCount('orden_produccions', 0);
    }

    /**
     * Un material que no está marcado como procesado (o no tiene ficha) no
     * puede seleccionarse como objetivo de una OP.
     */
    public function test_no_se_puede_producir_un_material_sin_ficha(): void
    {
        $contexto = $this->contextoMaterialProcesado();
        $sinFicha = Material::create([
            'codigo_material' => 'MAT-SIN-FICHA-001',
            'categoria' => 'Tela',
            'nombre_base' => 'Material sin proceso',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
        ]);

        $response = $this->actingAs($contexto['user'])->post(route('OrdenProduccion.store'), [
            'productos' => [],
            'materiales' => [$sinFicha->id],
            'materiales_cantidad' => [$sinFicha->id => 2],
            'cantidad_planificada' => 2,
            'tipo' => 'taller',
            'taller_id' => $contexto['taller']->id,
            'user_id' => $contexto['user']->id,
        ]);

        $response->assertSessionHasErrors('materiales');
        $this->assertDatabaseCount('orden_produccions', 0);
    }

    /**
     * La recepción de una OP de materiales usa la fila de distribución del
     * material, no una talla, y calcula el cierre con el costo de su ficha.
     */
    public function test_al_recibir_un_material_procesado_la_op_se_cierra_y_cuesta_su_ficha(): void
    {
        $contexto = $this->contextoMaterialProcesado();
        $op = $this->crearOpMaterial($contexto, 3, 'En taller');
        $distribucion = $op->distribucionesPorTallas()->firstOrFail();

        $response = $this->actingAs($contexto['user'])->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $contexto['estados']['Recibiendo']->id,
            'recepciones' => [[
                'distribucion_id' => $distribucion->id,
                'cantidad_aprobada' => 3,
                'cantidad_en_reparacion' => 0,
                'cantidad_no_recuperable' => 0,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame($contexto['estados']['Cerrada']->id, (int) $op->estado_op_id);
        $this->assertDatabaseHas('recepcion_calidads', [
            'orden_produccion_id' => $op->id,
            'producto_referencia_id' => null,
            'material_referencia_id' => $contexto['material']->id,
            'talla' => null,
            'cantidad_aprobada' => 3,
        ]);
        $this->assertDatabaseHas('cierre_orden_produccions', [
            'orden_produccion_id' => $op->id,
            'perdidas_mermas' => 0,
            'costo_real_final' => 60,
            'completado' => 1,
        ]);
    }

    /**
     * Si el material queda en reparación, la OP no se cierra y el compromiso
     * se registra contra el material, no contra un producto.
     */
    public function test_un_material_en_reparacion_crea_compromiso_y_no_cierra_la_op(): void
    {
        $contexto = $this->contextoMaterialProcesado();
        $op = $this->crearOpMaterial($contexto, 3, 'En taller');
        $distribucion = $op->distribucionesPorTallas()->firstOrFail();

        $response = $this->actingAs($contexto['user'])->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $contexto['estados']['Recibiendo']->id,
            'fecha_compromiso' => '2026-11-15',
            'recepciones' => [[
                'distribucion_id' => $distribucion->id,
                'cantidad_aprobada' => 2,
                'cantidad_en_reparacion' => 1,
                'cantidad_no_recuperable' => 0,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame($contexto['estados']['Recibiendo']->id, (int) $op->estado_op_id);
        $this->assertDatabaseHas('compromisos_reparacion', [
            'orden_produccion_id' => $op->id,
            'producto_referencia_id' => null,
            'material_referencia_id' => $contexto['material']->id,
            'talla' => null,
            'cantidad_en_reparacion' => 1,
            'fecha_compromiso' => '2026-11-15',
            'atendido' => 0,
        ]);
    }

    /**
     * El index expone el nombre del material, su cantidad y el costo unitario
     * para que la recepción y el detalle del estado funcionen como en productos.
     */
    public function test_el_index_expone_los_materiales_objetivo_y_sus_costos(): void
    {
        $contexto = $this->contextoMaterialProcesado();
        $op = $this->crearOpMaterial($contexto, 3);
        $op->forceFill(['costo_proceso' => 12.50])->save();
        $op->actualizarCostoTotal();

        $response = $this->actingAs($contexto['user'])->get(route('OrdenProduccion.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrdenProduccion/Index')
            ->where('ordenes.0.es_op_material', true)
            ->where('ordenes.0.costo_fichas', fn ($valor) => (float) $valor === 60.0)
            ->where('ordenes.0.costo_proceso', fn ($valor) => (float) $valor === 12.5)
            ->where('ordenes.0.costo_total', fn ($valor) => (float) $valor === 72.5)
            ->where('ordenes.0.materiales_nombres', fn ($valor) => is_string($valor) && str_contains($valor, 'MAT-PROC-001'))
            ->where('ordenes.0.objetivo_nombre', fn ($valor) => is_string($valor) && str_contains($valor, 'MAT-PROC-001'))
            ->where('ordenes.0.materiales_objetivo.0.id', $contexto['material']->id)
            ->where('ordenes.0.materiales_objetivo.0.cantidad', 3)
            ->where('ordenes.0.distribuciones_detalle.0.material_id', $contexto['material']->id)
            ->where('ordenes.0.distribuciones_detalle.0.material_nombre', fn ($valor) => is_string($valor) && str_contains($valor, 'MAT-PROC-001'))
            ->where('ordenes.0.costo_unitario_materiales', fn ($costos) => (float) ($costos[$contexto['material']->id] ?? 0) === 20.0));
    }

    /**
     * Actualizar solo el costo del proceso debe recalcular el costo de la ficha
     * del producto, persistir el total y reflejarlo también en el kit.
     */
    public function test_actualizar_solo_el_costo_de_proceso_persiste_el_total_de_la_op(): void
    {
        [$user, $op] = $this->contextoRecepcion('Preparación');

        $componente = Material::create([
            'codigo_material' => 'MAT-COSTO-PROCESO',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela para costo de proceso',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 100,
        ]);
        $componente->prices()->create([
            'precio' => 10,
            'fecha' => now()->toDateString(),
        ]);

        $ficha = FichaTecnica::create(['nombre' => 'Ficha del producto de la OP']);
        $ficha->productos()->attach($op->producto_referencia_id);
        $ficha->materiales()->create([
            'material_componente_id' => $componente->id,
            'consumo_estandar' => 2,
            'unidad_medida' => 'm',
            'costo_asociado_teorico' => 10,
        ]);

        $response = $this->actingAs($user)->put(route('OrdenProduccion.update', $op->id), [
            'costo_proceso' => 45,
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame(45.0, (float) $op->costo_proceso);
        $this->assertSame(80.0, $op->costoFichasTecnicas());
        $this->assertSame(125.0, (float) $op->costo_total);
        $this->assertDatabaseHas('orden_produccion_costos_materiales', [
            'orden_produccion_id' => $op->id,
            'material_id' => $componente->id,
            'cantidad' => 8,
            'precio_unitario' => 10,
            'costo_total' => 80,
        ]);
        $this->assertDatabaseHas('kit_procesos', [
            'orden_produccion_id' => $op->id,
            'material_id' => $componente->id,
            'cantidad_requerida' => 8,
        ]);
    }

    /**
     * Contexto mínimo para una OP de un material procesado con una ficha de
     * consumo 2 × componente de $10.
     *
     * @return array{user: User, estados: array<string, EstadoOperacion>, estadoMaterial: EstadoOpMaterial, taller: Taller, componente: Material, material: Material, ficha: FichaTecnica}
     */
    private function contextoMaterialProcesado(): array
    {
        $rol = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($rol);

        $definiciones = [
            'Borrador' => 'Preparación',
            'Preparación' => 'Esperando materiales / Procesos externos / Lista para taller',
            'Esperando materiales' => 'Preparación / Lista para taller',
            'Procesos externos' => 'Lista para taller / Preparación',
            'Lista para taller' => 'En taller / Recibiendo',
            'En taller' => 'Recibiendo / Pendiente / Cerrada',
            'Recibiendo' => 'Pendiente / Cerrada',
            'Pendiente' => 'Cerrada',
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
        $taller = Taller::create(['nombre' => 'Taller de materiales', 'direccion' => 'Calle 10 # 20-30']);

        $componente = Material::create([
            'codigo_material' => 'MAT-COMP-001',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela componente',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 100,
        ]);
        $componente->prices()->create(['precio' => 10, 'fecha' => now()->toDateString()]);

        $material = Material::create([
            'codigo_material' => 'MAT-PROC-001',
            'categoria' => 'Semielaborado',
            'nombre_base' => 'Material procesado',
            'unidad_inventario' => 'u',
            'estado' => 'Activo',
            'material_procesado' => 1,
            'existencia_actual' => 0,
        ]);

        $ficha = FichaTecnica::create(['nombre' => 'Ficha MAT-PROC-001']);
        $ficha->materiales()->create([
            'material_componente_id' => $componente->id,
            'consumo_estandar' => 2,
            'unidad_medida' => 'm',
            'costo_asociado_teorico' => 10,
        ]);
        $ficha->materialesProcesados()->attach($material->id);

        return compact('user', 'estados', 'estadoMaterial', 'taller', 'componente', 'material', 'ficha');
    }

    /**
     * Crea una OP de material directamente (sin pasar por el formulario) para
     * probar recepción e index.
     *
     * @param  array{user: User, estados: array<string, EstadoOperacion>, estadoMaterial: EstadoOpMaterial, taller: Taller, componente: Material, material: Material, ficha: FichaTecnica}  $contexto
     */
    private function crearOpMaterial(array $contexto, int $cantidad, string $estado = 'Preparación'): OrdenProduccion
    {
        $op = OrdenProduccion::create([
            'numero_op' => 'OP-200',
            'cantidad_planificada' => $cantidad,
            'tipo' => 'taller',
            'taller_id' => $contexto['taller']->id,
            'estado_op_id' => $contexto['estados'][$estado]->id,
            'user_id' => $contexto['user']->id,
            'estado_op_material_id' => $contexto['estadoMaterial']->id,
        ]);

        $op->materiales()->attach($contexto['material']->id, ['cantidad' => $cantidad]);

        $distribucion = Distribucion_por_tallas::create([
            'producto_id' => null,
            'material_id' => $contexto['material']->id,
            'talla' => null,
            'cantidad_planificada' => $cantidad,
        ]);
        $op->distribucionesPorTallas()->attach($distribucion->id);

        KitProceso::create([
            'orden_produccion_id' => $op->id,
            'material_id' => $contexto['componente']->id,
            'unidad' => 'm',
            'cantidad_requerida' => $cantidad * 2,
            'cantidad_disponible' => 100,
        ]);

        app(\App\Services\CostoMaterialOpService::class)->recalcular($op->fresh([
            'materiales.fichaTecnica.materiales',
            'distribucionesPorTallas',
            'costosMateriales',
        ]));

        return $op;
    }

    /**
     * Producto mínimo para probar la exclusividad de objetivos.
     */
    private function crearProducto(string $codigo): Producto
    {
        return Producto::create([
            'Codigo_interno' => $codigo,
            'ID_producto_Shopify' => 'shopify-' . strtolower($codigo),
            'Categoria_original' => 'Prueba',
            'Familia_normalizada' => 'Prueba',
            'Titulo_Shopify' => 'Producto ' . $codigo,
            'Control_de_variante' => 'Única',
            'Opciones' => 'Única',
            'Estado' => 'Activo',
        ]);
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
            'Pendiente' => 'Cerrada',
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
