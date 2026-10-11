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
            $table->string('etapa_componente');
            $table->decimal('cantidad');
            $table->string('unidad');
            $table->string('sale_de');
            $table->string('proceso_servicio');
            $table->date('fecha_salida')->nullable();
            $table->date('fecha_regreso')->nullable();
            $table->text('resultado_observaciones')->nullable();
            $table->timestamps();
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
