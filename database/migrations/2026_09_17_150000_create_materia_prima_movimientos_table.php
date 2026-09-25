<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materia_prima_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materia_prima_id')->constrained('materias_primas')->onDelete('cascade');
            $table->string('tipo_movimiento')->default('ajuste');
            $table->decimal('cantidad', 12, 2)->default(0);
            $table->decimal('existencia_anterior', 12, 2)->default(0);
            $table->decimal('existencia_nueva', 12, 2)->default(0);
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->string('motivo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materia_prima_movimientos');
    }
};
