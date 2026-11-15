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
        Schema::dropIfExists('orden_produccion_material_concreto');

        // La ficha técnica declara materiales genéricos (COM-CAP-0); en cada OP
        // el usuario define cuál material concreto de esa familia se consume
        // (COM-CAP-37, con su color o talla). Aquí queda esa definición.
        Schema::create('orden_produccion_material_concreto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_produccion_id')->constrained('orden_produccions')->cascadeOnDelete();
            $table->enum('objetivo_tipo', ['producto', 'material']);
            $table->unsignedBigInteger('objetivo_id')->comment('producto_id o material_id que produce la OP');
            $table->foreignId('material_generico_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('material_concreto_id')->constrained('materials')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['orden_produccion_id', 'objetivo_tipo', 'objetivo_id', 'material_generico_id'],
                'op_material_concreto_unico'
            );
            $table->index(['orden_produccion_id', 'objetivo_id'], 'op_mat_concreto_op_objetivo_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_produccion_material_concreto');
    }
};
