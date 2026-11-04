<?php

namespace Tests\Feature;

use App\Models\CierreOrdenProduccion;
use App\Models\EstadoOperacion;
use App\Models\MaterialMovimiento;
use App\Models\OrdenProduccion;
use App\Models\OrdenProduccionCostoMaterial;
use App\Models\RecepcionCalidad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Feature\Concerns\ContextoMateriales;

/**
 * KPIs del dashboard: valores numéricos simples (pares aprobados, costo de
 * materiales, OPs activas). Todos salen del bloque `mes`, que sólo cuenta el mes
 * en curso. Las series que llevan eje de tiempo se validan en
 * DashboardGraficasTest.
 */
class DashboardKpisTest extends TestCase
{
    use ContextoMateriales;
    use RefreshDatabase;

    /** Consecutivo para que cada OP tenga su numero_op único. */
    private int $secuencia = 0;

    private ?User $usuario = null;

    public function test_devuelve_los_kpis_del_mes_con_cero_cuando_no_ha_pasado_nada(): void
    {
        $response = $this->actingAs($this->usuario())
            ->getJson(route('dashboard.datos'))
            ->assertOk();

        $response->assertJsonStructure(['mes' => [
            'pares_recibidos',
            'pares_aprobados',
            'en_reparacion',
            'no_recuperables',
            'detalles_pares_recibidos',
            'detalles_pares_aprobados',
            'detalles_en_reparacion',
            'detalles_no_recuperables',
            'costo_materiales',
            'detalles_costo_materiales',
            'desviacion_costos',
            'detalles_desviacion_costos',
            'movimientos_entradas',
            'movimientos_salidas',
            'detalles_movimientos',
            'ops_activas',
            'ops_recibiendo',
            'detalles_ops_activas',
        ]]);

        $this->assertSame(0, $response->json('mes.pares_recibidos'));
        $this->assertSame(0, $response->json('mes.pares_aprobados'));
        $this->assertSame(0, $response->json('mes.en_reparacion'));
        $this->assertSame(0, $response->json('mes.no_recuperables'));
        $this->assertSame([], $response->json('mes.detalles_pares_recibidos'));
        $this->assertSame([], $response->json('mes.detalles_pares_aprobados'));
        $this->assertSame([], $response->json('mes.detalles_en_reparacion'));
        $this->assertSame([], $response->json('mes.detalles_no_recuperables'));
        $this->assertSame(0, $response->json('mes.costo_materiales'));
        $this->assertSame(0, $response->json('mes.desviacion_costos'));
        $this->assertSame(0, $response->json('mes.movimientos_entradas'));
        $this->assertSame(0, $response->json('mes.movimientos_salidas'));
        $this->assertSame(0, $response->json('mes.ops_activas'));
        $this->assertSame(0, $response->json('mes.ops_recibiendo'));
    }

    /**
     * Las cuatro cifras de calidad vienen de las recepciones del mes: se suman
     * por separado y una recepción de un mes anterior no entra.
     */
    public function test_las_cifras_de_calidad_suman_las_recepciones_del_mes_en_curso(): void
    {
        $op = $this->op($this->estado('Recibiendo'));

        $this->recepcion($op, ['recibida' => 10, 'aprobada' => 7, 'reparacion' => 2, 'no_recuperable' => 1]);
        $this->recepcion($op, ['recibida' => 5, 'aprobada' => 4, 'reparacion' => 1, 'no_recuperable' => 0]);
        // Mes anterior: el KPI es del mes en curso, así que se ignora.
        $this->recepcion($op, [
            'recibida' => 99,
            'aprobada' => 99,
            'reparacion' => 99,
            'no_recuperable' => 99,
            'fecha' => now()->startOfMonth()->subDay(),
        ]);

        $mes = $this->kpis();

        $this->assertSame(15, $mes['pares_recibidos']);
        $this->assertSame(11, $mes['pares_aprobados']);
        $this->assertSame(3, $mes['en_reparacion']);
        $this->assertSame(1, $mes['no_recuperables']);
    }

