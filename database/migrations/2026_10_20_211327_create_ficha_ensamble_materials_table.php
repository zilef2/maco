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
        Schema::create('ficha_ensamble_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_ensamble_id');
            $table->foreignId('material_id');
            $table->integer('cantidad');
            $table->string('unidad_medida');
            $table->string('aporta');
            // opcionales
            $table->string('variante_especificacion')->nullable();
            $table->string('proceso_previo')->nullable();
            $table->boolean('verificado')->default(false)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ficha_ensamble_materials');
    }
};
