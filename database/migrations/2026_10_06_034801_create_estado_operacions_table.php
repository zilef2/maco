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
        Schema::create('estado_operacions', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('cuando_aplica')->nullable();
            $table->text('regla_clave')->nullable();
            $table->string('puede_pasar_a')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estado_operacions');
    }
};
