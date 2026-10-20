<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La OP ahora tiene un tipo explícito: 'taller' (usa taller_id) o
     * 'externa' (usa empresa_externa_id). La columna booleana `externa`
     * se mantiene sincronizada desde el tipo en el controller.
     */
    public function up(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->string('tipo', 20)->default('taller')->after('taller_id');
            $table->foreignId('empresa_externa_id')
                ->nullable()
                ->after('tipo')
                ->constrained('empresa_externas')
                ->nullOnDelete();
        });

        // Las OP ya marcadas como externas pasan a tipo 'externa'.
        DB::table('orden_produccions')->where('externa', true)->update(['tipo' => 'externa']);
    }

    public function down(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_externa_id');
            $table->dropColumn('tipo');
        });
    }
};
