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
        // Desactivar temporalmente la verificación de llaves foráneas para evitar errores 1824 en MySQL
        Schema::disableForeignKeyConstraints();

        Schema::create('mermas', function (Blueprint $table) {
            $table->id();

            // Claves foráneas requeridas y opcionales
            $table->foreignId('orden_produccion_id')->constrained('orden_produccions');
            $table->foreignId('materia_prima_id')->constrained('materias_primas');
            $table->foreignId('producto_referencia_id')->nullable()->constrained('producto_referencias');
            $table->foreignId('talla_id')->nullable()->constrained('tallas');
            $table->foreignId('recepcion_calidad_id')->nullable()->constrained('recepcion_calidads');
            $table->foreignId('proceso_externo_id')->nullable()->constrained('proceso_externos');
            $table->foreignId('responsable_id')->nullable()->constrained('users');
            $table->foreignId('taller_id')->nullable()->constrained('talleres');

            // Datos operativos
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

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('mermas');
        Schema::enableForeignKeyConstraints();
    }
};
