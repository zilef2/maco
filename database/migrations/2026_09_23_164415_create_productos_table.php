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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('nombre');
            $table->text('descripcion');
            $table->integer('precio_normal');
            $table->boolean('esta_activo')->default(false);
            // opcionales
            $table->text('foto_principal')->nullable();
            $table->text('foto_lateral')->nullable();
            $table->text('foto_trasera')->nullable();
            $table->text('foto_derecha')->nullable();
            $table->text('foto_izquierda')->nullable();
            $table->integer('precio_ponderado')->nullable();
            $table->integer('precio_preventa')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
