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
        Schema::create('pendientes_produccions', function (Blueprint $table) {
            $table->id();
            $table->string('prioridad');
            $table->text('pendiente');
            $table->string('donde_se_usa');
            $table->boolean('por_definir')->default(false);
            $table->datetime('fecha_registro');
            // opcionales
            
            $table->date('fecha_estimada_resolucion')->nullable();
            $table->datetime('fecha_resolucion_real')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pendientes_produccions');
    }
};
