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
        Schema::create('procesoops', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->decimal('coste_proceso', 62, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procesoops');
    }
};
