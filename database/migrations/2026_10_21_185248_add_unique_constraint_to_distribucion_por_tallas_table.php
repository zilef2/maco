<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema::table('distribucion_por_tallas', function (Blueprint $table) {
            // Agregar restricción única para evitar duplicados de la misma talla
            // $table->unique('talla');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::table('distribucion_por_tallas', function (Blueprint $table) {
            // Eliminar la restricción única
            // $table->dropUnique('distribucion_por_tallas_talla_unique');
        // });
    }
};
