<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribucion_producto', function (Blueprint $table) {
            $table->id();
            $table->integer('distribucion_por_talla_id');
            $table->integer('producto_id');
            $table->timestamps();
            $table->unique(['distribucion_por_talla_id', 'producto_id'], 'dp_dist_prod_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribucion_producto');
    }
};
