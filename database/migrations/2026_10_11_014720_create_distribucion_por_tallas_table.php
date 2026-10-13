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
        Schema::create('distribucion_por_tallas', function (Blueprint $table) {
            $table->string('talla');
            $table->integer('cantidad_planificada');
            // opcionales
            $table->integer('cantidad_recibida_buena')->nullable();
            $table->integer('cantidad_pendiente_reparacion')->nullable();
            $table->id();
            $table->foreignId('orden_produccion_id')->nullable()->constrained('orden_produccions');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribucion_por_tallas');
    }
};
