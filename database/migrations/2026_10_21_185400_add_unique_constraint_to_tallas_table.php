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
        Schema::table('tallas', function (Blueprint $table) {
            // Agregar restricción única para evitar duplicados de la misma talla por nombre
            $table->unique('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tallas', function (Blueprint $table) {
            // Eliminar la restricción única
            $table->dropUnique('tallas_nombre_unique');
        });
    }
};
