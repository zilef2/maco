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
        Schema::create('estados_operacions', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('cuando_aplica');
            $table->text('regla_clave');
            $table->string('puede_pasar_a')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estados_operacions');
    }
};
