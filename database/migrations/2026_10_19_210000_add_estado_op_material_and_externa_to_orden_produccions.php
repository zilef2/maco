<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega a la OP el estado de sus materiales (independiente del estado de
     * la orden) y la marca `externa` para identificar OP de proceso externo.
     */
    public function up(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->foreignId('estado_op_material_id')
                ->nullable()
                ->after('estado_op_id')
                ->constrained('estado_op_materials')
                ->nullOnDelete();

            $table->boolean('externa')->default(false)->after('estado_op_material_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estado_op_material_id');
            $table->dropColumn('externa');
        });
    }
};
