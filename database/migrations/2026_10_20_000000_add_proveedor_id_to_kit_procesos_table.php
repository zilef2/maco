<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kit_procesos', function (Blueprint $table) {
            // El proveedor se elige al completar el kit; las filas que nacen
            // con la OP quedan sin proveedor hasta que el usuario lo defina.
            $table->foreignId('proveedor_id')
                ->nullable()
                ->after('material_id')
                ->constrained('proveedors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kit_procesos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proveedor_id');
        });
    }
};
