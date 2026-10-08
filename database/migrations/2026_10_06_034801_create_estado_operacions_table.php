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
            $table->string('estado_op');
            $table->text('cuando_aplica');
            $table->text('regla_clave');
            // opcionales
            
            $table->json('puede_pasar_a')->nullable();
            $table->datetime('fecha_creacion')->nullable();
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
