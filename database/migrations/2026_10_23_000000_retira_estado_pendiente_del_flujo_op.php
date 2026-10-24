<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * «Pendiente» deja de existir: los pares que se van a reparación se gestionan
     * dentro de «Recibiendo» con su fecha de compromiso pendiente. Desde
     * «En taller» solo se puede pasar a «Recibiendo», y «Recibiendo» ya no ofrece
     * «Cerrada»: el sistema cierra la OP cuando la recepción queda completa.
     */
    public function up(): void
    {
        $pendienteId = DB::table('estado_operacions')->where('nombre', 'Pendiente')->value('id');
        $recibiendoId = DB::table('estado_operacions')->where('nombre', 'Recibiendo')->value('id');

        if ($pendienteId && $recibiendoId) {
            DB::table('orden_produccions')
                ->where('estado_op_id', $pendienteId)
                ->update(['estado_op_id' => $recibiendoId]);

            DB::table('orden_produccions')
                ->where('estado_op_secundario_id', $pendienteId)
                ->update(['estado_op_secundario_id' => null]);
        }

        DB::table('estado_operacions')->where('nombre', 'En taller')->update([
            'puede_pasar_a' => 'Recibiendo',
        ]);

        DB::table('estado_operacions')->where('nombre', 'Recibiendo')->update([
            'puede_pasar_a' => null,
            'regla_clave' => 'Solo lo aprobado entra a inventario. La OP se cierra sola cuando cada talla cuadra con lo esperado y no quedan pares en reparación.',
        ]);

        if ($pendienteId) {
            DB::table('estado_operacions')->where('id', $pendienteId)->delete();
        }
    }

    public function down(): void
    {
        if (! DB::table('estado_operacions')->where('nombre', 'Pendiente')->exists()) {
            DB::table('estado_operacions')->insert([
                'nombre' => 'Pendiente',
                'cuando_aplica' => 'Uno o más pares quedaron en taller para corrección',
                'puede_pasar_a' => 'Cerrada / Recibiendo',
                'regla_clave' => 'No ingresan al inventario disponible.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('estado_operacions')->where('nombre', 'En taller')->update([
            'puede_pasar_a' => 'Recibiendo / Pendiente / Cerrada',
        ]);

        DB::table('estado_operacions')->where('nombre', 'Recibiendo')->update([
            'puede_pasar_a' => 'Pendiente / Cerrada',
            'regla_clave' => 'Solo lo aprobado entra a inventario.',
        ]);
    }
};
