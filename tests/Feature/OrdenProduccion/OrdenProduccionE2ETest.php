<?php

namespace Tests\Feature\OrdenProduccion;

use App\Models\Distribucion_por_tallas;
use App\Models\EstadoOpMaterial;
use App\Models\EstadoOperacion;
use App\Models\KitProceso;
use App\Models\Material;
use App\Models\MaterialMovimiento;
use App\Models\OrdenProduccion;
use App\Models\ProductoMovimiento;
use App\Models\Tercero;
use App\Models\User;
use App\Services\ColaMaterialesOpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\OrdenProduccion\Concerns\SeedsOrdenesProduccion;
use Tests\TestCase;

/**
 * Suite de extremo a extremo del ciclo de vida de la Orden de Producción.
 *
 * Recorre la OP completa —creación (borrador y definitiva), transiciones de
 * estado, asignación/consumo de materiales, validaciones y cierre— por las
 * rutas reales de la aplicación, con datos propios creados en cada test. Es
 * independiente de las demás suites: no usa seeders ni factories vacíos y no
 * comparte fixtures con ellas.
 */
class OrdenProduccionE2ETest extends TestCase
{
    use RefreshDatabase;
    use SeedsOrdenesProduccion;

    // ────────────────────────────────────────────────────────
    //  CREACIÓN
    // ────────────────────────────────────────────────────────

    /**
     * Una OP de producto arranca en «Esperando materiales» (la cola), con el
     * número calculado en el servidor (OP-100) y su kit generado desde la ficha.
     */
    public function test_crea_una_op_de_producto_con_kit_desde_la_ficha(): void
    {
        $user = $this->usuario();
        $this->estados();
        $taller = $this->taller();
        $estadoMaterial = $this->estadoMaterial();
        $producto = $this->productoConFicha(consumo: 2, precioComponente: 10);

        $response = $this->actingAs($user)->post(route('OrdenProduccion.store'), [
            'productos' => [$producto['producto']->id],
            'cantidad_planificada' => 10,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'estado_op_material_id' => $estadoMaterial->id,
        ]);

        $response->assertSessionHasNoErrors();

        $op = OrdenProduccion::firstOrFail();
        $this->assertSame('OP-100', $op->numero_op);
        $this->assertSame(10, (int) $op->cantidad_planificada);
        $this->assertSame(200.0, $op->costoFichasTecnicas());
        $this->assertSame(200.0, (float) $op->costo_total);

        $estadoEspera = EstadoOperacion::where('nombre', ColaMaterialesOpService::ESTADO_ESPERA)->value('id');
        $this->assertSame((int) $estadoEspera, (int) $op->estado_op_id);
        $this->assertNotNull($op->esperando_materiales_desde);

        // El kit se generó desde la ficha: 10 unidades x 2 de consumo = 20.
        $this->assertDatabaseHas('kit_procesos', [
            'orden_produccion_id' => $op->id,
            'material_id' => $producto['componente']->id,
            'cantidad_requerida' => 20,
        ]);
    }

    /**
     * La OP de materiales procesados toma su cantidad del payload de materiales
     * (no de cantidad_planificada) y genera su fila de distribución, su kit y su
     * costo igual que una OP de producto.
     */
    public function test_crea_una_op_de_material_procesado_con_ficha(): void
    {
        $user = $this->usuario();
        $this->estados();
        $taller = $this->taller();
        $estadoMaterial = $this->estadoMaterial();
        $contexto = $this->materialProcesado(consumo: 2, precioComponente: 10);

        $response = $this->actingAs($user)->post(route('OrdenProduccion.store'), [
            'productos' => [],
            'materiales' => [$contexto['material']->id],
            'materiales_cantidad' => [$contexto['material']->id => 3],
            'cantidad_planificada' => 3,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'estado_op_material_id' => $estadoMaterial->id,
        ]);

        $response->assertSessionHasNoErrors();

        $op = OrdenProduccion::firstOrFail();
        $this->assertNull($op->producto_referencia_id);
        $this->assertSame(3, (int) $op->cantidad_planificada);
        $this->assertSame(60.0, $op->costoFichasTecnicas());

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
    }

    /**
     * «Guardar borrador» crea la OP en «Borrador» con datos parciales, sin exigir
     * kit ni distribución completos.
     */
    public function test_guarda_un_borrador_en_estado_borrador(): void
    {
        $user = $this->usuario();
        $this->estados();

        $response = $this->actingAs($user)->post(route('OrdenProduccion.borrador'), [
            'cantidad_planificada' => 5,
            'observaciones' => 'Borrador E2E',
        ]);

        $response->assertSessionHasNoErrors();

        $estadoBorrador = EstadoOperacion::where('nombre', 'Borrador')->value('id');
        $this->assertDatabaseHas('orden_produccions', [
            'numero_op' => 'OP-100',
            'cantidad_planificada' => 5,
            'estado_op_id' => $estadoBorrador,
            'user_id' => $user->id,
        ]);
    }

