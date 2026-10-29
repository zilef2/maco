<?php

namespace Tests\Feature;

use App\Models\Distribucion_por_tallas;
use App\Models\EstadoOpMaterial;
use App\Models\EstadoOperacion;
use App\Models\FichaTecnica;
use App\Models\KitProceso;
use App\Models\Material;
use App\Models\MaterialMovimiento;
use App\Models\OrdenProduccion;
use App\Models\Producto;
use App\Models\ProductoMovimiento;
use App\Models\Role;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El efecto de un cambio de estado sobre el inventario va en dos tiempos:
 * primero salen los materiales que consume la OP (kit) y sólo después entra en
 * inventario lo que la OP produce: la existencia del material procesado
 * objetivo, o la cantidad por talla del producto objetivo. Cada uno de esos
 * cambios queda en su histórico (material_movimientos / producto_movimientos).
 */
class OrdenProduccionInventarioTest extends TestCase
{
    use RefreshDatabase;

    /** Estados del flujo, en el orden en que el inventario debe reaccionar. */
    private const FLUJO = [
        'Preparación' => 'Esperando materiales / Procesos externos / Lista para taller',
        'Esperando materiales' => 'Preparación / Lista para taller',
        'Procesos externos' => 'Lista para taller / Preparación',
        'Lista para taller' => 'En taller / Recibiendo',
        'En taller' => 'Recibiendo / Pendiente / Cerrada',
        'Recibiendo' => 'Pendiente / Cerrada',
        'Pendiente' => 'Cerrada',
        'Cerrada' => null,
    ];

