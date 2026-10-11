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
            $table->date('fecha_creacion');
            $table->date('fecha_prevista_envio_taller');
            $table->string('id_shopify_producto')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            //foreigns :
            // producto_referencia_id
            // taller_id
            // estado_op_id
            // user_id
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
