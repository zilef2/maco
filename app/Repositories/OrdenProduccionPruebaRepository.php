<?php

namespace App\Repositories;

use App\Models\OrdenProduccion;
use App\Models\Producto;
use App\Models\Tercero;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrdenProduccionPruebaRepository
{
    public function vaciarOrdenesProduccion(): void
    {
        Schema::disableForeignKeyConstraints();

        $tablasDependientes = [
            'cierre_orden_produccions',
            'compromisomaterials',
            'compromisos_reparacion',
            'compromisos_reparacions',
            'ficha_ensambles',
            'kit_procesos',
            'kit_tallers',
            'mermas',
            'orden_produccion_costos_cambios',
            'orden_produccion_costos_materiales',
            'orden_produccion_material',
            'orden_produccion_material_concreto',
            'orden_produccion_producto',
            'pendientes_produccions',
            'proceso_externos',
            'procesos_externos',
            'producto_movimientos',
            'recepcion_calidads',
            'distribucion_orden_produccion',
            'orden_produccions',
        ];

        foreach ($tablasDependientes as $tabla) {
            if (Schema::hasTable($tabla)) {
                DB::table($tabla)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    public function generarOrdenes(int $estadoOpId, int $cantidad = 2): array
    {
        $productoId = Producto::value('id');
        $tallerId = Tercero::where('tipo', 'Taller')->value('id');
        $userId = User::value('id');

        $creadas = [];
        for ($i = 1; $i <= $cantidad; $i++) {
            $numeroOp = 'OP-' . (100 + $i - 1);

            $op = OrdenProduccion::create([
                'numero_op' => $numeroOp,
                'producto_referencia_id' => $productoId,
                'cantidad_planificada' => 10,
                'costo_proceso' => 0,
                'costo_total' => 0,
                'fecha_prevista_envio_taller' => now()->toDateString(),
                'taller_id' => $tallerId,
                'tipo' => 'taller',
                'estado_op_id' => $estadoOpId,
                'user_id' => $userId,
            ]);

            if ($productoId) {
                $op->productos()->sync([$productoId]);
            }

            $creadas[] = $op;
        }

        return $creadas;
    }
}
