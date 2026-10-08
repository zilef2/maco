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
        Schema::create('ficha_tecnica_materiales', function (Blueprint $table) {
            $table->id();
            $table->string('aporta');
            $table->string('estado_al_llegar_ensamble');
            $table->string('unidad_medida');
            $table->integer('consumo_estandar');
            $table->decimal('costo_asociado', 62, 2)->default(0);
            $table->date('fecha_inicio_vigencia');
            // opcionales
            
            $table->string('proceso_previo')->nullable();
            $table->text('observaciones')->nullable();
            $table->date('fecha_fin_vigencia')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ficha_tecnica_materiales');
    }
};
