<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La ficha técnica toma la unidad y el costo unitario del material.
     * Se agrega un índice para obtener el último precio y se completan las
     * líneas existentes que todavía no tenían esos datos.
     */
    public function up(): void
    {
        Schema::table('material_prices', function (Blueprint $table) {
            $table->index(
                ['material_id', 'fecha', 'id'],
                'material_prices_latest_lookup_index'
            );
        });

        if (!Schema::hasTable('ficha_tecnica_materiales')) {
            return;
        }

        $unidades = DB::table('materials')->pluck('unidad_inventario', 'id');

        $ultimosPrecios = DB::table('material_prices')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['material_id', 'precio'])
            ->unique('material_id')
            ->keyBy('material_id');

        DB::table('ficha_tecnica_materiales')
            ->select(['id', 'material_componente_id', 'unidad_medida', 'costo_asociado_teorico'])
            ->orderBy('id')
            ->chunkById(500, function ($filas) use ($unidades, $ultimosPrecios) {
                foreach ($filas as $fila) {
                    $cambios = [];

                    $unidad = $unidades[$fila->material_componente_id] ?? null;
                    if ($unidad !== null && $unidad !== '' && $fila->unidad_medida !== $unidad) {
                        $cambios['unidad_medida'] = $unidad;
                    }

                    // El costo teórico vigente es el último precio del material.
                    $precio = $ultimosPrecios[$fila->material_componente_id] ?? null;
                    if ($precio && (float) $fila->costo_asociado_teorico !== (float) $precio->precio) {
                        $cambios['costo_asociado_teorico'] = $precio->precio;
                    }

                    if (!empty($cambios)) {
                        DB::table('ficha_tecnica_materiales')
                            ->where('id', $fila->id)
                            ->update($cambios);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('material_prices', function (Blueprint $table) {
            $table->dropIndex('material_prices_latest_lookup_index');
        });
    }
};
