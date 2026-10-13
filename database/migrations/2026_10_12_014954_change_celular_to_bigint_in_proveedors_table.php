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
        // Los celulares superan el rango de INT (ej: 573129998877), se usa BIGINT.
        Schema::table('proveedors', function (Blueprint $table) {
            $table->bigInteger('celular1')->nullable()->change();
            $table->bigInteger('celular2')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proveedors', function (Blueprint $table) {
            $table->integer('celular1')->nullable()->change();
            $table->integer('celular2')->nullable()->change();
        });
    }
};
