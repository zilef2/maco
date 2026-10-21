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
        Schema::create('ficha_ensambles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_produccion_id');
            $table->foreignId('producto_referencia_id')->nullable();
            $table->foreignId('taller_id')->nullable();
            $table->integer('cantidad_total');
            $table->date('fecha_entrega');
            // opcionales
            $table->string('coleccion')->nullable();
            $table->string('imagen_referencia_url')->nullable();
            $table->text('instrucciones_especiales')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ficha_ensambles');
    }
};
