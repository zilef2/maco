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
        // La tabla pudo quedar creada a medias por una version anterior de la
        // migracion; se recrea para asegurar las columnas y llaves foraneas.
        Schema::dropIfExists('compromisomaterials');

        Schema::create('compromisomaterials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('op_origen_id')->constrained('orden_produccions')->cascadeOnDelete();
            $table->foreignId('op_destino_id')->constrained('orden_produccions')->cascadeOnDelete();

            $table->decimal('cantidad', 12, 4)->default(0);
            $table->decimal('cantidad_cumplida', 12, 4)->default(0);
            $table->string('estado')->default('comprometido');
            $table->timestamps();

            $table->unique(
                ['material_id', 'op_origen_id', 'op_destino_id'],
                'compromiso_material_unico'
            );
            $table->index(['material_id', 'estado']);
            $table->index(['op_origen_id', 'estado']);
            $table->index(['op_destino_id', 'estado']);
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
