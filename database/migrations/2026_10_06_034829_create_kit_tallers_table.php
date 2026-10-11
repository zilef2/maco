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
        Schema::create('kit_tallers', function (Blueprint $table) {
            $table->id();
            $table->string('estado_requerido');
            $table->string('unidad');
            $table->decimal('cantidad_requerida');
            $table->boolean('listo')->default(false);
            $table->decimal('cantidad_disponible')->nullable();
            $table->decimal('cantidad_preparada')->nullable();
            $table->decimal('falta')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kit_tallers');
    }
};
