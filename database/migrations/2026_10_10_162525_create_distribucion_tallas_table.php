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
        Schema::create('distribucion_tallas', function (Blueprint $table) {
            $table->id();
            $table->string('talla');
            $table->integer('cantidad_planificada');
            $table->integer('cantidad_recibida_buena')->nullable();
            $table->integer('cantidad_pendiente_reparacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribucion_tallas');
    }
};
