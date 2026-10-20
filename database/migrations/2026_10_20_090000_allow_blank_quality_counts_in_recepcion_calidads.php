<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La recepción de calidad se genera al pasar la OP de "En taller" a
     * "Recibiendo", con las cantidades de calidad todavía en blanco.
     * El producto de referencia también puede faltar en la OP.
     */
    public function up(): void
    {
        Schema::table('recepcion_calidads', function (Blueprint $table) {
            $table->unsignedBigInteger('producto_referencia_id')->nullable()->change();
            $table->integer('cantidad_aprobada')->nullable()->change();
            $table->integer('cantidad_en_reparacion')->nullable()->change();
            $table->integer('cantidad_no_recuperable')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('recepcion_calidads')->whereNull('cantidad_aprobada')->update(['cantidad_aprobada' => 0]);
        DB::table('recepcion_calidads')->whereNull('cantidad_en_reparacion')->update(['cantidad_en_reparacion' => 0]);
        DB::table('recepcion_calidads')->whereNull('cantidad_no_recuperable')->update(['cantidad_no_recuperable' => 0]);
        DB::table('recepcion_calidads')->whereNull('producto_referencia_id')->delete();

        Schema::table('recepcion_calidads', function (Blueprint $table) {
            $table->unsignedBigInteger('producto_referencia_id')->nullable(false)->change();
            $table->integer('cantidad_aprobada')->nullable(false)->change();
            $table->integer('cantidad_en_reparacion')->nullable(false)->change();
            $table->integer('cantidad_no_recuperable')->nullable(false)->change();
        });
    }
};
