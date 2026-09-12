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
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('nombre')->nullable();
            $table->text('descripcion')->nullable();
            $table->datetime('fecha_inicio')->nullable();
            $table->datetime('fecha_fin')->nullable();
            $table->decimal('monto_total', 62, 2)->default(0);
            $table->decimal('monto_ejecutado', 62, 2)->default(0);
            $table->decimal('monto_disponible', 62, 2)->default(0);
            $table->decimal('total_salarios', 62, 2)->default(0);
            $table->decimal('total_viaticos', 62, 2)->default(0);
            $table->decimal('total_materiales', 62, 2)->default(0);
            $table->string('estado')->nullable();
            $table->string('aprobado_por')->nullable();
            $table->datetime('fecha_aprobacion')->nullable();
            $table->string('codigo_interno')->nullable();
            $table->text('observaciones')->nullable();
            $table->double('porcentaje_ejecutado')->nullable();
            $table->double('umbral_alerta')->nullable();
            $table->datetime('fecha_ultimo_reajuste')->nullable();
            $table->text('motivo_reajuste')->nullable();
            // $table->foreign('user_id_aprobado')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presupuestos');
    }
};