    // ────────────────────────────────────────────────────────
    //  TRANSICIONES DE ESTADO
    // ────────────────────────────────────────────────────────

    /**
     * Recorre el flujo completo: Preparación → Lista para taller → En taller.
     * Al entrar a taller, el kit sale de bodega y queda el movimiento de salida.
     */
    public function test_flujo_completo_hasta_en_taller_descuenta_el_kit(): void
    {
        $user = $this->usuario();
        $estados = $this->estados();
        $taller = $this->taller();
        $estadoMaterial = $this->estadoMaterial();
        $contexto = $this->materialProcesado(consumo: 2, precioComponente: 10);

        // Material disponible de sobra para cubrir el kit (3 x 2 = 6).
        $contexto['componente']->update(['existencia_actual' => 100]);

        $response = $this->actingAs($user)->post(route('OrdenProduccion.store'), [
            'materiales' => [$contexto['material']->id],
            'materiales_cantidad' => [$contexto['material']->id => 3],
            'cantidad_planificada' => 3,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'estado_op_material_id' => $estadoMaterial->id,
            'pasar_a_lista' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $op = OrdenProduccion::firstOrFail();
        $lista = EstadoOperacion::where('nombre', 'Lista para taller')->value('id');
        $this->assertSame((int) $lista, (int) $op->estado_op_id);
        $this->assertNull($op->esperando_materiales_desde);

        // En taller: sale el kit (6 unidades del componente).
        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['En taller']->id,
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame($estados['En taller']->id, (int) $op->estado_op_id);
        $this->assertSame(94.0, (float) $contexto['componente']->fresh()->existencia_actual);
        $this->assertSame(0.0, (float) $contexto['material']->fresh()->existencia_actual);

        $this->assertDatabaseHas('material_movimientos', [
            'material_id' => $contexto['componente']->id,
            'tipo_movimiento' => 'salida',
            'cantidad' => 6,
            'existencia_anterior' => 100,
            'existencia_nueva' => 94,
        ]);
    }

    /**
     * Una OP con faltantes no puede saltar a «Lista para taller»: la transición
     * se rechaza y la OP permanece en la cola de «Esperando materiales».
     */
    public function test_no_pasa_a_lista_para_taller_con_kit_incompleto(): void
    {
        $user = $this->usuario();
        $estados = $this->estados();
        $taller = $this->taller();
        $estadoMaterial = $this->estadoMaterial();
        $contexto = $this->materialProcesado(consumo: 2, precioComponente: 10);
        // El componente solo alcanza para 2 de las 3 unidades: faltan materiales.
        $contexto['componente']->update(['existencia_actual' => 4]);

        $response = $this->actingAs($user)->post(route('OrdenProduccion.store'), [
            'materiales' => [$contexto['material']->id],
            'materiales_cantidad' => [$contexto['material']->id => 3],
            'cantidad_planificada' => 3,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'estado_op_material_id' => $estadoMaterial->id,
            'pasar_a_lista' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $op = OrdenProduccion::firstOrFail();
        $estadoEspera = EstadoOperacion::where('nombre', ColaMaterialesOpService::ESTADO_ESPERA)->value('id');
        $this->assertSame((int) $estadoEspera, (int) $op->estado_op_id);
    }

    /**
     * Reenviar el estado actual es válido en «Recibiendo»: actualiza la recepción
     * sin cambiar de estado.
     */
    public function test_recibiendo_permite_reenviar_el_mismo_estado(): void
    {
        $user = $this->usuario();
        $estados = $this->estados();
        $taller = $this->taller();
        $estadoMaterial = $this->estadoMaterial();
        $producto = $this->productoConFicha(consumo: 2, precioComponente: 10);

        $op = $this->crearOpProducto($user, $estados, $taller, $estadoMaterial, $producto['producto'], 'Recibiendo');
        $distribucion = $this->distribucion($op->id, [
            'producto_id' => $producto['producto']->id,
            'talla' => 'S',
            'cantidad_planificada' => 4,
        ]);

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Recibiendo']->id,
            'recepciones' => [[
                'distribucion_id' => $distribucion->id,
                'cantidad_aprobada' => 4,
                'cantidad_en_reparacion' => 0,
                'cantidad_no_recuperable' => 0,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        // Recibiendo completo → cierre automático.
        $this->assertSame($estados['Cerrada']->id, (int) $op->estado_op_id);
        $this->assertDatabaseHas('recepcion_calidads', [
            'orden_produccion_id' => $op->id,
            'talla' => 'S',
            'cantidad_aprobada' => 4,
        ]);
        $this->assertDatabaseHas('cierre_orden_produccions', [
            'orden_produccion_id' => $op->id,
            'completado' => 1,
        ]);
    }

    // ────────────────────────────────────────────────────────
    //  ASIGNACIÓN / CONSUMO DE MATERIALES
    // ────────────────────────────────────────────────────────

    /**
     * La compra de un material mueve el inventario y promueve la OP que estaba
     * en «Esperando materiales» a «Lista para taller».
     */
    public function test_la_compra_de_material_promueve_la_op_a_lista_para_taller(): void
    {
        $user = $this->usuario();
        $estados = $this->estados();
        $taller = $this->taller();
        $estadoMaterial = $this->estadoMaterial();
        $contexto = $this->materialProcesado(consumo: 2, precioComponente: 10);

        // El componente no alcanza: la OP queda esperando materiales.
        $contexto['componente']->update(['existencia_actual' => 2]);

        $this->actingAs($user)->post(route('OrdenProduccion.store'), [
            'materiales' => [$contexto['material']->id],
            'materiales_cantidad' => [$contexto['material']->id => 3],
            'cantidad_planificada' => 3,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'estado_op_material_id' => $estadoMaterial->id,
        ])->assertSessionHasNoErrors();

        $op = OrdenProduccion::firstOrFail();
        $this->assertSame(
            (int) EstadoOperacion::where('nombre', ColaMaterialesOpService::ESTADO_ESPERA)->value('id'),
            (int) $op->estado_op_id
        );

        // Se compra el componente (precio 12): ahora alcanza para el kit (3 x 2 = 6).
        $this->actingAs($user)->post(route('Material.compra'), [
            'materiales' => [
                ['material_id' => $contexto['componente']->id, 'cantidad' => 4, 'precio' => 12],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(6.0, (float) $contexto['componente']->fresh()->existencia_actual);
        $this->assertSame(
            (int) EstadoOperacion::where('nombre', ColaMaterialesOpService::ESTADO_LISTA)->value('id'),
            (int) $op->fresh()->estado_op_id
        );
    }

    /**
     * Al entrar a taller sale el kit; al recibir, lo aprobado del material
     * procesado entra de nuevo al inventario con su movimiento de entrada.
     */
    public function test_entrada_a_taller_y_cierre_mueven_el_inventario_del_material(): void
    {
        $user = $this->usuario();
        $estados = $this->estados();
        $taller = $this->taller();
        $estadoMaterial = $this->estadoMaterial();
        $contexto = $this->materialProcesado(consumo: 2, precioComponente: 10);
        $contexto['componente']->update(['existencia_actual' => 100]);

        $this->actingAs($user)->post(route('OrdenProduccion.store'), [
            'materiales' => [$contexto['material']->id],
            'materiales_cantidad' => [$contexto['material']->id => 3],
            'cantidad_planificada' => 3,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'estado_op_material_id' => $estadoMaterial->id,
            'pasar_a_lista' => true,
        ])->assertSessionHasNoErrors();

        $op = OrdenProduccion::firstOrFail();

        $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['En taller']->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(94.0, (float) $contexto['componente']->fresh()->existencia_actual);

        $distribucion = $op->distribucionesPorTallas()->firstOrFail();
        $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Recibiendo']->id,
            'recepciones' => [[
                'distribucion_id' => $distribucion->id,
                'cantidad_aprobada' => 3,
                'cantidad_en_reparacion' => 0,
                'cantidad_no_recuperable' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3.0, (float) $contexto['material']->fresh()->existencia_actual);
        $this->assertDatabaseHas('material_movimientos', [
            'material_id' => $contexto['material']->id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => 3,
        ]);
        $this->assertSame($estados['Cerrada']->id, (int) $op->fresh()->estado_op_id);
    }

    // ────────────────────────────────────────────────────────
    //  VALIDACIONES / ERRORES ESPERADOS
    // ────────────────────────────────────────────────────────

    /**
     * Una transición no permitida (Borrador → Cerrada) se rechaza y la OP
     * conserva su estado.
     */
    public function test_transicion_no_permitida_es_rechazada(): void
    {
        $user = $this->usuario();
        $estados = $this->estados();
        $taller = $this->taller();
        $estadoMaterial = $this->estadoMaterial();
        $producto = $this->productoConFicha();

        $op = $this->crearOpProducto($user, $estados, $taller, $estadoMaterial, $producto['producto'], 'Borrador');

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Cerrada']->id,
        ]);

        $response->assertSessionHasErrors('estado_op_id');
        $this->assertSame($estados['Borrador']->id, (int) $op->fresh()->estado_op_id);
    }

    /**
     * No se puede cerrar una OP mientras queden pares en reparación.
     */
    public function test_no_se_puede_cerrar_con_pares_en_reparacion(): void
    {
        $user = $this->usuario();
        $estados = $this->estados();
        $taller = $this->taller();
        $estadoMaterial = $this->estadoMaterial();
        $producto = $this->productoConFicha();

        $op = $this->crearOpProducto($user, $estados, $taller, $estadoMaterial, $producto['producto'], 'En taller');
        $distribucion = $this->distribucion($op->id, [
            'producto_id' => $producto['producto']->id,
            'talla' => 'S',
            'cantidad_planificada' => 2,
        ]);

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Cerrada']->id,
            'fecha_compromiso' => '2026-11-15',
            'recepciones' => [[
                'distribucion_id' => $distribucion->id,
                'cantidad_aprobada' => 1,
                'cantidad_en_reparacion' => 1,
                'cantidad_no_recuperable' => 0,
            ]],
        ]);

        $response->assertSessionHasErrors('estado_op_id');
        $this->assertSame($estados['En taller']->id, (int) $op->fresh()->estado_op_id);
    }

    /**
     * La recepción no puede superar la cantidad esperada de la talla.
     */
    public function test_la_recepcion_no_puede_superar_lo_esperado(): void
    {
        $user = $this->usuario();
        $estados = $this->estados();
        $taller = $this->taller();
        $estadoMaterial = $this->estadoMaterial();
        $producto = $this->productoConFicha();

        $op = $this->crearOpProducto($user, $estados, $taller, $estadoMaterial, $producto['producto'], 'En taller');
        $distribucion = $this->distribucion($op->id, [
            'producto_id' => $producto['producto']->id,
            'talla' => 'S',
            'cantidad_planificada' => 2,
        ]);

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Recibiendo']->id,
            'recepciones' => [[
                'distribucion_id' => $distribucion->id,
                'cantidad_aprobada' => 3,
                'cantidad_en_reparacion' => 0,
                'cantidad_no_recuperable' => 0,
            ]],
        ]);

        $response->assertSessionHasErrors('recepciones');
        $this->assertSame($estados['En taller']->id, (int) $op->fresh()->estado_op_id);
    }

    // ────────────────────────────────────────────────────────
    //  CIERRE / FINALIZACIÓN
    // ────────────────────────────────────────────────────────

    /**
     * Con la recepción completa (todo aprobado y sin reparaciones) la OP se
     * cierra automáticamente, registra su cierre y el inventario del producto
     * sube por talla con su historial.
     */
    public function test_cierre_automatico_registra_cierre_y_actualiza_inventario(): void
    {
        $user = $this->usuario();
        $estados = $this->estados();
        $taller = $this->taller();
        $estadoMaterial = $this->estadoMaterial();
        $producto = $this->productoConFicha();

        $op = $this->crearOpProducto($user, $estados, $taller, $estadoMaterial, $producto['producto'], 'En taller');
        $distribucion = $this->distribucion($op->id, [
            'producto_id' => $producto['producto']->id,
            'talla' => 'S',
            'cantidad_planificada' => 4,
        ]);

        $response = $this->actingAs($user)->put(route('OrdenProduccion.cambiarEstado', $op->id), [
            'estado_op_id' => $estados['Recibiendo']->id,
            'recepciones' => [[
                'distribucion_id' => $distribucion->id,
                'cantidad_aprobada' => 4,
                'cantidad_en_reparacion' => 0,
                'cantidad_no_recuperable' => 0,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $op->refresh();
        $this->assertSame($estados['Cerrada']->id, (int) $op->estado_op_id);
        $this->assertNull($op->recepcion_desde);

        $this->assertDatabaseHas('cierre_orden_produccions', [
            'orden_produccion_id' => $op->id,
            'perdidas_mermas' => 0,
            'completado' => 1,
            'estado_accion' => 'cerrado',
        ]);

        $this->assertSame(4, (int) $distribucion->fresh()->cantidad_recibida_buena);
        $this->assertDatabaseHas('producto_movimientos', [
            'producto_id' => $producto['producto']->id,
            'talla' => 'S',
            'orden_produccion_id' => $op->id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => 4,
        ]);
    }

    // ────────────────────────────────────────────────────────
    //  HELPERS DE LA SUITE
    // ────────────────────────────────────────────────────────

    /**
     * Crea una OP de producto directamente (sin formulario) en el estado dado.
     */
    private function crearOpProducto(
        User $user,
        array $estados,
        Tercero $taller,
        EstadoOpMaterial $estadoMaterial,
        \App\Models\Producto $producto,
        string $estado
    ): OrdenProduccion {
        $op = OrdenProduccion::create([
            'numero_op' => 'OP-300',
            'producto_referencia_id' => $producto->id,
            'cantidad_planificada' => 4,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'estado_op_id' => $estados[$estado]->id,
            'user_id' => $user->id,
            'estado_op_material_id' => $estadoMaterial->id,
        ]);

        $op->productos()->attach($producto->id);

        return $op;
    }
}