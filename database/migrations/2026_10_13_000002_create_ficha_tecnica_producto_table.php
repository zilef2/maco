<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ficha_tecnica_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_tecnica_id')->constrained('ficha_tecnicas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->timestamps();
            // una ficha única por cada producto
            $table->unique('producto_id', 'ftp_producto_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ficha_tecnica_producto');
    }
};
