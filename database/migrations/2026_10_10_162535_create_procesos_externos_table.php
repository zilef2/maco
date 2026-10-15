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
        Schema::create('procesos_externos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orden_produccion_id')->nullable();
            $table->string('etapa_componente')->nullable();
            $table->unsignedBigInteger('material_origen_id')->nullable(); // FK a material
            $table->decimal('cantidad_salida')->nullable();
            $table->string('unidad_medida')->nullable();
            $table->string('bodega_origen')->nullable();
            $table->unsignedBigInteger('proveedor_destino_id')->nullable();
            $table->string('servicio_proceso')->nullable();
            $table->date('fecha_salida')->nullable();
            $table->date('fecha_prevista_regreso')->nullable();
            $table->date('fecha_regreso_real')->nullable();
            $table->text('resultado_observaciones')->nullable();
            $table->timestamps();

            $table->foreign('material_origen_id')->references('id')->on('material')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procesos_externos');
    }
};
