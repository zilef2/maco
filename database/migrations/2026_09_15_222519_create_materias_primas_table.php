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
        Schema::create('materias_primas', function (Blueprint $table) {
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
            $table->string('categoria')->nullable();
            $table->string('unidad_medida')->nullable();
            $table->integer('existencia_actual')->nullable();
            $table->decimal('costo_promedio_ponderado', 62, 2)->default(0);
            $table->datetime('fecha_creacion')->nullable();
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materias_primas');
    }
};
