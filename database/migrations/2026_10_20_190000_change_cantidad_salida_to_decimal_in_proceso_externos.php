<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las cantidades de materiales pueden ser fraccionarias (metros, kilos).
     * La columna original era entera y truncaba esos valores.
     */
    public function up(): void
    {
        Schema::table('proceso_externos', function (Blueprint $table) {
            $table->decimal('cantidad_salida', 12, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('proceso_externos', function (Blueprint $table) {
            $table->integer('cantidad_salida')->nullable()->change();
        });
    }
};
