<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            // la UI de OP selecciona productos por pivote; la referencia es opcional
            $table->foreignId('producto_referencia_id')->nullable()->after('numero_op')->constrained('productos');
            $table->foreignId('taller_id')->after('fecha_prevista_envio_taller')->constrained('tallers');
            $table->foreignId('estado_op_id')->after('taller_id')->constrained('estado_operacions');
            $table->foreignId('user_id')->after('estado_op_id')->constrained('users');
        });

        Schema::table('proceso_externos', function (Blueprint $table) {
            $table->foreignId('orden_produccion_id')->after('id')->constrained('orden_produccions');
            $table->foreignId('material_origen_id')->after('orden_produccion_id')->constrained('materials');
            $table->foreignId('proveedor_destino_id')->after('material_origen_id')->constrained('proveedors');

        });

        Schema::table('kit_tallers', function (Blueprint $table) {
            $table->foreignId('orden_produccion_id')->after('id')->constrained('orden_produccions');
            $table->foreignId('material_id')->after('orden_produccion_id')->constrained('materials');
        });

        Schema::table('distribucion_tallas', function (Blueprint $table) {
            $table->foreignId('orden_produccion_id')->after('id')->constrained('orden_produccions');
        });

        Schema::table('recepcion_calidads', function (Blueprint $table) {
            $table->foreignId('orden_produccion_id')->after('id')->constrained('orden_produccions');
            $table->foreignId('producto_referencia_id')->after('orden_produccion_id')->constrained('productos');
        });

        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->foreignId('orden_produccion_id')->after('id')->constrained('orden_produccions');
            $table->foreignId('responsable_id')->after('orden_produccion_id')->constrained('users');
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

        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_produccion_id');
            $table->dropConstrainedForeignId('responsable_id');
        });

        Schema::table('recepcion_calidads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_produccion_id');
            $table->dropConstrainedForeignId('producto_referencia_id');
        });

        Schema::table('distribucion_tallas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_produccion_id');
        });

        Schema::table('kit_tallers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_produccion_id');
            $table->dropConstrainedForeignId('material_id');
        });

        Schema::table('proceso_externos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_produccion_id');
            $table->dropConstrainedForeignId('material_origen_id');
            $table->dropConstrainedForeignId('proveedor_destino_id');
        });

        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_referencia_id');
            $table->dropConstrainedForeignId('taller_id');
            $table->dropConstrainedForeignId('estado_op_id');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};