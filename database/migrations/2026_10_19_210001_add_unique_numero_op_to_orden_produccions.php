<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Garantiza que no existan dos OP con el mismo `numero_op`.
     *
     * Primero renumera las OP repetidas (se conserva la de id más bajo) usando
     * el mismo criterio de la aplicación: el mayor número OP + 1. Luego agrega
     * el índice único.
     */
    public function up(): void
    {
        $duplicados = DB::table('orden_produccions')
            ->select('numero_op')
            ->groupBy('numero_op')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('numero_op');

        foreach ($duplicados as $numero) {
            $repetidas = DB::table('orden_produccions')
                ->where('numero_op', $numero)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1);

            foreach ($repetidas as $id) {
                $max = DB::table('orden_produccions')
                    ->where('numero_op', 'like', 'OP-%')
                    ->selectRaw("MAX(CAST(REPLACE(numero_op, 'OP-', '') AS UNSIGNED)) as max_op")
                    ->value('max_op');

                $nuevo = 'OP-' . max(100, ((int) $max) + 1);

                DB::table('orden_produccions')->where('id', $id)->update(['numero_op' => $nuevo]);
            }
        }

        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->unique('numero_op');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropUnique(['numero_op']);
        });
    }
};
