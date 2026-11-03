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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('Codigo_interno');
            $table->string('ID_producto_Shopify');
            $table->string('Categoria_original');
            $table->string('Familia_normalizada');
            $table->string('Titulo_Shopify');
            $table->string('Control_de_variante');
            $table->string('Opciones');
            $table->string('Estado');
            $table->string('Observaciones')->nullable();
            $table->text('descripcion')->nullable();
            $table->integer('precio_predicho')->nullable();
            $table->integer('precio_venta_producto')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
