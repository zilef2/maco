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
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('gasto_diario', 12, 4)->nullable()
                ->comment('Unidades gastadas por día (calculado a partir de salidas a taller)')
                ->after('existencia_actual');
            $table->timestamp('gasto_diario_actualizado_en')->nullable()
                ->comment('Fecha en que se recalculó por última vez el gasto_diario')
                ->after('gasto_diario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['gasto_diario', 'gasto_diario_actualizado_en']);
        });
    }
};
