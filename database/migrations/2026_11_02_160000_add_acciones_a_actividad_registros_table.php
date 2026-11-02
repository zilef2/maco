<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La bitácora por petición no alcanza para ver qué hizo realmente el usuario.
 *
 * `tipo` separa la navegación (`peticion`) de las acciones de negocio (`accion`)
 * que un controlador registra desde dentro, y `peticion` agrupa unas con otras
 * para que el análisis mida el salto entre acciones y no entre peticiones.
 * `status` no distingue un guardado rechazado de uno aceptado, así que `error`
 * guarda la excepción cuando la petición falló.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividad_registros', function (Blueprint $table) {
            $table->string('tipo', 12)->default('peticion')->after('metodo');
            $table->string('peticion', 36)->nullable()->after('tipo');
            $table->string('entidad', 60)->nullable()->after('controlador');
            $table->string('entidad_id', 40)->nullable()->after('entidad');
            $table->string('detalle', 255)->nullable()->after('entidad_id');
            $table->string('error', 191)->nullable()->after('status');

            $table->index(['user_id', 'tipo', 'created_at'], 'actividad_usuario_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('actividad_registros', function (Blueprint $table) {
            $table->dropIndex('actividad_usuario_tipo_idx');
            $table->dropColumn(['tipo', 'peticion', 'entidad', 'entidad_id', 'detalle', 'error']);
        });
    }
};
