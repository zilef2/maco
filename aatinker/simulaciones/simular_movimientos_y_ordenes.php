<?php

use App\Models\EstadoOperacion;
use App\Models\Material;
use App\Models\MaterialMovimiento;
use App\Models\OrdenProduccion;
use App\Models\Producto;
use App\Models\ProductoMovimiento;
use App\Models\Tercero;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {

    # primero los parametros
    $dias = 10;
    $tallas = ['S', 'M', 'L', 'XL', '36', '38', '40'];



    $userId = User::query()->value('id');
    $materiales = Material::query()->where('generico', false)->limit(15)->get();
    if ($materiales->isEmpty()) {
        $materiales = Material::query()->limit(15)->get();
    }

    $productos = Producto::query()
        ->whereHas('fichaTecnica')
        ->whereHas('procesos')
        ->get();

    $materialesSuficientes = Material::query()
        ->where('generico', false)
        ->where('existencia_actual', '>=', 10)
        ->get();

    $estados = EstadoOperacion::query()->orderBy('id')->get();
    $estadoListaParaTaller = $estados->first(
        fn ($e) => mb_strtolower(trim((string) $e->nombre)) === 'lista para taller'
    );
    $estadosPosterioresIds = $estadoListaParaTaller
        ? $estados->where('id', '>', $estadoListaParaTaller->id)->pluck('id')->all()
        : [];
    $estadosOp = $estados->pluck('id')->all();
    $talleres = Tercero::query()->where('tipo', 'Taller')->pluck('id')->all();

    $puedeGenerarOps = $productos->isNotEmpty() && $materialesSuficientes->isNotEmpty() && !empty($estadosOp);

    if ($materiales->isEmpty() && !$puedeGenerarOps) {
        echo "No hay materiales ni productos válidos (con ficha técnica y al menos un proceso) para simular.\n";
        return;
    }

    $motivosMaterial = [
        'entrada' => ['Compra a proveedor', 'Ajuste de inventario', 'Devolución de taller'],
        'salida' => ['Consumo en producción', 'Entrega a taller', 'Ajuste por merma'],
    ];
    $motivosProducto = [
        'entrada' => ['Recepción de producción', 'Devolución de cliente', 'Ajuste de inventario'],
        'salida' => ['Despacho de venta', 'Muestra comercial', 'Ajuste por merma'],
    ];

    $creadosMaterialMov = 0;
    $creadosProductoMov = 0;
    $creadasOrdenes = 0;
    $codigosOps = [];

    for ($d = $dias - 1; $d >= 0; $d--) {
        $fechaBase = Carbon::now()->subDays($d)->startOfDay();

        // 1. Movimientos de materiales (3 a 6 por día)
        if ($materiales->isNotEmpty()) {
            $cantidadMovs = rand(3, 6);
            for ($i = 0; $i < $cantidadMovs; $i++) {
                $material = $materiales->random();
                $fecha = $fechaBase->copy()->addHours(rand(7, 19))->addMinutes(rand(0, 59));

                $anterior = (float) ($material->existencia_actual ?? 0);
                $esEntrada = $anterior <= 5 ? true : (bool) rand(0, 1);
                $cantidad = $esEntrada ? rand(5, 40) : rand(1, max(1, (int) floor($anterior * 0.4)));
                $nueva = $esEntrada ? $anterior + $cantidad : max(0, $anterior - $cantidad);

                if ($nueva === $anterior) {
                    continue;
                }

                $tipo = $esEntrada ? 'entrada' : 'salida';
                $mov = new MaterialMovimiento([
                    'material_id' => $material->id,
                    'tipo_movimiento' => $tipo,
                    'cantidad' => abs($nueva - $anterior),
                    'existencia_anterior' => $anterior,
                    'existencia_nueva' => $nueva,
                    'motivo' => $motivosMaterial[$tipo][array_rand($motivosMaterial[$tipo])] . ' (simulación)',
                    'user_id' => $userId,
                ]);
                $mov->created_at = $fecha;
                $mov->updated_at = $fecha;
                $mov->save();

                $material->existencia_actual = $nueva;
                $material->saveQuietly();

                $creadosMaterialMov++;
            }
        }

        // 2. Órdenes de producción (1 a 3 por día)
        if ($puedeGenerarOps) {
            $cantidadOps = rand(1, 3);
            for ($i = 0; $i < $cantidadOps; $i++) {
                $producto = $productos->random();
                $fecha = $fechaBase->copy()->addHours(rand(8, 17))->addMinutes(rand(0, 59));
                $estadoId = $estadosOp[array_rand($estadosOp)];
                $esPosterior = in_array($estadoId, $estadosPosterioresIds, true);

                $maxExistencia = (int) floor((float) $materialesSuficientes->max('existencia_actual'));
                $cantidadPlanificada = $esPosterior
                    ? rand(1, max(1, min(30, $maxExistencia)))
                    : rand(10, 60);

                if ($esPosterior && $materialesSuficientes->where('existencia_actual', '>=', $cantidadPlanificada)->isEmpty()) {
                    continue;
                }

                $costoProceso = rand(50000, 250000);

                $op = new OrdenProduccion([
                    'numero_op' => OrdenProduccion::siguienteNumeroOp(),
                    'producto_referencia_id' => $producto->id,
                    'cantidad_planificada' => $cantidadPlanificada,
                    'costo_proceso' => $costoProceso,
                    'costo_total' => $costoProceso + rand(20000, 100000),
                    'fecha_prevista_envio_taller' => $fecha->copy()->addDays(rand(1, 5))->toDateString(),
                    'taller_id' => !empty($talleres) ? $talleres[array_rand($talleres)] : null,
                    'tipo' => 'produccion',
                    'estado_op_id' => $estadoId,
                    'externa' => (bool) rand(0, 1),
                    'user_id' => $userId,
                    'observaciones' => 'Orden generada por simulación',
                ]);
                $op->created_at = $fecha;
                $op->updated_at = $fecha;
                $op->save();
                $op->productos()->attach($producto->id);

                $creadasOrdenes++;
                $codigosOps[] = $op->numero_op;

                // 3. Movimientos de producto asociados o adicionales (2 a 4 por día)
                $talla = $tallas[array_rand($tallas)];
                $antProd = rand(0, 30);
                $esEntProd = (bool) rand(0, 1);
                $cantProd = rand(2, 15);
                $nvaProd = $esEntProd ? $antProd + $cantProd : max(0, $antProd - $cantProd);

                if ($nvaProd !== $antProd) {
                    $tipoProd = $nvaProd > $antProd ? 'entrada' : 'salida';
                    $movProd = new ProductoMovimiento([
                        'producto_id' => $producto->id,
                        'talla' => $talla,
                        'orden_produccion_id' => $op->id,
                        'tipo_movimiento' => $tipoProd,
                        'cantidad' => abs($nvaProd - $antProd),
                        'existencia_anterior' => $antProd,
                        'existencia_nueva' => $nvaProd,
                        'motivo' => $motivosProducto[$tipoProd][array_rand($motivosProducto[$tipoProd])] . ' (simulación)',
                        'user_id' => $userId,
                    ]);
                    $movProd->created_at = $fecha;
                    $movProd->updated_at = $fecha;
                    $movProd->save();

                    $creadosProductoMov++;
                }
            }
        }
    }

    echo "Simulación completada ({$dias} días):\n";
    echo "- Movimientos de material creados: {$creadosMaterialMov}\n";
    echo "- Movimientos de producto creados: {$creadosProductoMov}\n";
    echo "- Órdenes de producción creadas: {$creadasOrdenes}\n";
    if (!empty($codigosOps)) {
        echo "- Códigos de OP generadas: " . implode(', ', $codigosOps) . "\n";
    }
});
