<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas de productos terminados.
 *
 * La cabecera guarda la fecha automática, la forma de pago y los ajustes
 * (descuento e impuesto, como monto o como porcentaje) con el subtotal y el
 * ingreso final ya calculados. El detalle guarda cada producto y talla que se
 * vendió: es la fila de la que se descuenta la existencia recibida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('forma_pago', 20)->default('contado');
            $table->string('forma_pago_detalle', 100)->nullable();
            $table->enum('descuento_tipo', ['monto', 'porcentaje'])->default('monto');
            $table->decimal('descuento_valor', 14, 2)->default(0);
            $table->decimal('descuento_monto', 14, 2)->default(0);
            $table->enum('impuesto_tipo', ['monto', 'porcentaje'])->default('monto');
            $table->decimal('impuesto_valor', 14, 2)->default(0);
            $table->decimal('impuesto_monto', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('observaciones', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('fecha');
        });

        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('talla', 50);
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['producto_id', 'talla']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
    }
};
