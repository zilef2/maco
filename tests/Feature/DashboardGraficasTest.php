<?php

namespace Tests\Feature;

use App\Models\CierreOrdenProduccion;
use App\Models\EstadoOperacion;
use App\Models\Material;
use App\Models\MaterialMovimiento;
use App\Models\OrdenProduccion;
use App\Models\OrdenProduccionCostoMaterial;
use App\Models\RecepcionCalidad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Feature\Concerns\ContextoMateriales;

/**
 * Series de las gráficas del dashboard: cada bloque devuelve un eje (labels) y
 * una serie alineada por índice, con un punto por día o por mes según el rango
 * pedido. Aquí se valida el armado del eje y el filtrado por fecha; los números
 * simples del mes viven en DashboardKpisTest.
 */
class DashboardGraficasTest extends TestCase
{
    use ContextoMateriales;
    use RefreshDatabase;

    private int $secuencia = 0;

    private ?User $usuario = null;

    public function test_el_rango_normaliza_los_dias_pedidos_entre_siete_y_trescientos_sesenta_y_cinco(): void
    {
        $hoy = Carbon::today();

        $porDefecto = $this->datos();
        $this->assertSame(30, $porDefecto['periodo']['dias']);
        $this->assertSame($hoy->copy()->subDays(29)->toDateString(), $porDefecto['periodo']['desde']);
        $this->assertSame($hoy->toDateString(), $porDefecto['periodo']['hasta']);

        $this->assertSame(7, $this->datos(['dias' => 1])['periodo']['dias']);
        $this->assertSame(365, $this->datos(['dias' => 400])['periodo']['dias']);
        // Un valor ilegible se vuelve 0 al castear, así que cae en el mínimo.
        $this->assertSame(7, $this->datos(['dias' => 'abc'])['periodo']['dias']);
    }

    /**
     * Con un rango diario la gráfica tiene un punto exacto por día del rango,
     * aunque ese día no tenga movimientos.
     */
    public function test_la_serie_de_movimientos_dame_un_punto_por_dia_del_rango(): void
    {
        $material = $this->materialBase('GRAF-MOV');
        $hoy = Carbon::today();

        $estaTarde = $hoy->copy()->addHours(9);
        $haceTresDias = $hoy->copy()->subDays(3)->addHours(9);
        $fueraDelRango = $hoy->copy()->subDays(20);

        $this->movimiento($material, 'entrada', 40, $estaTarde);
        $this->movimiento($material, 'salida', 15, $estaTarde);
        $this->movimiento($material, 'entrada', 100, $haceTresDias);
        $this->movimiento($material, 'entrada', 999, $fueraDelRango);

        $movimientos = $this->datos(['dias' => 14])['movimientos'];

        $this->assertCount(14, $movimientos['labels']);
        $this->assertSame($hoy->copy()->subDays(13)->toDateString(), $movimientos['labels'][0]);
        $this->assertSame($hoy->toDateString(), $movimientos['labels'][13]);
        $this->assertCount(14, $movimientos['entradas']);
        $this->assertCount(14, $movimientos['salidas']);

        $posicion = array_search($haceTresDias->toDateString(), $movimientos['labels'], true);
        $this->assertIsInt($posicion);
        $this->assertSame(100, $movimientos['entradas'][$posicion]);
        $this->assertSame(0, $movimientos['salidas'][$posicion]);

        $posicionHoy = array_search($hoy->toDateString(), $movimientos['labels'], true);
        $this->assertSame(40, $movimientos['entradas'][$posicionHoy]);
        $this->assertSame(15, $movimientos['salidas'][$posicionHoy]);

        // Los días sin movimientos siguen en el eje, en cero, y lo antiguo no cuenta.
        $this->assertSame(0, $movimientos['entradas'][5]);
        $this->assertNotContains(999, $movimientos['entradas']);
    }

