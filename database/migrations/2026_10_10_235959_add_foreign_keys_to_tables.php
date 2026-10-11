<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->foreignId('producto_id')->after('id')->constrained('productos');
            $table->foreignId('estado_operacion_id')->after('producto_id')->constrained('estado_operacions');
            $table->foreignId('responsable_id')->after('estado_operacion_id')->constrained('users');
        });

        Schema::table('proceso_externos', function (Blueprint $table) {
            $table->foreignId('orden_produccion_id')->after('id')->constrained('orden_produccions');
            $table->foreignId('material_origen_id')->after('orden_produccion_id')->constrained('materias_primas');
            $table->foreignId('proveedor_destino_id')->after('material_origen_id')->constrained('proveedors');
        });

        Schema::table('kit_tallers', function (Blueprint $table) {
            $table->foreignId('orden_produccion_id')->after('id')->constrained('orden_produccions');
            $table->foreignId('material_componente_id')->after('orden_produccion_id')->constrained('materias_primas');
        });

        Schema::table('recepcion_calidads', function (Blueprint $table) {
            $table->foreignId('orden_produccion_id')->after('id')->constrained('orden_produccions');
            $table->foreignId('producto_referencia_id')->after('orden_produccion_id')->constrained('productos');
        });

        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->foreignId('orden_produccion_id')->after('id')->constrained('orden_produccions');
            $table->foreignId('responsable_id')->after('orden_produccion_id')->constrained('users');
        });

        Schema::table('ficha_tecnica_materiales', function (Blueprint $table) {
            $table->foreignId('producto_referencia_id')->after('id')->constrained('productos');
            $table->foreignId('material_componente_id')->after('producto_referencia_id')->constrained('materias_primas');
        });

        Schema::table('pendientes_produccions', function (Blueprint $table) {
            $table->foreignId('orden_produccion_id')->after('id')->constrained('orden_produccions');
        });
    }

    public function down(): void
    {
        Schema::table('pendientes_produccions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_produccion_id');
        });

        Schema::table('ficha_tecnica_materiales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_referencia_id');
            $table->dropConstrainedForeignId('material_componente_id');
        });

        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_produccion_id');
            $table->dropConstrainedForeignId('responsable_id');
        });

        Schema::table('recepcion_calidads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_produccion_id');
            $table->dropConstrainedForeignId('producto_referencia_id');
        });

        Schema::table('kit_tallers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_produccion_id');
            $table->dropConstrainedForeignId('material_componente_id');
        });

        Schema::table('proceso_externos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_produccion_id');
            $table->dropConstrainedForeignId('material_origen_id');
            $table->dropConstrainedForeignId('proveedor_destino_id');
        });

        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_id');
            $table->dropConstrainedForeignId('estado_operacion_id');
            $table->dropConstrainedForeignId('responsable_id');
        });
    }
};
