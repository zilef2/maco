<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Costo del proceso (mano de obra, taller, etc.) y su suma con el costo de
     * las fichas técnicas. El total se persiste en la OP y se renueva cada vez
     * que cambian los costos de sus materiales.
     */
    public function up(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->decimal('costo_proceso', 14, 2)->default(0)->after('cantidad_planificada');
            $table->decimal('costo_total', 16, 2)->default(0)->after('costo_proceso');
        });
    }

    public function down(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropColumn(['costo_proceso', 'costo_total']);
        });
    }
};
