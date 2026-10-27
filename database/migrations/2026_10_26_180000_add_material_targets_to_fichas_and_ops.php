<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una ficha técnica puede describir un producto o un material procesado.
     * La OP, a su vez, puede producir productos (como antes) o materiales
     * procesados, y la recepción de calidad debe saber cuál de los dos recibió.
     */
    public function up(): void
    {
        Schema::create('ficha_tecnica_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_tecnica_id')->constrained('ficha_tecnicas')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('material_id', 'ftm_material_unico');
        });

        Schema::create('orden_produccion_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_produccion_id')->constrained('orden_produccions')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->unsignedInteger('cantidad')->default(0);
            $table->timestamps();

            $table->unique(['orden_produccion_id', 'material_id'], 'opm_op_material_unico');
        });

        Schema::table('distribucion_por_tallas', function (Blueprint $table) {
            $table->foreignId('material_id')
                ->nullable()
                ->after('producto_id')
                ->constrained('materials')
                ->nullOnDelete();
        });

        Schema::table('recepcion_calidads', function (Blueprint $table) {
            $table->foreignId('material_referencia_id')
                ->nullable()
                ->after('producto_referencia_id')
                ->constrained('materials')
                ->nullOnDelete();
        });

        Schema::table('compromisos_reparacion', function (Blueprint $table) {
            $table->foreignId('material_referencia_id')
                ->nullable()
                ->after('producto_referencia_id')
                ->constrained('materials')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('compromisos_reparacion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_referencia_id');
        });

        Schema::table('recepcion_calidads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_referencia_id');
        });

        Schema::table('distribucion_por_tallas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
        });

        Schema::dropIfExists('orden_produccion_material');
        Schema::dropIfExists('ficha_tecnica_material');
    }
};
