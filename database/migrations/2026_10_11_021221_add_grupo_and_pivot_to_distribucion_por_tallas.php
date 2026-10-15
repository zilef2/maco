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
            $table->integer('distribucion_por_talla_id');
            $table->integer('orden_produccion_id');
            $table->timestamps();
            $table->unique(['distribucion_por_talla_id', 'orden_produccion_id'], 'dop_dist_op_unique');
        });


        // Schema::table('distribucion_por_tallas', function (Blueprint $table) {
        //     $table->dropConstrainedForeignId('orden_produccion_id');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribucion_orden_produccion');

        Schema::table('distribucion_por_tallas', function (Blueprint $table) {
            $table->dropColumn('grupo');
        });
    }
};
