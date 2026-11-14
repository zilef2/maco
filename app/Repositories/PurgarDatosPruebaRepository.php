<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgarDatosPruebaRepository
{
    public function purgarTablas(): void
    {
        Schema::disableForeignKeyConstraints();

        $tablas = [
            'cierre_orden_produccions',
            'kit_procesos',
            'kit_tallers',
            'proceso_externos',
            'procesos_externos',
            'recepcion_calidads',
            'pendientes_produccions',
            'distribucion_orden_produccion',
            'distribucion_por_tallas',
            'distribucion_tallas',
            'orden_produccion_producto',
            'orden_produccion_costos_materiales',
            'costo_material_ops',
            'estado_op_materials',
            'mermas',
            'compromisos_reparacion',
            'compromisos_reparacions',
            'procesoop_producto',
            'procesoops',
            'orden_produccions',
            'material_movimientos',
            'material_prices',
            'material_variants',
            'ficha_tecnica_materiales',
            'ficha_ensamble_materials',
            'ficha_ensambles',
            'fichas_tecnicas',
            'materials',
            'ficha_tecnica_producto',
            'distribucion_producto',
            'producto_tallas_history',
            'producto_movimientos',
            'productos',
            'proveedores_maco',
        ];

        foreach ($tablas as $tabla) {
            if (Schema::hasTable($tabla)) {
                DB::table($tabla)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}

