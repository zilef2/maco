<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El cierre de una OP guarda los tres datos que lo habilitan:
     * materiales sobrantes/recuperados, pérdidas en mermas y costo real final.
     * Se permite guardarlo como borrador mientras la OP sigue en «Pendiente»,
     * por eso los campos de control pasan a ser opcionales.
     */
    public function up(): void
    {
        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->unsignedInteger('materiales_sobrantes')->nullable()->after('orden_produccion_id');
            $table->unsignedInteger('perdidas_mermas')->nullable()->after('materiales_sobrantes');
            $table->decimal('costo_real_final', 14, 2)->nullable()->after('perdidas_mermas');
            $table->boolean('completado')->default(false)->after('costo_real_final');
        });

        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->string('control_cierre')->nullable()->change();
            $table->integer('dato_esperado')->nullable()->change();
            $table->integer('dato_real')->nullable()->change();
            $table->integer('diferencia')->nullable()->change();
            $table->string('estado_accion')->nullable()->change();
            $table->datetime('fecha_cierre')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('cierre_orden_produccions')->whereNull('control_cierre')->update(['control_cierre' => 'Cierre de OP']);
        DB::table('cierre_orden_produccions')->whereNull('dato_esperado')->update(['dato_esperado' => 0]);
        DB::table('cierre_orden_produccions')->whereNull('dato_real')->update(['dato_real' => 0]);
        DB::table('cierre_orden_produccions')->whereNull('diferencia')->update(['diferencia' => 0]);
        DB::table('cierre_orden_produccions')->whereNull('estado_accion')->update(['estado_accion' => 'borrador']);
        DB::table('cierre_orden_produccions')->whereNull('fecha_cierre')->update(['fecha_cierre' => now()]);

        Schema::table('cierre_orden_produccions', function (Blueprint $table) {
            $table->string('control_cierre')->nullable(false)->change();
            $table->integer('dato_esperado')->nullable(false)->change();
            $table->integer('dato_real')->nullable(false)->change();
            $table->integer('diferencia')->nullable(false)->change();
            $table->string('estado_accion')->nullable(false)->change();
            $table->datetime('fecha_cierre')->nullable(false)->change();

            $table->dropColumn(['materiales_sobrantes', 'perdidas_mermas', 'costo_real_final', 'completado']);
        });
    }
};
