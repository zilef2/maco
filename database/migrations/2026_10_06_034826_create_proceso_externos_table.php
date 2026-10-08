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
        Schema::create('proceso_externos', function (Blueprint $table) {
            $table->id();

            $table->string('etapa_componente');
            $table->integer('cantidad_salida');
            $table->string('unidad_medida');
            $table->string('bodega_origen');
            $table->string('servicio_proceso');
            $table->datetime('fecha_salida');
            $table->date('fecha_prevista_regreso');
            // opcionales
            
            $table->text('resultado_observaciones')->nullable();
            $table->datetime('fecha_regreso_real')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proceso_externos');
    }
};
