<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('existencia_actual', 12, 2)->default(0)->after('unidad_inventario');
        });

        Schema::create('material_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->onDelete('cascade');
            $table->string('tipo_movimiento')->default('ajuste');
            $table->decimal('cantidad', 12, 2)->default(0);
            $table->decimal('existencia_anterior', 12, 2)->default(0);
            $table->decimal('existencia_nueva', 12, 2)->default(0);
            $table->string('motivo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_movimientos');
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('existencia_actual');
        });
    }
};
