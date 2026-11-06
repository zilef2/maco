<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * «Procesos externos» deja de ser un estado de la OP y con él se va la única
     * coexistencia permitida, así que una OP vuelve a tener un solo estado.
     * «Esperando materiales» pasa a ser una cola FIFO: la fecha de entrada a la
     * cola decide qué OP se promueve primero a «Lista para taller» cuando una
     * compra completa el kit.
     */
    public function up(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->timestamp('esperando_materiales_desde')->nullable()->after('recepcion_desde');
        });

        $externosId = DB::table('estado_operacions')->where('nombre', 'Procesos externos')->value('id');
        $preparacionId = DB::table('estado_operacions')->where('nombre', 'Preparación')->value('id');
        $esperandoId = DB::table('estado_operacions')->where('nombre', 'Esperando materiales')->value('id');

        // El material de esas OPs sigue fuera, en manos del proveedor: vuelven a
        // «Preparación», que es donde se reúne el kit.
        if ($externosId && $preparacionId) {
            DB::table('orden_produccions')
                ->where('estado_op_id', $externosId)
                ->update(['estado_op_id' => $preparacionId]);
        }

        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estado_op_secundario_id');
        });

        if ($externosId) {
            DB::table('estado_operacions')->where('id', $externosId)->delete();
        }

        DB::table('estado_operacions')->where('nombre', 'Preparación')->update([
            'puede_pasar_a' => 'Esperando materiales / Lista para taller',
        ]);

        DB::table('estado_operacions')->where('nombre', 'Esperando materiales')->update([
            'cuando_aplica' => 'Falta comprar o recibir algún componente; la OP espera en la cola por orden de llegada',
            'puede_pasar_a' => 'Preparación / Lista para taller',
            'regla_clave' => 'Cola FIFO: al registrarse una compra, las OP cuyo kit quede completo pasan solas a «Lista para taller», empezando por la más antigua en la cola.',
        ]);

        DB::table('estado_operacions')->where('nombre', 'Lista para taller')->update([
            'regla_clave' => 'Solo se entra con el kit completo: el sistema valida que ningún material requerido supere la existencia actual.',
        ]);

        // Las OP que ya estaban esperando entran a la cola con la fecha de su
        // último cambio, que es lo más cercano que hay al momento de entrada.
        if ($esperandoId) {
            DB::table('orden_produccions')
                ->where('estado_op_id', $esperandoId)
                ->whereNull('esperando_materiales_desde')
                ->update(['esperando_materiales_desde' => DB::raw('updated_at')]);
        }
    }

    public function down(): void
    {
        if (! DB::table('estado_operacions')->where('nombre', 'Procesos externos')->exists()) {
            DB::table('estado_operacions')->insert([
                'nombre' => 'Procesos externos',
                'cuando_aplica' => 'Hay material en embarretado, sublimación, corte, pedrería, etc.',
                'puede_pasar_a' => 'Preparación / Lista para taller',
                'regla_clave' => 'El material continúa siendo propiedad de Macondas.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('estado_operacions')->where('nombre', 'Preparación')->update([
            'puede_pasar_a' => 'Esperando materiales / Procesos externos / Lista para taller',
        ]);

        DB::table('estado_operacions')->where('nombre', 'Esperando materiales')->update([
            'cuando_aplica' => 'Falta comprar o recibir algún componente',
            'regla_clave' => 'No debe enviarse al taller.',
        ]);

        DB::table('estado_operacions')->where('nombre', 'Lista para taller')->update([
            'regla_clave' => 'El sistema debe validar que no haya faltantes.',
        ]);

        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->foreignId('estado_op_secundario_id')
                ->nullable()
                ->after('estado_op_id')
                ->constrained('estado_operacions')
                ->nullOnDelete();
        });

        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropColumn('esperando_materiales_desde');
        });
    }
};