    /**
     * Al entrar a «En taller» sale el kit de bodega: bajan los componentes y
     * el material producido sigue intacto, porque todavía no existe.
     */
    public function test_al_pasar_a_en_taller_solo_descienden_los_materiales_consumidos(): void
    {
        $c = $this->contexto('Lista para taller');

        $response = $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['En taller']->id,
        ]);

        $response->assertSessionHasNoErrors();

        // 3 unidades producidas x 2 de consumo = 6 fuera de inventario.
        $this->assertSame(94.0, (float) $c['componente']->fresh()->existencia_actual);
        $this->assertSame(0.0, (float) $c['producido']->fresh()->existencia_actual);

        $salida = MaterialMovimiento::where('material_id', $c['componente']->id)->sole();
        $this->assertSame('salida', $salida->tipo_movimiento);
        $this->assertSame(6.0, (float) $salida->cantidad);
        $this->assertSame(100.0, (float) $salida->existencia_anterior);
        $this->assertSame(94.0, (float) $salida->existencia_nueva);

        $this->assertSame(0, MaterialMovimiento::where('material_id', $c['producido']->id)->count());
    }

    /**
     * Al quedar completa la recepción, lo aprobado entra al inventario del
     * material que produce la OP y deja su movimiento de entrada.
     */
    public function test_al_cerrar_la_op_incrementa_el_inventario_del_material_producido(): void
    {
        $c = $this->contexto('En taller');

        $response = $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['Recibiendo']->id,
            'recepciones' => [[
                'distribucion_id' => $c['distribucion']->id,
                'cantidad_aprobada' => 3,
                'cantidad_en_reparacion' => 0,
                'cantidad_no_recuperable' => 0,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame($c['estados']['Cerrada']->id, (int) $c['op']->fresh()->estado_op_id);
        $this->assertSame(3.0, (float) $c['producido']->fresh()->existencia_actual);

        $entrada = MaterialMovimiento::where('material_id', $c['producido']->id)->sole();
        $this->assertSame('entrada', $entrada->tipo_movimiento);
        $this->assertSame(3.0, (float) $entrada->cantidad);
        $this->assertSame(0.0, (float) $entrada->existencia_anterior);
        $this->assertSame(3.0, (float) $entrada->existencia_nueva);
    }

    /**
     * Los dos tiempos del cierre de un ciclo: primero descuenta el kit y sólo
     * después registra la entrada del producto, sin solaparse.
     */
    public function test_el_descuento_de_materiales_ocurre_antes_del_incremento_del_producido(): void
    {
        $c = $this->contexto('Lista para taller');

        $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['En taller']->id,
        ])->assertSessionHasNoErrors();

        // Nada entra al inventario mientras la OP sigue sin recibir.
        $this->assertSame(0, MaterialMovimiento::where('tipo_movimiento', 'entrada')->count());

        $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['Recibiendo']->id,
            'recepciones' => [[
                'distribucion_id' => $c['distribucion']->id,
                'cantidad_aprobada' => 3,
                'cantidad_en_reparacion' => 0,
                'cantidad_no_recuperable' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $movimientos = MaterialMovimiento::orderBy('id')->get()->pluck('tipo_movimiento')->all();
        $this->assertSame(['salida', 'entrada'], $movimientos);

        $this->assertSame(94.0, (float) $c['componente']->fresh()->existencia_actual);
        $this->assertSame(3.0, (float) $c['producido']->fresh()->existencia_actual);
    }

    /**
     * La entrada refleja lo efectivamente aprobado: una recepción parcial
     * aumenta sólo lo aprobado y, al completarla después, el inventario queda
     * en la cantidad final y no en la suma de ambas guardadas.
     */
    public function test_la_entrada_del_producido_siempre_es_lo_aprobado_y_no_se_duplica(): void
    {
        $c = $this->contexto('En taller');

        $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['Recibiendo']->id,
            'fecha_compromiso' => '2026-11-15',
            'recepciones' => [[
                'distribucion_id' => $c['distribucion']->id,
                'cantidad_aprobada' => 2,
                'cantidad_en_reparacion' => 1,
                'cantidad_no_recuperable' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame($c['estados']['Recibiendo']->id, (int) $c['op']->fresh()->estado_op_id);
        $this->assertSame(2.0, (float) $c['producido']->fresh()->existencia_actual);

        $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['Recibiendo']->id,
            'recepciones' => [[
                'distribucion_id' => $c['distribucion']->id,
                'cantidad_aprobada' => 3,
                'cantidad_en_reparacion' => 0,
                'cantidad_no_recuperable' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3.0, (float) $c['producido']->fresh()->existencia_actual);
    }

    /**
     * Un par no recuperable se pierde: no entra al inventario del material
     * producido aunque la OP cierre.
     */
    public function test_lo_no_recuperable_no_incrementa_el_inventario_del_producido(): void
    {
        $c = $this->contexto('En taller');

        $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['Recibiendo']->id,
            'recepciones' => [[
                'distribucion_id' => $c['distribucion']->id,
                'cantidad_aprobada' => 2,
                'cantidad_en_reparacion' => 0,
                'cantidad_no_recuperable' => 1,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame($c['estados']['Cerrada']->id, (int) $c['op']->fresh()->estado_op_id);
        $this->assertSame(2.0, (float) $c['producido']->fresh()->existencia_actual);
    }

    /**
     * Faltante de stock en el kit: la OP no avanza y ningún inventario se
     * mueve, incluidos los materiales ya descontados en el paso anterior.
     */
    public function test_sin_stock_suficiente_no_se_descuenta_ni_se_incrementa_nada(): void
    {
        $c = $this->contexto('Lista para taller');
        $c['componente']->update(['existencia_actual' => 4]);

        $response = $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['En taller']->id,
        ]);

        $response->assertSessionHasErrors('estado_op_id');

        $this->assertSame($c['estados']['Lista para taller']->id, (int) $c['op']->fresh()->estado_op_id);
        $this->assertSame(4.0, (float) $c['componente']->fresh()->existencia_actual);
        $this->assertSame(0.0, (float) $c['producido']->fresh()->existencia_actual);
        $this->assertDatabaseCount('material_movimientos', 0);
    }

    /**
     * Un producto no tiene existencia propia: su inventario es la cantidad de
     * su distribución por talla, así que lo aprobado de cada talla recibida
     * debe coincidir con esa cantidad y quedar historiado.
     */
    public function test_al_recibir_un_producto_la_cantidad_entra_por_talla_y_deja_historial(): void
    {
        $c = $this->contextoProducto();

        $response = $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['Recibiendo']->id,
            'fecha_compromiso' => '2026-11-15',
            'recepciones' => [
                ['distribucion_id' => $c['tallaS']->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
                ['distribucion_id' => $c['tallaM']->id, 'cantidad_aprobada' => 1, 'cantidad_en_reparacion' => 1, 'cantidad_no_recuperable' => 0],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame(2, (int) $c['tallaS']->fresh()->cantidad_recibida_buena);
        $this->assertSame(1, (int) $c['tallaM']->fresh()->cantidad_recibida_buena);

        // Lo que quedó en reparación no es inventario: vive en la recepción.
        $this->assertDatabaseHas('recepcion_calidads', [
            'orden_produccion_id' => $c['op']->id,
            'talla' => 'M',
            'cantidad_en_reparacion' => 1,
        ]);

        // La distribución por tallas del producto es la misma fila: su cantidad
        // recibida cuadra con lo aprobado talla por talla.
        $recibidoPorTalla = $c['producto']->distribucionesPorTallas()
            ->get()
            ->pluck('cantidad_recibida_buena', 'talla')
            ->all();
        $this->assertSame(['S' => 2, 'M' => 1], $recibidoPorTalla);
        $this->assertSame(3, (int) $c['producto']->distribucionesPorTallas()->sum('cantidad_recibida_buena'));

        $this->assertSame(2, ProductoMovimiento::where('producto_id', $c['producto']->id)->count());
        $movimientoS = ProductoMovimiento::where('talla', 'S')->sole();
        $this->assertSame('entrada', $movimientoS->tipo_movimiento);
        $this->assertSame(2, (int) $movimientoS->cantidad);
        $this->assertSame(0, (int) $movimientoS->existencia_anterior);
        $this->assertSame(2, (int) $movimientoS->existencia_nueva);
        $this->assertSame($c['op']->id, (int) $movimientoS->orden_produccion_id);
        $this->assertSame($c['user']->id, (int) $movimientoS->user_id);
        $this->assertSame('Recepción de calidad — OP ' . $c['op']->numero_op, $movimientoS->motivo);
    }

    /**
     * La talla de un producto la comparten todas las OPs que la producen, así
     * que el histórico se lleva por OP: cada suma entra al inventario y la
     * corrección de una OP no toca lo que metió otra.
     */
    public function test_dos_ops_sobre_la_misma_talla_acumulan_y_no_se_pisan_el_historico(): void
    {
        $c = $this->contextoProducto();

        $op2 = OrdenProduccion::create([
            'numero_op' => 'OP-INV-003',
            'producto_referencia_id' => $c['producto']->id,
            'cantidad_planificada' => 3,
            'tipo' => 'taller',
            'estado_op_id' => $c['estados']['En taller']->id,
            'user_id' => $c['user']->id,
        ]);
        $op2->productos()->attach($c['producto']->id);
        $op2->distribucionesPorTallas()->attach($c['tallaS']->id);

        // La segunda OP planea 3 unidades sobre la misma fila de talla, así que
        // esa fila pasa a llevar su planificación: la recepción se valida
        // contra la cantidad esperada que está en la fila compartida.
        $c['tallaS']->update(['cantidad_planificada' => 3]);

        $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['Recibiendo']->id,
            'recepciones' => [
                ['distribucion_id' => $c['tallaS']->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
                ['distribucion_id' => $c['tallaM']->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $op2->id), [
            'estado_op_id' => $c['estados']['Recibiendo']->id,
            'recepciones' => [
                ['distribucion_id' => $c['tallaS']->id, 'cantidad_aprobada' => 3, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
            ],
        ])->assertSessionHasNoErrors();

        // 2 de la primera OP + 3 de la segunda.
        $this->assertSame(5, (int) $c['tallaS']->fresh()->cantidad_recibida_buena);
        $this->assertSame(2, ProductoMovimiento::unidadesDeOrden($c['producto']->id, 'S', $c['op']->id));
        $this->assertSame(3, ProductoMovimiento::unidadesDeOrden($c['producto']->id, 'S', $op2->id));

        $segundo = ProductoMovimiento::where('orden_produccion_id', $op2->id)->sole();
        $this->assertSame(2, (int) $segundo->existencia_anterior);
        $this->assertSame(5, (int) $segundo->existencia_nueva);
    }

    /**
     * La cantidad recibida también se puede digitar desde el editor de
     * distribución de la OP: ese ajuste deja su movimiento, no solo el cambio
     * de la fila.
     */
    public function test_ajustar_a_mano_la_cantidad_recibida_deja_su_movimiento(): void
    {
        $c = $this->contextoProducto();

        $response = $this->actingAs($c['user'])->put(route('OrdenProduccion.distribucion', $c['op']->id), [
            'distribucion_tallas' => [
                ['id' => $c['tallaS']->id, 'talla' => 'S', 'cantidad_recibida_buena' => 4, 'cantidad_pendiente_reparacion' => 0],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame(4, (int) $c['tallaS']->fresh()->cantidad_recibida_buena);

        $movimiento = ProductoMovimiento::where('talla', 'S')->sole();
        $this->assertSame('entrada', $movimiento->tipo_movimiento);
        $this->assertSame(4, (int) $movimiento->cantidad);
        $this->assertSame(0, (int) $movimiento->existencia_anterior);
        $this->assertSame(4, (int) $movimiento->existencia_nueva);
        $this->assertSame($c['op']->id, (int) $movimiento->orden_produccion_id);
    }

    /**
     * Reabrir la recepción de una OP de producto corrige la cantidad de la talla
     * en lugar de sumarla otra vez.
     */
    public function test_corregir_la_recepcion_de_un_producto_no_duplica_su_cantidad(): void
    {
        $c = $this->contextoProducto();

        $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['Recibiendo']->id,
            'fecha_compromiso' => '2026-11-15',
            'recepciones' => [
                ['distribucion_id' => $c['tallaS']->id, 'cantidad_aprobada' => 1, 'cantidad_en_reparacion' => 1, 'cantidad_no_recuperable' => 0],
                ['distribucion_id' => $c['tallaM']->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, (int) $c['tallaS']->fresh()->cantidad_recibida_buena);
        $this->assertSame($c['estados']['Recibiendo']->id, (int) $c['op']->fresh()->estado_op_id);

        $this->actingAs($c['user'])->put(route('OrdenProduccion.cambiarEstado', $c['op']->id), [
            'estado_op_id' => $c['estados']['Recibiendo']->id,
            'recepciones' => [
                ['distribucion_id' => $c['tallaS']->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
                ['distribucion_id' => $c['tallaM']->id, 'cantidad_aprobada' => 2, 'cantidad_en_reparacion' => 0, 'cantidad_no_recuperable' => 0],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame($c['estados']['Cerrada']->id, (int) $c['op']->fresh()->estado_op_id);
        $this->assertSame(2, (int) $c['tallaS']->fresh()->cantidad_recibida_buena);
        $this->assertSame(4, (int) $c['producto']->distribucionesPorTallas()->sum('cantidad_recibida_buena'));

        // La talla M no volvió a moverse (siguió en 2) y la S suma 2 netos en
        // dos movimientos de 1: la corrección ajustó, no duplicó.
        $this->assertSame(2, ProductoMovimiento::unidadesDeOrden($c['producto']->id, 'S', $c['op']->id));
        $this->assertSame(1, ProductoMovimiento::where('talla', 'M')->count());
    }

    /**
     * OP de un material procesado: consume `cantidad x consumo` del componente
     * y produce `cantidad` del material objetivo.
     *
     * @return array{user: User, op: OrdenProduccion, estados: array<string, EstadoOperacion>, componente: Material, producido: Material, distribucion: Distribucion_por_tallas}
     */
    private function contexto(string $estadoInicial): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $estados = [];
        foreach (self::FLUJO as $nombre => $puedePasarA) {
            $estados[$nombre] = EstadoOperacion::create([
                'nombre' => $nombre,
                'puede_pasar_a' => $puedePasarA,
            ]);
        }

        $taller = Taller::create(['nombre' => 'Taller de inventario', 'direccion' => 'Calle 30 # 40-50']);
        $estadoMaterial = EstadoOpMaterial::create(['nombre' => 'Pendiente', 'valor' => 'pendiente']);

        $componente = Material::create([
            'codigo_material' => 'MAT-COMP-INV',
            'categoria' => 'Tela',
            'nombre_base' => 'Tela consumida',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 100,
        ]);
        $componente->prices()->create(['precio' => 10, 'fecha' => now()->toDateString()]);

        $producido = Material::create([
            'codigo_material' => 'MAT-PROC-INV',
            'categoria' => 'Semielaborado',
            'nombre_base' => 'Material producido',
            'unidad_inventario' => 'u',
            'estado' => 'Activo',
            'material_procesado' => 1,
            'existencia_actual' => 0,
        ]);

        $ficha = FichaTecnica::create(['nombre' => 'Ficha MAT-PROC-INV']);
        $ficha->materiales()->create([
            'material_componente_id' => $componente->id,
            'consumo_estandar' => 2,
            'unidad_medida' => 'm',
            'costo_asociado_teorico' => 10,
        ]);
        $ficha->materialesProcesados()->attach($producido->id);

        $op = OrdenProduccion::create([
            'numero_op' => 'OP-INV-001',
            'cantidad_planificada' => 3,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'estado_op_id' => $estados[$estadoInicial]->id,
            'user_id' => $user->id,
            'estado_op_material_id' => $estadoMaterial->id,
        ]);
        $op->materiales()->attach($producido->id, ['cantidad' => 3]);

        $distribucion = Distribucion_por_tallas::create([
            'producto_id' => null,
            'material_id' => $producido->id,
            'talla' => null,
            'cantidad_planificada' => 3,
        ]);
        $op->distribucionesPorTallas()->attach($distribucion->id);

        KitProceso::create([
            'orden_produccion_id' => $op->id,
            'material_id' => $componente->id,
            'unidad' => 'm',
            'cantidad_requerida' => 6,
            'cantidad_disponible' => 100,
            'cantidad_preparada' => 6,
        ]);

        app(\App\Services\CostoMaterialOpService::class)->recalcular($op->fresh([
            'materiales.fichaTecnica.materiales',
            'distribucionesPorTallas',
            'costosMateriales',
        ]));

        return compact('user', 'op', 'estados', 'componente', 'producido', 'distribucion');
    }

    /**
     * OP de un producto con dos tallas. Las filas de distribución son las mismas
     * del producto (así se crean desde su plantilla), de modo que la cantidad por
     * talla es a la vez el plan de la OP y el inventario del producto.
     *
     * @return array{user: User, op: OrdenProduccion, producto: Producto, estados: array<string, EstadoOperacion>, tallaS: Distribucion_por_tallas, tallaM: Distribucion_por_tallas}
     */
    private function contextoProducto(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $estados = [];
        foreach (self::FLUJO as $nombre => $puedePasarA) {
            $estados[$nombre] = EstadoOperacion::create([
                'nombre' => $nombre,
                'puede_pasar_a' => $puedePasarA,
            ]);
        }

        $taller = Taller::create(['nombre' => 'Taller de producto', 'direccion' => 'Calle 50 # 60-70']);
        $estadoMaterial = EstadoOpMaterial::create(['nombre' => 'Pendiente', 'valor' => 'pendiente']);

        $producto = Producto::create([
            'Codigo_interno' => 'TEST-INV-PRODUCTO',
            'ID_producto_Shopify' => 'shopify-inv-producto',
            'Categoria_original' => 'Prueba',
            'Familia_normalizada' => 'Prueba',
            'Titulo_Shopify' => 'Producto de inventario',
            'Control_de_variante' => 'Única',
            'Opciones' => 'Única',
            'Estado' => 'Activo',
        ]);

        $op = OrdenProduccion::create([
            'numero_op' => 'OP-INV-002',
            'producto_referencia_id' => $producto->id,
            'cantidad_planificada' => 4,
            'tipo' => 'taller',
            'taller_id' => $taller->id,
            'estado_op_id' => $estados['En taller']->id,
            'user_id' => $user->id,
            'estado_op_material_id' => $estadoMaterial->id,
        ]);
        $op->productos()->attach($producto->id);

        $filas = [];
        foreach (['S' => 2, 'M' => 2] as $talla => $cantidad) {
            $distribucion = Distribucion_por_tallas::create([
                'grupo' => null,
                'producto_id' => $producto->id,
                'talla' => $talla,
                'cantidad_planificada' => $cantidad,
            ]);
            $producto->distribucionesPorTallas()->attach($distribucion->id);
            $op->distribucionesPorTallas()->attach($distribucion->id);
            $filas[$talla] = $distribucion;
        }

        return compact('user', 'op', 'producto', 'estados') + [
            'tallaS' => $filas['S'],
            'tallaM' => $filas['M'],
        ];
    }
}
