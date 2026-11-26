<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El archivo MPP trae dos nombres: el de la familia ("Nombre base") y el de
     * la fila ("Nombre del material"). El segundo no tenía dónde guardarse.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->string('nombre_material', 255)->nullable()->after('nombre_base');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table): void {
            $table->dropColumn('nombre_material');
        });
    }
};
