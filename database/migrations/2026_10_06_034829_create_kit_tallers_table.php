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
        Schema::create('kit_tallers', function (Blueprint $table) {
            $table->id();

            $table->string('estado_requerido');
            $table->string('unidad_medida');
            $table->integer('cantidad_requerida');
            $table->integer('cantidad_disponible');
            $table->integer('cantidad_preparada');
            $table->integer('cantidad_faltante');
            $table->boolean('esta_listo')->default(false);
            // opcionales
            
            $table->text('observaciones')->nullable();
            $table->datetime('fecha_inicio_alistamiento')->nullable();
            $table->datetime('fecha_completado')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kit_tallers');
    }
};
