<?php

namespace Database\Seeders;

use App\Models\CierreOrdenProduccion;
use App\Models\OrdenProduccion;
use App\Models\User;
use Illuminate\Database\Seeder;

class CierreOrdenProduccionSeeder extends Seeder
{
    /** Cuántos cierres de orden de producción crear (editar aquí) */
    private const CANTIDAD = 20;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ordenes = OrdenProduccion::pluck('id');
        $usuarios = User::pluck('id');

        if ($ordenes->isEmpty() || $usuarios->isEmpty()) {
            $this->command?->warn('No hay órdenes de producción o usuarios: no se crearon cierres.');
            return;
        }

        $controles = [
            'Cantidad producida', 'Tiempo de producción', 'Merma registrada',
            'Unidades defectuosas', 'Rendimiento del taller', 'Cumplimiento de fecha',
        ];
        $estados = ['Completado', 'Completado', 'Pendiente', 'Revisado', 'Con observaciones'];
        $observaciones = [
            'Cierre sin novedades.',
            'Se detectó merma dentro del rango esperado.',
            'Lídera verificó la muestra final.',
            'Diferencia explicada por ajuste de máquina.',
            null, null,
        ];

        for ($i = 1; $i <= self::CANTIDAD; $i++) {
            $esperado = rand(50, 1000);
            $real = max(0, $esperado + rand(-60, 60));

            CierreOrdenProduccion::create([
                'orden_produccion_id' => $ordenes->random(),
                'responsable_id'      => $usuarios->random(),
                'control_cierre'      => $controles[array_rand($controles)],
                'dato_esperado'       => $esperado,
                'dato_real'           => $real,
                'diferencia'          => $real - $esperado,
                'estado_accion'       => $estados[array_rand($estados)],
                'fecha_cierre'        => now()->subDays(rand(0, 180))->subHours(rand(0, 23)),
                'observaciones'       => $observaciones[array_rand($observaciones)],
            ]);
        }
    }
}
