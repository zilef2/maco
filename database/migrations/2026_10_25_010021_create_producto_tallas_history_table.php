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
        Schema::create('producto_tallas_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('talla', 50);
            $table->integer('cantidad_planificada')->default(0);
            $table->string('accion', 20)->comment('create, update, delete');
            $table->json('datos_anteriores')->nullable()->comment('Snapshot del estado anterior');
            $table->json('datos_nuevos')->nullable()->comment('Snapshot del nuevo estado');
            $table->timestamps();

            $table->index(['producto_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_tallas_history');
    }
};
