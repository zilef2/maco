<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El cierre ya no se digita: las pérdidas en mermas pasan a ser un valor
     * monetario calculado desde los productos no recuperables, y el campo de
     * materiales sobrantes/recuperados se elimina por completo.
     */
    public function up(): void
    {
        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->decimal('perdidas_mermas', 14, 2)->nullable()->change();
        });

        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->dropColumn('materiales_sobrantes');
        });
    }

    public function down(): void
    {
        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->unsignedInteger('materiales_sobrantes')->nullable()->after('orden_produccion_id');
            $table->unsignedInteger('perdidas_mermas')->nullable()->change();
        });
    }
};
