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
        Schema::table('productos', function (Blueprint $table) {
            if (!Schema::hasColumn('productos', 'precio_venta_producto')) {
                $table->decimal('precio_venta_producto', 15, 2)->nullable()->after('costo_anticipado');
            } else {
                $table->decimal('precio_venta_producto', 15, 2)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('productos', 'precio_venta_producto')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropColumn('precio_venta_producto');
            });
        }
    }
};
