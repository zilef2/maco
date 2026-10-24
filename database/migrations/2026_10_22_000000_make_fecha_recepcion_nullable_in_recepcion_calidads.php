<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las recepciones se crean al guardar un avance, antes de que el usuario
     * termine de diligenciar la fecha. La fecha se registra al guardar, pero
     * la columna debe permitir nulos para no romper borradores antiguos.
     */
    public function up(): void
    {
        Schema::table('recepcion_calidads', function (Blueprint $table) {
            $table->dateTime('fecha_recepcion')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('recepcion_calidads')
            ->whereNull('fecha_recepcion')
            ->update(['fecha_recepcion' => now()]);

        Schema::table('recepcion_calidads', function (Blueprint $table) {
            $table->dateTime('fecha_recepcion')->nullable(false)->change();
        });
    }
};
