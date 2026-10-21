<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Llaves foráneas de las fichas de ensamble. Se agregan en un archivo
     * aparte para que las tablas ya existan cuando se crean las restricciones.
     */
    public function up(): void
    {
        Schema::table('ficha_ensambles', function (Blueprint $table) {
            $table->foreign('orden_produccion_id')
                ->references('id')->on('orden_produccions')
                ->cascadeOnDelete();

            $table->foreign('producto_referencia_id')
                ->references('id')->on('productos')
                ->nullOnDelete();

            $table->foreign('taller_id')
                ->references('id')->on('tallers')
                ->nullOnDelete();
        });

        Schema::table('ficha_ensamble_materials', function (Blueprint $table) {
            $table->foreign('ficha_ensamble_id')
                ->references('id')->on('ficha_ensambles')
                ->cascadeOnDelete();

            $table->foreign('material_id')
                ->references('id')->on('materials')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ficha_ensamble_materials', function (Blueprint $table) {
            $table->dropForeign(['ficha_ensamble_id']);
            $table->dropForeign(['material_id']);
        });

        Schema::table('ficha_ensambles', function (Blueprint $table) {
            $table->dropForeign(['orden_produccion_id']);
            $table->dropForeign(['producto_referencia_id']);
            $table->dropForeign(['taller_id']);
        });
    }
};
