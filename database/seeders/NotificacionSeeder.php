<?php

namespace Database\Seeders;

use Alejo\Notificaciones\Facades\NotificacionesFacade as Notificaciones;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificacionSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::where('email', 'like', '%@%')->first();
        $segundoUsuario = User::skip(1)->first();

        if ($superadmin) {
            Notificaciones::crear('Nuevo pedido recibido #1001', 'alta', $superadmin->id);
            Notificaciones::crear('Tu reporte mensual de ventas está listo', 'media', $superadmin->id);
        }

        if ($segundoUsuario) {
            Notificaciones::crear('Se ha asignado un nuevo presupuesto para revisión', 'alta', $segundoUsuario->id);
        }

        Notificaciones::crear('Aviso general: Actualización del sistema completada', 'baja', null);
    }
}
