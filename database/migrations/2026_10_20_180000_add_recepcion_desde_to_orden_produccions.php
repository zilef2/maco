<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca de entrada al estado "Recibiendo". `updated_at` cambia con
     * cualquier edición, así que no sirve para saber desde cuándo la OP está
     * en recepción; este campo se fija al entrar y se limpia al salir.
     */
    public function up(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->timestamp('recepcion_desde')
                ->nullable()
                ->after('estado_op_secundario_id');
        });

        // Para las OP que ya estaban en recepción no existe historial: se usa
        // la última actualización como aproximación.
        DB::table('orden_produccions')
            ->whereIn('estado_op_id', function ($query) {
                $query->select('id')
                    ->from('estado_operacions')
                    ->where('nombre', 'Recibiendo');
            })
            ->update(['recepcion_desde' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropColumn('recepcion_desde');
        });
    }
};