    /**
     * Una recepción todavía sin cantidades no puede reventar los KPIs: los
     * campos de calidad son nulos hasta que alguien los digita.
     */
    public function test_una_recepcion_sin_cantidades_digitaladas_no_rompe_los_kpis(): void
    {
        $op = $this->op($this->estado('Recibiendo'));

        RecepcionCalidad::create([
            'orden_produccion_id' => $op->id,
            'talla' => 'S',
            'cantidad_esperada' => 4,
            'cantidad_recibida' => 4,
            'cantidad_aprobada' => null,
            'cantidad_en_reparacion' => null,
            'cantidad_no_recuperable' => null,
            'fecha_recepcion' => now(),
        ]);

        $this->recepcion($op, ['recibida' => 6, 'aprobada' => 5, 'reparacion' => 1, 'no_recuperable' => 0]);

        $mes = $this->kpis();

        $this->assertSame(10, $mes['pares_recibidos']);
        $this->assertSame(5, $mes['pares_aprobados']);
        $this->assertSame(1, $mes['en_reparacion']);
    }

    public function test_devuelve_detalles_de_pares_recibidos_en_el_mes(): void
    {
        $op = $this->op($this->estado('Recibiendo'));
        $this->recepcion($op, ['recibida' => 8, 'aprobada' => 6, 'reparacion' => 1, 'no_recuperable' => 1]);

        $mes = $this->kpis();

        $this->assertNotEmpty($mes['detalles_pares_recibidos']);
        $this->assertSame(8, $mes['detalles_pares_recibidos'][0]['cantidad']);
        $this->assertArrayHasKey('producto', $mes['detalles_pares_recibidos'][0]);
        $this->assertArrayHasKey('op', $mes['detalles_pares_recibidos'][0]);
        $this->assertArrayHasKey('talla', $mes['detalles_pares_recibidos'][0]);
        $this->assertArrayHasKey('fecha', $mes['detalles_pares_recibidos'][0]);
    }

    /**
     * El costo de materiales es la suma de lo que quedó snapshotteado en las OPs
     * del mes, redondeada a pesos.
     */
    public function test_el_costo_de_materiales_suma_los_snapshot_del_mes_redondeados(): void
    {
        $material = $this->materialBase('KPI-COSTO');
        $op = $this->op($this->estado('En taller'));
        $otraOp = $this->op($this->estado('En taller'));

        $this->costoMaterial($op, $material, 1200.40);
        $this->costoMaterial($otraOp, $material, 300.00);
        // Compra del mes anterior: fuera del KPI.
        $this->costoMaterial($otraOp, $this->materialBase('KPI-COSTO-2'), 5000.00, now()->startOfMonth()->subDays(3));

        $this->assertSame(1500, $this->kpis()['costo_materiales']);
    }

    /**
     * La desviación es real menos planeado: un sobrecosto suma, un ahorro resta,
     * un cierre sin costo real no existe para el KPI y un cierre de otro mes
     * tampoco.
     */
    public function test_la_desviacion_de_costos_es_la_diferencia_real_menos_planeado_del_mes(): void
    {
        $estado = $this->estado('Cerrada');

        $encarecida = $this->op($estado, 1000);
        $barata = $this->op($estado, 500);
        $sinCostoReal = $this->op($estado, 800);
        $mesPasado = $this->op($estado, 700);

        $this->cierre($encarecida, 1250.00);
        $this->cierre($barata, 400.00);
        $this->cierre($sinCostoReal, null);
        $this->cierre($mesPasado, 2000.00, now()->startOfMonth()->subDay());

        // (1250 - 1000) + (400 - 500) = +150
        $this->assertSame(150, $this->kpis()['desviacion_costos']);
    }

    /**
     * Los KPIs de bodega cuentan movimientos, no unidades: dos entradas de
     * muchísimas unidades siguen siendo 2. Los ajustes no son entrada ni salida.
     */
    public function test_los_kpis_de_bodega_cuentan_movimientos_no_cantidades(): void
    {
        $material = $this->materialBase('KPI-BODEGA');

        $this->movimiento($material, 'entrada', 500);
        $this->movimiento($material, 'entrada', 25);
        $this->movimiento($material, 'salida', 300);
        $this->movimiento($material, 'ajuste', 7);
        $this->movimiento($material, 'entrada', 999, now()->startOfMonth()->subDay());

        $mes = $this->kpis();

        $this->assertSame(2, $mes['movimientos_entradas']);
        $this->assertSame(1, $mes['movimientos_salidas']);
    }

