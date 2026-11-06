<?php

namespace Database\Seeders;

use App\Models\EstadoOperacion;
use App\Models\OrdenProduccion;
use Illuminate\Database\Seeder;

class EstadoOperacionSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['nombre' => 'Borrador', 'cuando_aplica' => 'OP creada pero aún no confirmada', 'puede_pasar_a' => 'Preparación', 'regla_clave' => 'No genera movimientos de inventario.'],
            ['nombre' => 'Preparación', 'cuando_aplica' => 'Se están reuniendo materiales', 'puede_pasar_a' => 'Esperando materiales / Lista para taller', 'regla_clave' => 'Debe comparar materiales requeridos vs disponibles.'],
            ['nombre' => 'Esperando materiales', 'cuando_aplica' => 'Falta comprar o recibir algún componente; la OP espera en la cola por orden de llegada', 'puede_pasar_a' => 'Preparación / Lista para taller', 'regla_clave' => 'Cola FIFO: al registrarse una compra, las OP cuyo kit quede completo pasan solas a «Lista para taller», empezando por la más antigua en la cola.'],
            ['nombre' => 'Lista para taller', 'cuando_aplica' => 'Kit completo + ficha de ensamble lista', 'puede_pasar_a' => 'En taller', 'regla_clave' => 'Solo se entra con el kit completo: el sistema valida que ningún material requerido supere la existencia actual.'],
            ['nombre' => 'En taller', 'cuando_aplica' => 'Kit Recibido por taller y ensamble iniciado', 'puede_pasar_a' => 'Recibiendo', 'regla_clave' => 'Registrar fecha de entrega y taller. Al entrar se descuenta del inventario la cantidad requerida de cada material del kit.'],
            ['nombre' => 'Recibiendo', 'cuando_aplica' => 'Se recibieron algunos pares aprobados pero la OP no está completa', 'puede_pasar_a' => null, 'regla_clave' => 'Solo lo aprobado entra a inventario. La OP se cierra sola cuando cada talla cuadra con lo esperado y no quedan pares en reparación.'],
            ['nombre' => 'Cerrada', 'cuando_aplica' => 'OP conciliada y costo final registrado', 'puede_pasar_a' => null, 'regla_clave' => 'No permitir editar movimientos sin procedimiento de ajuste.'],
        ];

        foreach ($estados as $estado) {
            EstadoOperacion::updateOrCreate(['nombre' => $estado['nombre']], $estado);
        }

        $borradorId = EstadoOperacion::where('nombre', 'Borrador')->value('id');
        $recibiendoId = EstadoOperacion::where('nombre', 'Recibiendo')->value('id');
        $preparacionId = EstadoOperacion::where('nombre', 'Preparación')->value('id');
        $externosId = EstadoOperacion::where('nombre', 'Procesos externos')->value('id');
        $esperandoId = EstadoOperacion::where('nombre', 'Esperando materiales')->value('id');

        // «Pendiente» ya no existe: sus OPs siguen esperando pares del taller,
        // así que continúan en «Recibiendo» con su fecha de compromiso.
        OrdenProduccion::query()
            ->where('estado_op_id', EstadoOperacion::where('nombre', 'Pendiente')->value('id'))
            ->update(['estado_op_id' => $recibiendoId]);

        // «Procesos externos» tampoco: el material sigue en manos del proveedor,
        // así que la OP vuelve a reunir el kit en «Preparación».
        if ($externosId && $preparacionId) {
            OrdenProduccion::query()
                ->where('estado_op_id', $externosId)
                ->update(['estado_op_id' => $preparacionId]);
        }

        $idsValidos = EstadoOperacion::whereIn('nombre', array_column($estados, 'nombre'))->pluck('id');
        OrdenProduccion::query()
            ->whereNotIn('estado_op_id', $idsValidos)
            ->update(['estado_op_id' => $borradorId]);

        // Toda OP que espera materiales tiene su lugar en la cola FIFO.
        if ($esperandoId) {
            OrdenProduccion::query()
                ->where('estado_op_id', $esperandoId)
                ->whereNull('esperando_materiales_desde')
                ->update(['esperando_materiales_desde' => now()]);
        }

        EstadoOperacion::whereNotIn('nombre', array_column($estados, 'nombre'))->delete();
    }
}