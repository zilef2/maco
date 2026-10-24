<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fecha de compromiso pendiente por episodio de reparación. Es multiplo
     * porque una OP puede irse a reparación varias veces a lo largo de los
     * dias; cada episodio queda registrado con su propia fecha.
     */
    public function up(): void
    {
        Schema::create('compromisos_reparacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_produccion_id')
                ->constrained('orden_produccions')
                ->cascadeOnDelete();
            $table->foreignId('producto_referencia_id')
                ->nullable()
                ->constrained('productos')
                ->nullOnDelete();
            $table->string('talla')->nullable();
            $table->integer('cantidad_en_reparacion');
            $table->date('fecha_compromiso');
            $table->boolean('atendido')->default(false);
            $table->timestamps();

            $table->index(['orden_produccion_id', 'talla', 'atendido'], 'compromisos_reparacion_abiertos_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compromisos_reparacion');
    }
};