    /**
     * «OPs activas» es todo lo que no está cerrado, y «en Recibiendo» se muestra
     * aparte porque es la fila que alimenta los pares del mes.
     */
    public function test_las_ops_activas_excluyen_las_cerradas_y_marcan_las_que_estan_recibiendo(): void
    {
        $estados = [
            'Preparación' => $this->estado('Preparación'),
            'En taller' => $this->estado('En taller'),
            'Recibiendo' => $this->estado('Recibiendo'),
            'Cerrada' => $this->estado('Cerrada'),
        ];

        $this->op($estados['Preparación']);
        $this->op($estados['En taller']);
        $this->op($estados['En taller']);
        $this->op($estados['Recibiendo']);
        $this->op($estados['Cerrada']);
        $this->op($estados['Cerrada']);

        $mes = $this->kpis();

        $this->assertSame(4, $mes['ops_activas']);
        $this->assertSame(1, $mes['ops_recibiendo']);
    }

    private function usuario(): User
    {
        return $this->usuario ??= $this->usuarioAdmin();
    }

    /** @return array<string, int> */
    private function kpis(): array
    {
        return $this->actingAs($this->usuario())
            ->getJson(route('dashboard.datos'))
            ->assertOk()
            ->json('mes');
    }

    private function estado(string $nombre): EstadoOperacion
    {
        return $this->estadoOperacion($nombre);
    }

    private function op(EstadoOperacion $estado, float $costoTotal = 0): OrdenProduccion
    {
        return OrdenProduccion::create([
            'numero_op' => 'OP-KPI-' . (++$this->secuencia),
            'cantidad_planificada' => 1,
            'costo_total' => $costoTotal,
            'tipo' => 'taller',
            'estado_op_id' => $estado->id,
            'user_id' => $this->usuario()->id,
        ]);
    }

    /** @param array{recibida: int, aprobada: int, reparacion: int, no_recuperable: int, fecha?: \DateTimeInterface} $datos */
    private function recepcion(OrdenProduccion $op, array $datos): RecepcionCalidad
    {
        return RecepcionCalidad::create([
            'orden_produccion_id' => $op->id,
            'talla' => 'M',
            'cantidad_esperada' => $datos['recibida'],
            'cantidad_recibida' => $datos['recibida'],
            'cantidad_aprobada' => $datos['aprobada'],
            'cantidad_en_reparacion' => $datos['reparacion'],
            'cantidad_no_recuperable' => $datos['no_recuperable'],
            'fecha_recepcion' => $datos['fecha'] ?? now(),
        ]);
    }

    private function costoMaterial(
        OrdenProduccion $op,
        \App\Models\Material $material,
        float $costoTotal,
        ?\DateTimeInterface $creadoEn = null,
    ): OrdenProduccionCostoMaterial {
        $costo = OrdenProduccionCostoMaterial::create([
            'orden_produccion_id' => $op->id,
            'material_id' => $material->id,
            'metodo_costo' => 2,
            'precio_unitario' => $costoTotal,
            'cantidad' => 1,
            'costo_total' => $costoTotal,
            'user_id' => $this->usuario()->id,
        ]);

        if ($creadoEn !== null) {
            $costo->forceFill(['created_at' => $creadoEn])->save();
        }

        return $costo;
    }

    private function cierre(OrdenProduccion $op, ?float $costoReal, ?\DateTimeInterface $fechaCierre = null): CierreOrdenProduccion
    {
        return CierreOrdenProduccion::create([
            'orden_produccion_id' => $op->id,
            'responsable_id' => $this->usuario()->id,
            'costo_real_final' => $costoReal,
            'fecha_cierre' => $fechaCierre ?? now(),
        ]);
    }

    private function movimiento(\App\Models\Material $material, string $tipo, float $cantidad, ?\DateTimeInterface $creadoEn = null): MaterialMovimiento
    {
        $movimiento = MaterialMovimiento::create([
            'material_id' => $material->id,
            'tipo_movimiento' => $tipo,
            'cantidad' => $cantidad,
            'existencia_anterior' => 0,
            'existencia_nueva' => $cantidad,
        ]);

        if ($creadoEn !== null) {
            $movimiento->forceFill(['created_at' => $creadoEn])->save();
        }

        return $movimiento;
    }
}
