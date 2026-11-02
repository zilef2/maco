<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de las cargas masivas por Excel.
 *
 * Se guarda una fila por archivo subido con la duración de la subida y la
 * duración total del procesamiento. La vista /ImportarExcel usa estos datos
 * para mostrar el tiempo promedio de las últimas cargas de cada entidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacion_excels', function (Blueprint $table) {
            $table->id();
            $table->string('entidad', 40);
            $table->string('archivo')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            // pendiente -> procesando -> completado | fallido
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedInteger('filas')->nullable();
            $table->unsignedInteger('creados')->nullable();
            $table->unsignedInteger('actualizados')->nullable();
            // Tiempo que tardó la petición HTTP (lo que espera el usuario).
            $table->unsignedInteger('subida_ms')->nullable();
            // Tiempo total, incluyendo el procesamiento en segundo plano.
            $table->unsignedInteger('duracion_ms')->nullable();
            // microtime(true) del inicio: permite medir procesos en otro worker.
            $table->double('iniciado_en')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['entidad', 'estado', 'id']);
            $table->index(['entidad', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_excels');
    }
};
