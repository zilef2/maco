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
        Schema::create('materials', function (Blueprint $table) {
            $table->string('codigo_material');
            $table->string('categoria');
            $table->string('nombre_base');
            $table->string('unidad_inventario');
            $table->boolean('maneja_color')->default(false);
            $table->boolean('maneja_talla')->default(false);
            $table->boolean('maneja_otra_variante')->default(false);
            $table->string('estado');
            // opcionales
            $table->string('variante_atributo_sugerido')->nullable();
            $table->string('proveedor_habitual')->nullable();
            $table->string('observaciones')->nullable();
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
