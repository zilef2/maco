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
        Schema::create('recepcion_calidads', function (Blueprint $table) {
            $table->id();

            $table->string('talla');
            $table->integer('cantidad_esperada');
            $table->integer('cantidad_recibida');
            $table->integer('cantidad_aprobada');
            $table->integer('cantidad_en_reparacion');
            $table->integer('cantidad_no_recuperable');
            $table->datetime('fecha_recepcion');
            // opcionales
            
            $table->text('observaciones')->nullable();
            $table->datetime('fecha_inicio_revision')->nullable();
            $table->datetime('fecha_fin_revision')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recepcion_calidads');
    }
};
