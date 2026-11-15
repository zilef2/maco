<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('existencia_actual', 12, 2)->nullable()->default(null)->change();
        });

        // El genérico representa a una familia, no a un artículo almacenado: su
        // inventario y su consumo viven en los materiales concretos del mismo par.
        DB::table('materials')
            ->where('generico', true)
            ->update(['existencia_actual' => null, 'gasto_diario' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('materials')
            ->whereNull('existencia_actual')
            ->update(['existencia_actual' => 0]);

        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('existencia_actual', 12, 2)->default(0)->change();
        });
    }
};
