<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de inventario de los productos, espejo de material_movimientos.
 *
 * A diferencia de los materiales, el inventario de un producto se lleva por
 * talla, y la misma fila de distribución la comparten todas las OPs que
 * producen esa talla. Por eso cada movimiento guarda la OP que lo originó: así
 * la entrada de una OP se puede corregir sin tocar lo que metió otra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->string('talla', 50)->nullable();
            $table->unsignedBigInteger('orden_produccion_id')->nullable();
            $table->string('tipo_movimiento')->default('entrada');
            $table->integer('cantidad')->default(0);
            $table->integer('existencia_anterior')->default(0);
            $table->integer('existencia_nueva')->default(0);
            $table->string('motivo')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('producto_id')->references('id')->on('productos')->cascadeOnDelete();
            $table->foreign('orden_produccion_id')
                ->references('id')
                ->on('orden_produccions')
                ->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['producto_id', 'talla']);
            $table->index('orden_produccion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_movimientos');
    }
};
