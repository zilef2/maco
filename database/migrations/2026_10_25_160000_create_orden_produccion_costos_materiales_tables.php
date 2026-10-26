<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Precio de cada material tal como quedó guardado en la OP, más el historial
     * de los cambios de ese precio. El precio depende del parámetro
     * «costos_promedio»: 1 = promedio de los precios del material,
     * 2 = último precio digitado.
     */
    public function up(): void
    {
        Schema::create('orden_produccion_costos_materiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_produccion_id')
                ->constrained('orden_produccions')
                ->cascadeOnDelete();
            $table->foreignId('material_id')
                ->constrained('materials')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('metodo_costo');
            $table->decimal('precio_unitario', 12, 2)->default(0);
            // Consumo estándar de la ficha × unidades del producto en la OP.
            $table->decimal('cantidad', 14, 4)->default(0);
            $table->decimal('costo_total', 16, 2)->default(0);
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['orden_produccion_id', 'material_id'], 'op_costos_materiales_unica');
        });

        Schema::create('orden_produccion_costos_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_produccion_id')
                ->constrained('orden_produccions')
                ->cascadeOnDelete();
            $table->foreignId('material_id')
                ->constrained('materials')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('metodo_costo');
            // Null cuando el precio se guarda por primera vez en la OP.
            $table->decimal('precio_anterior', 12, 2)->nullable();
            $table->decimal('precio_nuevo', 12, 2);
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['orden_produccion_id', 'created_at'], 'op_costos_cambios_por_op_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_produccion_costos_cambios');
        Schema::dropIfExists('orden_produccion_costos_materiales');
    }
};
