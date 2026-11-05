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
        Schema::table('productos', function (Blueprint $table) {
            // Coste de producción unitario (materiales según el parámetro de
            // costos + procesos). Se guarda al cerrar una OP, no se digita.
            $table->decimal('coste_produccion', 15, 2)->nullable()->after('precio_venta_producto');
            // Diferencia en dinero entre precio de venta y coste_produccion.
            $table->decimal('ganancia_producto', 15, 2)->nullable()->after('coste_produccion');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['coste_produccion', 'ganancia_producto']);
        });
    }
};
