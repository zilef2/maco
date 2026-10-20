<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La dirección del taller pasa a ser opcional (la importación desde Excel
     * solo exige el nombre).
     */
    public function up(): void
    {
        Schema::table('tallers', function (Blueprint $table) {
            $table->string('direccion')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tallers')->whereNull('direccion')->update(['direccion' => '']);

        Schema::table('tallers', function (Blueprint $table) {
            $table->string('direccion')->nullable(false)->change();
        });
    }
};
