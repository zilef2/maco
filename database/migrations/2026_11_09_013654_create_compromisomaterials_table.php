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
        Schema::create('compromisomaterials', function (Blueprint $table) {
            $table->foreign('material_id');
            $table->foreign('op_origen_id');
            $table->foreign('op_destino_id');
            $table->decimal('cantidad');
            $table->string('estado');
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compromisomaterials');
    }
};
