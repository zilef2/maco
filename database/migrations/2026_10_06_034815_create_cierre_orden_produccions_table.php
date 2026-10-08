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
        Schema::create('cierre_orden_produccions', function (Blueprint $table) {
            $table->id();
            $table->string('control_cierre');
            $table->integer('dato_esperado');
            $table->integer('dato_real');
            $table->integer('diferencia');
            $table->string('estado_accion');
            $table->datetime('fecha_cierre');
            // opcionales
            
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cierre_orden_produccions');
    }
};