    /**
     * Al pasar de 90 días el eje deja de ser diario y pasa a ser mensual, con lo
     * cual el movimiento de hace dos meses cae en su mes, no en su día.
     */
    public function test_la_serie_pasa_a_meses_cuando_el_rango_pasa_de_noventa_dias(): void
    {
        $material = $this->materialBase('GRAF-MES');
        $haceSesentaDias = Carbon::today()->subDays(60)->addHours(8);
        $this->movimiento($material, 'salida', 250, $haceSesentaDias);

        $movimientos = $this->datos(['dias' => 120])['movimientos'];

        $this->assertSame($this->ejesMensales(120), $movimientos['labels']);
        foreach ($movimientos['labels'] as $label) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $label);
        }

        $posicion = array_search($haceSesentaDias->format('Y-m'), $movimientos['labels'], true);
        $this->assertIsInt($posicion);
        $this->assertSame(250, $movimientos['salidas'][$posicion]);
        $this->assertSame(array_fill(0, count($movimientos['labels']), 0), $movimientos['entradas']);
    }

    /**
     * La producción se grafica por fecha de recepción: las recepciones todavía
     * sin fecha (la OP recién entra a Recibiendo) no pueden aparecer en el eje.
     */
    public function test_la_serie_de_produccion_usa_la_fecha_de_recepcion_e_ignora_las_pendientes(): void
    {
        $op = $this->op($this->estado('Recibiendo'));
        $haceDosDias = Carbon::today()->subDays(2);

        $this->recepcion($op, ['recibida' => 5, 'aprobada' => 3, 'reparacion' => 1, 'no_recuperable' => 1, 'fecha' => $haceDosDias]);
        $this->recepcion($op, ['recibida' => 2, 'aprobada' => 2, 'reparacion' => 0, 'no_recuperable' => 0, 'fecha' => $haceDosDias->copy()->addHours(12)]);
        $this->recepcion($op, ['recibida' => 7, 'aprobada' => 7, 'reparacion' => 0, 'no_recuperable' => 0, 'fecha' => null]);

        $produccion = $this->datos(['dias' => 30])['produccion'];

        $this->assertCount(30, $produccion['labels']);
        $this->assertCount(30, $produccion['aprobadas']);
        $this->assertCount(30, $produccion['reparacion']);
        $this->assertCount(30, $produccion['no_recuperables']);

        $posicion = array_search($haceDosDias->toDateString(), $produccion['labels'], true);
        $this->assertIsInt($posicion);
        $this->assertSame(5, $produccion['aprobadas'][$posicion]);
        $this->assertSame(1, $produccion['reparacion'][$posicion]);
        $this->assertSame(1, $produccion['no_recuperables'][$posicion]);

        // Lo de la recepción pendiente (7) no se grafica en ningún mes del rango.
        $this->assertSame(5, array_sum($produccion['aprobadas']));
    }

    /**
     * La dona de existencias agrupa por categoría, suma unidades y materiales,
     * mete las categorías vacías en «Sin categoría», ordena de mayor a menor y
     * deja fuera lo inactivo.
     */
    public function test_las_existencias_se_agrupan_por_categoria_de_mayor_a_menor(): void
    {
        $this->materialBase('GRAF-T1', null, ['categoria' => 'Tela', 'existencia_actual' => 100]);
        $this->materialBase('GRAF-T2', null, ['categoria' => 'Tela', 'existencia_actual' => 50]);
        $this->materialBase('GRAF-H1', null, ['categoria' => 'Hilo', 'existencia_actual' => 500]);
        $this->materialBase('GRAF-V1', null, ['categoria' => '', 'existencia_actual' => 20]);
        $this->materialBase('GRAF-X1', null, ['categoria' => 'Hilo', 'existencia_actual' => 9999, 'estado' => 'Inactivo']);

        $this->assertSame([
            ['categoria' => 'Hilo', 'materiales' => 1, 'unidades' => 500],
            ['categoria' => 'Tela', 'materiales' => 2, 'unidades' => 150],
            ['categoria' => 'Sin categoría', 'materiales' => 1, 'unidades' => 20],
        ], $this->datos()['existencias']);
    }

    /**
     * La gráfica mixta de compras usa meses: el costo snapshotteado en las OPs y
     * el promedio de los precios registrados caen en el mismo eje, y un mes sin
     * datos sigue apareciendo en cero.
     */
    public function test_la_grafica_de_compras_alinea_meses_costos_y_precio_promedio(): void
    {
        $mesActual = Carbon::today()->format('Y-m');
        $mesAnterior = Carbon::today()->startOfMonth()->subDay()->format('Y-m');

        $op = $this->op($this->estado('En taller'));
        $otraOp = $this->op($this->estado('En taller'));
        $material = $this->materialBase('GRAF-COMPRA');

        $this->costoMaterial($op, $material, 900.00);
        $this->costoMaterial($otraOp, $material, 100.00);

        $material->prices()->create(['precio' => 100, 'fecha' => Carbon::today()->toDateString()]);
        $material->prices()->create(['precio' => 200, 'fecha' => Carbon::today()->toDateString()]);
        $material->prices()->create(['precio' => 50, 'fecha' => $mesAnterior . '-28']);

        $compras = $this->datos(['dias' => 120])['compras'];

        $this->assertSame($this->ejesMensales(120), $compras['labels']);
        $this->assertCount(count($compras['labels']), $compras['costos']);
        $this->assertCount(count($compras['labels']), $compras['precio_promedio']);

        $actual = array_search($mesActual, $compras['labels'], true);
        $anterior = array_search($mesAnterior, $compras['labels'], true);
        $vacio = array_values(array_filter(
            $compras['labels'],
            fn (string $mes) => $mes !== $mesActual && $mes !== $mesAnterior,
        ))[0] ?? null;
        $this->assertIsInt($actual);
        $this->assertIsInt($anterior);
        $this->assertIsString($vacio);

        $this->assertSame(1000, $compras['costos'][$actual]);
        $this->assertSame(150.0, (float) $compras['precio_promedio'][$actual]);

        $posicionVacia = array_search($vacio, $compras['labels'], true);
        $this->assertSame(0, $compras['costos'][$posicionVacia]);
        $this->assertSame(0.0, (float) $compras['precio_promedio'][$posicionVacia]);

        // En el mes anterior solo hubo un precio registrado, sin compras de OP.
        $this->assertSame(0, $compras['costos'][$anterior]);
        $this->assertSame(50.0, (float) $compras['precio_promedio'][$anterior]);
    }

    /**
     * La gráfica de desviaciones toma a lo más 12 cierres, del más reciente al
     * más antiguo, y solo los que ya tienen costo real. Un cierre sin fecha
     * (borrador) sí entra, porque ahí no se filtra por fecha de cierre.
     */
    public function test_la_grafica_de_desviaciones_toma_los_doce_cierres_mas_recentes_con_costo_real(): void
    {
        $estado = $this->estado('Cerrada');

        // Doce cierres comparables, en orden de creación.
        $recientes = [];
        for ($i = 1; $i <= 12; $i++) {
            $op = $this->op($estado, 100 * $i);
            $this->cierre($op, 100.00 * $i + 10);
            $recientes[] = $op->numero_op;
        }

        // El decimotercero es el más nuevo: desplaza al más antiguo.
        $masNuevo = $this->op($estado, 500);
        $this->cierre($masNuevo, 520);

        // Cierre sin costo real final: todavía no es comparable.
        $sinCostoReal = $this->op($estado, 600);
        $this->cierre($sinCostoReal, null);

        // Cierre cerrado hace 300 días: fuera del rango de 120 días.
        $fueraDelRango = $this->op($estado, 700);
        $this->cierre($fueraDelRango, 710, ['fecha_cierre' => Carbon::today()->subDays(300)]);

        // Cierre sin fecha: entra primero, aunque no tenga fecha de cierre.
        $borrador = $this->op($estado, 800);
        $this->cierre($borrador, 820, ['fecha_cierre' => null]);

        $desviaciones = $this->datos(['dias' => 120])['desviaciones'];

        $this->assertCount(12, $desviaciones['ops']);
        $this->assertCount(12, $desviaciones['planeado']);
        $this->assertCount(12, $desviaciones['real']);

        $this->assertSame($borrador->numero_op, $desviaciones['ops'][0]);
        $this->assertSame($masNuevo->numero_op, $desviaciones['ops'][1]);
        $this->assertSame([800, 500], array_slice($desviaciones['planeado'], 0, 2));
        $this->assertSame([820, 520], array_slice($desviaciones['real'], 0, 2));

        $this->assertContains($recientes[11], $desviaciones['ops']);
        $this->assertNotContains($recientes[0], $desviaciones['ops']);
        $this->assertNotContains($sinCostoReal->numero_op, $desviaciones['ops']);
        $this->assertNotContains($fueraDelRango->numero_op, $desviaciones['ops']);
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    private function datos(array $query = []): array
    {
        return $this->actingAs($this->usuario())
            ->getJson(route('dashboard.datos', $query))
            ->assertOk()
            ->json();
    }

    /**
     * Eje mensual que corresponde al rango pedido: desde el mes del día más
     * antiguo del rango hasta el mes actual.
     *
     * @return string[]
     */
    private function ejesMensales(int $dias): array
    {
        $labels = [];
        $cursor = Carbon::today()->subDays($dias - 1)->startOfMonth();
        while ($cursor <= now()) {
            $labels[] = $cursor->format('Y-m');
            $cursor->addMonthNoOverflow();
        }

        return $labels;
    }

    private function usuario(): User
    {
        return $this->usuario ??= $this->usuarioAdmin();
    }

    private function estado(string $nombre): EstadoOperacion
    {
        return $this->estadoOperacion($nombre);
    }

    private function op(EstadoOperacion $estado, float $costoTotal = 0): OrdenProduccion
    {
        return OrdenProduccion::create([
            'numero_op' => 'OP-GRAF-' . (++$this->secuencia),
            'cantidad_planificada' => 1,
            'costo_total' => $costoTotal,
            'tipo' => 'taller',
            'estado_op_id' => $estado->id,
            'user_id' => $this->usuario()->id,
        ]);
    }

    private function movimiento(Material $material, string $tipo, float $cantidad, Carbon $creadoEn): MaterialMovimiento
    {
        $movimiento = MaterialMovimiento::create([
            'material_id' => $material->id,
            'tipo_movimiento' => $tipo,
            'cantidad' => $cantidad,
            'existencia_anterior' => 0,
            'existencia_nueva' => $cantidad,
        ]);

        $movimiento->forceFill(['created_at' => $creadoEn])->save();

        return $movimiento;
    }

    /** @param array{recibida: int, aprobada: int, reparacion: int, no_recuperable: int, fecha?: \DateTimeInterface|null} $datos */
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
            'fecha_recepcion' => $datos['fecha'] ?? null,
        ]);
    }

    private function costoMaterial(OrdenProduccion $op, Material $material, float $costoTotal): OrdenProduccionCostoMaterial
    {
        return OrdenProduccionCostoMaterial::create([
            'orden_produccion_id' => $op->id,
            'material_id' => $material->id,
            'metodo_costo' => 2,
            'precio_unitario' => $costoTotal,
            'cantidad' => 1,
            'costo_total' => $costoTotal,
            'user_id' => $this->usuario()->id,
        ]);
    }

    /** @param array<string, mixed> $atributos */
    private function cierre(OrdenProduccion $op, ?float $costoReal, array $atributos = []): CierreOrdenProduccion
    {
        return CierreOrdenProduccion::create(array_replace([
            'orden_produccion_id' => $op->id,
            'responsable_id' => $this->usuario()->id,
            'costo_real_final' => $costoReal,
            'fecha_cierre' => now(),
        ], $atributos));
    }
}
