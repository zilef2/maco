<?php

namespace Tests\Feature;

use App\Models\ActividadRegistro;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActividadTest extends TestCase
{
    use RefreshDatabase;

    public function test_bitacora_filtra_por_rango_de_fecha_hora_usuario_tipo_y_metodo(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        $superadmin = $this->superadmin();
        $usuario = User::factory()->create(['name' => 'Usuario con historial']);

        $incluida = $this->registro($usuario, '2026-11-05 08:15:00', 'POST', ActividadRegistro::TIPO_ACCION, '10.0.0.8');
        $excluidaPorFecha = $this->registro($usuario, '2026-11-06 18:30:00', 'POST', ActividadRegistro::TIPO_ACCION, '10.0.0.8');
        $excluidaPorMetodo = $this->registro($usuario, '2026-11-05 09:00:00', 'GET', ActividadRegistro::TIPO_PETICION, '10.0.0.8');

        $response = $this->actingAs($superadmin)->get(route('Actividad.index', [
            'bitacora_desde' => '2026-11-05T08:00',
            'bitacora_hasta' => '2026-11-05T09:00',
            'bitacora_usuario' => $usuario->id,
            'bitacora_tipo' => 'acciones',
            'bitacora_metodo' => 'POST',
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('actividad/Index')
            ->where('bitacoraFiltro.desde', '2026-11-05T08:00')
            ->where('bitacoraFiltro.hasta', '2026-11-05T09:00')
            ->where('bitacoraFiltro.usuario', $usuario->id)
            ->where('bitacoraFiltro.tipo', 'acciones')
            ->where('bitacoraFiltro.metodo', 'POST')
            ->where('bitacora.total', 1)
            ->where('bitacora.data.0.id', $incluida->id)
            ->where('bitacora.data.0.ip', '10.0.0.8')
            ->where('bitacora.data', fn (Collection $filas) => $filas->count() === 1)
        );

        $this->assertNotSame($excluidaPorFecha->id, $incluida->id);
        $this->assertNotSame($excluidaPorMetodo->id, $incluida->id);
    }

    public function test_camino_filtra_por_usuario_con_historial_y_muestra_la_ip_de_cada_paso(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        $superadmin = $this->superadmin();
        $usuario = User::factory()->create(['name' => 'Usuario del camino']);
        $usuarioSinHistorial = User::factory()->create(['name' => 'Usuario sin historial']);

        $this->registro($usuario, now()->subDay()->format('Y-m-d H:i:s'), 'GET', ActividadRegistro::TIPO_PETICION, '192.168.10.25');

        $response = $this->actingAs($superadmin)->get(route('Actividad.index', [
            'camino_usuario' => $usuario->id,
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('actividad/Index')
            ->where('filtro.camino_usuario', $usuario->id)
            ->where('camino.usuario', 'Usuario del camino')
            ->where('camino.pasos.0.ip', '192.168.10.25')
            ->where('usuarios_historial.0.id', $usuario->id)
            ->where('usuarios_historial.0.nombre', 'Usuario del camino')
            ->where('usuarios_historial', fn (Collection $historial) => $historial->count() === 1
                && ! $historial->contains('id', $usuarioSinHistorial->id))
        );
    }

    private function superadmin(): User
    {
        $rol = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $usuario = User::factory()->create(['name' => 'Superadmin de prueba']);
        $usuario->assignRole($rol);

        return $usuario;
    }

    private function registro(User $usuario, string $fecha, string $metodo, string $tipo, string $ip): ActividadRegistro
    {
        return ActividadRegistro::create([
            'user_id' => $usuario->id,
            'nombre_usuario' => $usuario->name,
            'ruta_nombre' => 'Material.index',
            'ruta_uri' => 'material',
            'metodo' => $metodo,
            'tipo' => $tipo,
            'peticion' => 'peticion-'.$usuario->id.'-'.$fecha,
            'modulo' => 'Material',
            'accion' => $tipo === ActividadRegistro::TIPO_ACCION ? 'store' : 'index',
            'status' => 200,
            'duracion_ms' => 125,
            'ip' => $ip,
            'created_at' => $fecha,
        ]);
    }
}
