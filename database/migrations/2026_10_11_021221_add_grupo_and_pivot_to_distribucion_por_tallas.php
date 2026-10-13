<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('distribucion_por_tallas', function (Blueprint $table) {
            $table->integer('grupo')->nullable()->after('talla');
        });

        Schema::create('distribucion_orden_produccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribucion_por_talla_id')->constrained('distribucion_por_tallas')->cascadeOnDelete();
            $table->foreignId('orden_produccion_id')->constrained('orden_produccions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['distribucion_por_talla_id', 'orden_produccion_id'], 'dop_dist_op_unique');
        });

        // Migrar el vínculo directo existente hacia la pivote
        $vinculos = DB::table('distribucion_por_tallas')
            ->whereNotNull('orden_produccion_id')
            ->get(['id', 'orden_produccion_id']);
        foreach ($vinculos as $vinculo) {
            DB::table('distribucion_orden_produccion')->updateOrInsert(
                [
                    'distribucion_por_talla_id' => $vinculo->id,
                    'orden_produccion_id' => $vinculo->orden_produccion_id,
                ],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        Schema::table('distribucion_por_tallas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_produccion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distribucion_por_tallas', function (Blueprint $table) {
            $table->foreignId('orden_produccion_id')->nullable()->constrained('orden_produccions');
        });

        Schema::dropIfExists('distribucion_orden_produccion');

        Schema::table('distribucion_por_tallas', function (Blueprint $table) {
            $table->dropColumn('grupo');
        });
    }
};
