<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La distribución de tallas queda ligada a su producto, para que la
     * recepción de calidad sepa qué producto tiene cada talla sin preguntarlo.
     */
    public function up(): void
    {
        Schema::table('distribucion_por_tallas', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('grupo')->constrained('productos')->nullOnDelete();
        });

        // 1) Distribuciones de plantilla: ya están vinculadas al producto en su pivote.
        DB::statement('
            UPDATE distribucion_por_tallas d
            JOIN distribucion_producto dp ON dp.distribucion_por_talla_id = d.id
            SET d.producto_id = dp.producto_id
            WHERE d.producto_id IS NULL
        ');

        // 2) Distribuciones de OP: se reparten por bloques, en el orden en que se
        //    crearon y en el orden en que los productos se agregaron a la OP.
        $vinculos = DB::table('orden_produccion_producto')
            ->orderBy('orden_produccion_id')
            ->orderBy('id')
            ->get()
            ->groupBy('orden_produccion_id');

        foreach ($vinculos as $ordenId => $filas) {
            $productos = $filas->pluck('producto_id')->unique()->values();
            if ($productos->isEmpty()) {
                continue;
            }

            $distribuciones = DB::table('distribucion_orden_produccion')
                ->where('orden_produccion_id', $ordenId)
                ->orderBy('id')
                ->pluck('distribucion_por_talla_id');

            if ($distribuciones->isEmpty()) {
                continue;
            }

            if ($productos->count() === 1) {
                DB::table('distribucion_por_tallas')
                    ->whereIn('id', $distribuciones)
                    ->whereNull('producto_id')
                    ->update(['producto_id' => $productos->first()]);
                continue;
            }

            // Sin bloques exactos no se adivina: esas filas quedan sin producto.
            if ($distribuciones->count() % $productos->count() !== 0) {
                continue;
            }

            $porProducto = intdiv($distribuciones->count(), $productos->count());
            foreach ($distribuciones->values() as $indice => $distribucionId) {
                DB::table('distribucion_por_tallas')
                    ->where('id', $distribucionId)
                    ->whereNull('producto_id')
                    ->update(['producto_id' => $productos[intdiv($indice, $porProducto)]]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('distribucion_por_tallas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_id');
        });
    }
};
