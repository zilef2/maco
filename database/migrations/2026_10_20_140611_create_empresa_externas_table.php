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
        Schema::create('empresa_externas', function (Blueprint $table) {
            $table->string('nombre');
            $table->string('actividad');
            // opcionales
            $table->string('direccion')->nullable();
            $table->string('numero_contacto')->nullable();
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa_externas');
    }
};
