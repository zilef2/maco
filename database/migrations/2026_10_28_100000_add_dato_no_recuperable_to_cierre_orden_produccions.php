<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El cierre también guarda los pares no recuperables recibidos, para que
     * la pantalla de cierres muestre lo mismo que el formulario de
     * «Cambiar estado»: Esperada, Aprobada y No recuperable.
     */
    public function up(): void
    {
        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->integer('dato_no_recuperable')->nullable()->after('dato_real');
        });
    }

    public function down(): void
    {
        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->dropColumn('dato_no_recuperable');
        });
    }
};
