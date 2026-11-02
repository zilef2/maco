<?php

namespace App\Http\Controllers;

use App\Models\CierreOrdenProduccion;
use App\Models\MaterialMovimiento;
use App\Models\OrdenProduccion;
use App\Models\OrdenProduccionCostoMaterial;
use App\Models\RecepcionCalidad;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Datos agregados del dashboard (movimientos, semana de OPs, compras,
     * producción y calidad) para un período en días.
     */
    public function datos(Request $request)
    {
        $dias = min(max((int) $request->query('dias', 30), 7), 365);
        $desde = now()->startOfDay()->subDays($dias - 1);
        $diario = $dias <= 90;

        return response()->json([
            'periodo' => [
                'dias' => $dias,
                'desde' => $desde->toDateString(),
                'hasta' => now()->toDateString(),
            ],
            'movimientos' => $this->movimientos($desde, $diario),
            'semana' => $this->semanaOp(),
            'produccion' => $this->produccion($desde, $diario),
            'compras' => $this->compras($desde),
            'recepcionCalidadPorOp' => $this->recepcionCalidadPorOp($desde),
            'mes' => $this->resultadosMes(),
            'generado_en' => now()->format('H:i:s'),
        ]);
    }

    /**
     * Serie de movimientos de materiales (entradas vs salidas) por día o mes.
     *
     * @return array{labels: string[], entradas: int[], salidas: int[]}
     */
    private function movimientos(Carbon $desde, bool $diario): array
    {
        $fila = $diario ? 'DATE(created_at)' : "DATE_FORMAT(created_at, '%Y-%m')";

        $filas = MaterialMovimiento::query()
            ->where('created_at', '>=', $desde)
            ->selectRaw("{$fila} AS periodo,
                SUM(CASE WHEN tipo_movimiento = 'entrada' THEN cantidad ELSE 0 END) AS entradas,
                SUM(CASE WHEN tipo_movimiento = 'salida' THEN cantidad ELSE 0 END) AS salidas")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get()
            ->keyBy('periodo');

        $labels = $this->ejes($desde, $diario);
        $entradas = [];
        $salidas = [];
        foreach ($labels as $label) {
            $entradas[] = (int) ($filas[$label]->entradas ?? 0);
            $salidas[] = (int) ($filas[$label]->salidas ?? 0);
        }

        return ['labels' => $labels, 'entradas' => $entradas, 'salidas' => $salidas];
    }

    /**
     * Plata de las OPs creadas cada día de la semana en curso (lunes a domingo).
     *
     * @return array{labels: string[], hoy: int[], costos: int[], ops: int[], total: int}
     */
    private function semanaOp(): array
    {
        $inicio = now()->startOfWeek(Carbon::MONDAY);
        $fin = $inicio->copy()->endOfDay()->addDays(6);

        $filas = OrdenProduccion::query()
            ->where('created_at', '>=', $inicio)
            ->where('created_at', '<', $fin)
            ->selectRaw('DATE(created_at) AS dia, ROUND(SUM(costo_total)) AS plata, COUNT(*) AS ops')
            ->groupBy('dia')
            ->pluck('plata', 'dia');

        $conteo = OrdenProduccion::query()
            ->where('created_at', '>=', $inicio)
            ->where('created_at', '<', $fin)
            ->selectRaw('DATE(created_at) AS dia, COUNT(*) AS ops')
            ->groupBy('dia')
            ->pluck('ops', 'dia');

        $nombres = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $labels = [];
        $costos = [];
        $ops = [];
        $total = 0;

        $cursor = $inicio->copy();
        for ($i = 0; $i < 7; $i++) {
            $dia = $cursor->toDateString();
            $labels[] = $nombres[$i].' '.$cursor->format('d/m');
            $costos[] = (int) ($filas[$dia] ?? 0);
            $ops[] = (int) ($conteo[$dia] ?? 0);
            $total += $costos[$i];
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'hoy' => now()->dayOfWeekIso - 1,
            'costos' => $costos,
            'ops' => $ops,
            'total' => $total,
        ];
    }

    /**
     * Producción recibida en calidad (aprobada, en reparación, no recuperable).
     *
     * @return array{labels: string[], aprobadas: int[], reparacion: int[], no_recuperables: int[]}
     */
    private function produccion(Carbon $desde, bool $diario): array
    {
        $fila = $diario ? 'DATE(fecha_recepcion)' : "DATE_FORMAT(fecha_recepcion, '%Y-%m')";

        $filas = RecepcionCalidad::query()
            ->whereNotNull('fecha_recepcion')
            ->where('fecha_recepcion', '>=', $desde)
            ->selectRaw("{$fila} AS periodo,
                COALESCE(SUM(cantidad_aprobada), 0) AS aprobadas,
                COALESCE(SUM(cantidad_en_reparacion), 0) AS reparacion,
                COALESCE(SUM(cantidad_no_recuperable), 0) AS no_recuperables")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get()
            ->keyBy('periodo');

        $labels = $this->ejes($desde, $diario);
        $aprobadas = [];
        $reparacion = [];
        $noRecuperables = [];
        foreach ($labels as $label) {
            $aprobadas[] = (int) ($filas[$label]->aprobadas ?? 0);
            $reparacion[] = (int) ($filas[$label]->reparacion ?? 0);
            $noRecuperables[] = (int) ($filas[$label]->no_recuperables ?? 0);
        }

        return [
            'labels' => $labels,
            'aprobadas' => $aprobadas,
            'reparacion' => $reparacion,
            'no_recuperables' => $noRecuperables,
        ];
    }

    /**
     * Costo de materiales asignado a OPs (compras) por mes, con el promedio
     * de precio de compra registrado en el mismo mes.
     *
     * @return array{labels: string[], costos: int[], precio_promedio: float[]}
     */
    private function compras(Carbon $desde): array
    {
        $inicioMes = $desde->copy()->startOfMonth();
        $meses = [];
        $cursor = $inicioMes->copy();
        while ($cursor <= now()) {
            $meses[] = $cursor->format('Y-m');
            $cursor->addMonthNoOverflow();
        }

        $costos = OrdenProduccionCostoMaterial::query()
            ->where('created_at', '>=', $inicioMes)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS periodo, ROUND(SUM(costo_total)) AS total, COUNT(DISTINCT orden_produccion_id) AS ops")
            ->groupBy('periodo')
            ->pluck('total', 'periodo');

        $precios = DB::table('material_prices')
            ->where('fecha', '>=', $inicioMes->toDateString())
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') AS periodo, ROUND(AVG(precio), 2) AS promedio")
            ->groupBy('periodo')
            ->pluck('promedio', 'periodo');

        return [
            'labels' => $meses,
            'costos' => array_map(fn ($m) => (int) ($costos[$m] ?? 0), $meses),
            'precio_promedio' => array_map(fn ($m) => (float) ($precios[$m] ?? 0), $meses),
        ];
    }

    /**
     * Recepción por OP: esperado, recibido y no recuperable.
     *
     * @return array{ops: string[], esperada: int[], recibida: int[], no_recuperable: int[]}
     */
    private function recepcionCalidadPorOp(Carbon $desde): array
    {
        $filas = RecepcionCalidad::query()
            ->whereHas('ordenProduccion', fn ($q) => $q->where('fecha_recepcion', '>=', $desde))
            ->selectRaw('orden_produccion_id AS op_id,
                COALESCE(SUM(cantidad_esperada), 0) AS esperada,
                COALESCE(SUM(cantidad_recibida), 0) AS recibida,
                COALESCE(SUM(cantidad_no_recuperable), 0) AS no_recuperable')
            ->groupBy('orden_produccion_id')
            ->orderByRaw('MAX(fecha_recepcion) DESC')
            ->get();

        $ops = $filas->pluck('op_id')->all();

        // Mapear op_id a numero_op de la OP relacionada
        $opsNumeros = OrdenProduccion::whereIn('id', $filas->pluck('op_id'))
            ->pluck('numero_op', 'id')
            ->toArray();

        $opsEtiquetas = array_map(fn ($id) => $opsNumeros[$id] ?? 'OP-' . $id, $ops);

        return [
            'ops' => $opsEtiquetas,
            'esperada' => $filas->map(fn ($f) => (int) $f->esperada)->all(),
            'recibida' => $filas->map(fn ($f) => (int) $f->recibida)->all(),
            'no_recuperable' => $filas->map(fn ($f) => (int) $f->no_recuperable)->all(),
        ];
    }

    /**
     * KPIs de resultados del mes en curso.
     */
    private function resultadosMes(): array
    {
        $inicioMes = now()->startOfMonth();

        $recepciones = RecepcionCalidad::query()
            ->where('fecha_recepcion', '>=', $inicioMes)
            ->selectRaw('COALESCE(SUM(cantidad_recibida), 0) AS recibidos,
                COALESCE(SUM(cantidad_aprobada), 0) AS aprobados,
                COALESCE(SUM(cantidad_en_reparacion), 0) AS en_reparacion,
                COALESCE(SUM(cantidad_no_recuperable), 0) AS no_recuperables')
            ->first();

        $compras = OrdenProduccionCostoMaterial::query()
            ->where('created_at', '>=', $inicioMes)
            ->selectRaw('ROUND(SUM(costo_total)) AS total')->value('total');

        $desviacion = CierreOrdenProduccion::query()
            ->join('orden_produccions', 'orden_produccions.id', '=', 'cierre_orden_produccions.orden_produccion_id')
            ->where('cierre_orden_produccions.fecha_cierre', '>=', $inicioMes)
            ->whereNotNull('cierre_orden_produccions.costo_real_final')
            ->selectRaw('ROUND(SUM(cierre_orden_produccions.costo_real_final - orden_produccions.costo_total)) AS delta')
            ->value('delta');

        $movimientos = MaterialMovimiento::query()
            ->where('created_at', '>=', $inicioMes)
            ->selectRaw("SUM(CASE WHEN tipo_movimiento = 'entrada' THEN 1 ELSE 0 END) AS entradas,
                SUM(CASE WHEN tipo_movimiento = 'salida' THEN 1 ELSE 0 END) AS salidas")
            ->first();

        $opsActivas = OrdenProduccion::query()
            ->whereHas('estadoOp', fn ($q) => $q->where('nombre', '!=', 'Cerrada'))
            ->count();

        $recibiendo = OrdenProduccion::query()
            ->whereHas('estadoOp', fn ($q) => $q->where('nombre', 'Recibiendo'))
            ->count();

        return [
            'pares_recibidos' => (int) ($recepciones->recibidos ?? 0),
            'pares_aprobados' => (int) ($recepciones->aprobados ?? 0),
            'en_reparacion' => (int) ($recepciones->en_reparacion ?? 0),
            'no_recuperables' => (int) ($recepciones->no_recuperables ?? 0),
            'costo_materiales' => (int) ($compras ?? 0),
            'desviacion_costos' => (int) ($desviacion ?? 0),
            'movimientos_entradas' => (int) ($movimientos->entradas ?? 0),
            'movimientos_salidas' => (int) ($movimientos->salidas ?? 0),
            'ops_activas' => $opsActivas,
            'ops_recibiendo' => $recibiendo,
        ];
    }

    /**
     * Etiquetas del eje (un día o un mes por posición) hasta hoy.
     *
     * @return string[]
     */
    private function ejes(Carbon $desde, bool $diario): array
    {
        $labels = [];
        $cursor = $desde->copy();
        if ($diario) {
            while ($cursor <= now()) {
                $labels[] = $cursor->toDateString();
                $cursor->addDay();
            }
        } else {
            $cursor->startOfMonth();
            while ($cursor <= now()) {
                $labels[] = $cursor->format('Y-m');
                $cursor->addMonthNoOverflow();
            }
        }

        return $labels;
    }
}
