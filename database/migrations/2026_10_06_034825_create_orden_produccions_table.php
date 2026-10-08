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
        Schema::create('orden_produccions', function (Blueprint $table) {
            $table->id();

            $table->string('numero_op');
            $table->integer('cantidad_planificada');
            $table->string('taller');
            $table->datetime('fecha_creacion');
            $table->date('fecha_inicio_programada');
            $table->date('fecha_prevista_envio_taller');
            $table->date('fecha_entrega_estimada');
            $table->string('shopify_product_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->datetime('fecha_fin_real')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_produccions');
    }
};
