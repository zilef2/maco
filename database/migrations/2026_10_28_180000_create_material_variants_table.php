<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variantes del material (colores u otro atributo, por ejemplo tallas).
     *
     * El material sigue siendo la entidad base del inventario y cada fila de
     * material_variants representa una presentación concreta de ese material.
     * Un material no puede mezclar colores con otras variantes: el tipo de la
     * variante lo indica explícitamente.
     */
    public function up(): void
    {
        Schema::create('material_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')
                ->constrained('materials')
                ->cascadeOnDelete();
            $table->string('nombre', 255);
            $table->string('tipo', 20)->default('variante')
                ->comment('color | variante');
            $table->unsignedInteger('orden')->default(0)
                ->comment('Orden de presentación dentro del material');
            $table->timestamps();

            $table->unique(['material_id', 'tipo', 'nombre']);
            $table->index(['material_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_variants');
    }
};
