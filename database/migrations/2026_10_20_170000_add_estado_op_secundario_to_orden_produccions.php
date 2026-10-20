<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado secundario de la OP. La única coexistencia permitida es
     * "Esperando materiales" + "Procesos externos"; el resto de estados
     * siguen siendo únicos (estado_op_id).
     */
    public function up(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->foreignId('estado_op_secundario_id')
                ->nullable()
                ->after('estado_op_id')
                ->constrained('estado_operacions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estado_op_secundario_id');
        });
    }
};
