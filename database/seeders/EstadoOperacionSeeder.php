<?php

namespace Database\Seeders;

use App\Models\EstadoOperacion;
use App\Models\OrdenProduccion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EstadoOperacionSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['nombre' => 'Borrador', 'cuando_aplica' => 'OP creada pero aún no confirmada', 'puede_pasar_a' => 'Preparación', 'regla_clave' => 'No genera movimientos de inventario.'],
            ['nombre' => 'Preparación', 'cuando_aplica' => 'Se están reuniendo materiales', 'puede_pasar_a' => 'Esperando materiales / Procesos externos / Lista para taller', 'regla_clave' => 'Debe comparar materiales requeridos vs disponibles.'],
            ['nombre' => 'Esperando materiales', 'cuando_aplica' => 'Falta comprar o recibir algún componente', 'puede_pasar_a' => 'Preparación / Lista para taller', 'regla_clave' => 'No debe enviarse al taller.'],
            ['nombre' => 'Procesos externos', 'cuando_aplica' => 'Hay material en embarretado, sublimación, corte, pedrería, etc.', 'puede_pasar_a' => 'Preparación / Lista para taller', 'regla_clave' => 'El material continúa siendo propiedad de Macondas.'],
            ['nombre' => 'Lista para taller', 'cuando_aplica' => 'Kit completo + ficha de ensamble lista', 'puede_pasar_a' => 'En taller', 'regla_clave' => 'El sistema debe validar que no haya faltantes.'],
            ['nombre' => 'En taller', 'cuando_aplica' => 'Kit recibido por taller y ensamble iniciado', 'puede_pasar_a' => 'Recepción parcial / En reparación / Terminada', 'regla_clave' => 'Registrar fecha de entrega y taller.'],
            ['nombre' => 'Recepción parcial', 'cuando_aplica' => 'Se recibieron algunos pares aprobados pero la OP no está completa', 'puede_pasar_a' => 'En reparación / Terminada', 'regla_clave' => 'Solo lo aprobado entra a inventario.'],
            ['nombre' => 'En reparación', 'cuando_aplica' => 'Uno o más pares quedaron en taller para corrección', 'puede_pasar_a' => 'Terminada / Baja parcial', 'regla_clave' => 'No ingresan al inventario disponible.'],
            ['nombre' => 'Terminada', 'cuando_aplica' => 'Toda la producción está conciliada', 'puede_pasar_a' => 'Cerrada', 'regla_clave' => 'Debe estar definido el estado de cada par.'],
            ['nombre' => 'Cerrada', 'cuando_aplica' => 'OP conciliada y costo final registrado', 'puede_pasar_a' => null, 'regla_clave' => 'No permitir editar movimientos sin procedimiento de ajuste.'],
        ];

        foreach ($estados as $estado) {
            EstadoOperacion::updateOrCreate(['nombre' => $estado['nombre']], $estado);
        }

        $borradorId = EstadoOperacion::where('nombre', 'Borrador')->value('id');
        $idsValidos = EstadoOperacion::whereIn('nombre', array_column($estados, 'nombre'))->pluck('id');
        OrdenProduccion::query()
            ->whereNotIn('estado_op_id', $idsValidos)
            ->update(['estado_op_id' => $borradorId]);

        EstadoOperacion::whereNotIn('nombre', array_column($estados, 'nombre'))->delete();
    }
}
