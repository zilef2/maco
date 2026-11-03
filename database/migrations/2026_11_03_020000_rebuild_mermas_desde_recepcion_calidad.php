<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La merma no se digita: es el registro de los pares «No recuperable» que se
     * anotan en la recepción de calidad, así que deja de ser una tabla propia de
     * captura y pasa a apuntar a la recepción que la originó.
     *
     * La tabla venía vacía y con llaves a tablas que ya no existen
     * (materias_primas, producto_referencias, talleres).
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('mermas');

        Schema::create('mermas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('orden_produccion_id')->constrained('orden_produccions')->cascadeOnDelete();
            $table->foreignId('recepcion_calidad_id')->constrained('recepcion_calidads')->cascadeOnDelete();
            $table->foreignId('producto_referencia_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('material_referencia_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('talla')->nullable();
            $table->unsignedInteger('cantidad');
            $table->dateTime('fecha_registro');
            $table->decimal('costo_unitario', 12, 2)->nullable()->default(0);
            $table->decimal('costo_total_perdida', 12, 2)->nullable()->default(0);
            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->unique('recepcion_calidad_id');
            $table->index('fecha_registro');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('mermas');

        Schema::create('mermas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_produccion_id');
            $table->foreignId('materia_prima_id');
            $table->foreignId('producto_referencia_id')->nullable();
            $table->foreignId('talla_id')->nullable();
            $table->foreignId('recepcion_calidad_id')->nullable();
            $table->foreignId('proceso_externo_id')->nullable();
            $table->foreignId('responsable_id')->nullable();
            $table->foreignId('taller_id')->nullable();
            $table->integer('cantidad');
            $table->string('motivo');
            $table->dateTime('fecha_registro');
            $table->decimal('porcentaje_merma', 5, 2)->nullable();
            $table->decimal('costo_unitario', 12, 2)->default(0)->nullable();
            $table->decimal('costo_total_perdida', 12, 2)->default(0)->nullable();
            $table->boolean('es_recuperable')->default(false)->nullable();
            $table->integer('cantidad_recuperada')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }
};
