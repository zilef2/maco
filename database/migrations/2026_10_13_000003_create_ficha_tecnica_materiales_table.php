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
            $table->foreignId('ficha_tecnica_id')->constrained('ficha_tecnicas')->cascadeOnDelete();
            $table->foreignId('material_componente_id')->constrained('materials')->cascadeOnDelete();
            $table->string('aporta')->nullable();
            $table->string('estado_al_llegar_ensamble')->nullable();
            $table->string('unidad_medida')->nullable();
            $table->decimal('consumo_estandar', 62, 2)->default(1);
            $table->decimal('costo_asociado_teorico', 62, 2)->default(0);
            $table->decimal('costo_asociado_real', 62, 2)->nullable();
            $table->date('fecha_inicio_vigencia')->nullable();
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
