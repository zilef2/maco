<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los datos que no siempre se conocen al registrar la salida (bodega,
     * servicio, fecha prevista, etc.) deben poder quedar vacíos.
     */
    public function up(): void
    {
        Schema::table('proceso_externos', function (Blueprint $table) {
            $table->string('etapa_componente')->nullable()->change();
            $table->string('unidad_medida')->nullable()->change();
            $table->string('bodega_origen')->nullable()->change();
            $table->string('servicio_proceso')->nullable()->change();
            $table->date('fecha_prevista_regreso')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('proceso_externos', function (Blueprint $table) {
            $table->string('etapa_componente')->nullable(false)->change();
            $table->string('unidad_medida')->nullable(false)->change();
            $table->string('bodega_origen')->nullable(false)->change();
            $table->string('servicio_proceso')->nullable(false)->change();
            $table->date('fecha_prevista_regreso')->nullable(false)->change();
        });
    }
};
