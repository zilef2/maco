<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de navegación: una fila por petición autenticada.
 *
 * Los archivos de storage/logs no sirven para analizar quién hizo qué y cuándo:
 * las líneas de admin y superadmin se escriben sin la acción del controlador,
 * no guardan el usuario ni la ruta, y el archivo se trunca. Esta tabla es la
 * fuente estable sobre la que se construye la vista de Actividad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad_registros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            // Foto del nombre: el usuario puede borrarse o renombrarse después.
            $table->string('nombre_usuario')->nullable();
            $table->string('ruta_nombre', 191)->nullable();
            $table->string('ruta_uri', 191);
            $table->string('metodo', 10);
            $table->string('modulo', 60)->nullable();
            $table->string('accion', 60)->nullable();
            $table->string('controlador', 191)->nullable();
            $table->smallInteger('status')->nullable();
            $table->integer('duracion_ms')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
            $table->index(['modulo', 'accion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_registros');
    }
};
