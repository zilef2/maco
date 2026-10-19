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
            // Estados: 0 = no procesado, 1 = procesado (en un futuro puede tener más estados)
            $table->integer('material_procesado')->default(0)->after('estado');

            // proveedor_habitual pasa de texto a llave foránea de Proveedor
            $table->dropColumn('proveedor_habitual');
            $table->unsignedBigInteger('proveedor_habitual')->nullable()->after('variante_atributo_sugerido');
            $table->foreign('proveedor_habitual')->references('id')->on('proveedors')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['proveedor_habitual']);
            $table->dropColumn('proveedor_habitual');
            $table->dropColumn('material_procesado');
        });
    }
};
