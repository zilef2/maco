<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite crear una notificación que solo se muestra a partir de una fecha
     * (avisos por fecha de compromiso de reparación: dos días antes y el día).
     */
    public function up(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->date('fecha_programada')->nullable()->after('prioridad')->index();
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropIndex(['fecha_programada']);
            $table->dropColumn('fecha_programada');
        });
    }
};
