<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La columna "proveedor o externa o macondas" del Excel solo decide en qué
 * entidad termina cada fila, así que no tiene sentido guardarla dentro de
 * proveedores: todo lo que llega a esa tabla es proveedor por definición.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedors', function (Blueprint $table) {
            $table->dropColumn('clasificacion');
        });
    }

    public function down(): void
    {
        Schema::table('proveedors', function (Blueprint $table) {
            $table->string('clasificacion')->default('proveedor')->after('observaciones_generales');
        });
    }
};
