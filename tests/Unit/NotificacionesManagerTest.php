<?php

namespace Tests\Unit;

use Alejo\Notificaciones\NotificacionesManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacionesManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_notification_for_a_user_successfully(): void
    {
        $user = User::factory()->create();
        $manager = new NotificacionesManager();

        $notificacion = $manager->crear('Test mensaje', 'alta', $user->id);

        $this->assertEquals($user->id, $notificacion->user_id);
        $this->assertEquals('Test mensaje', $notificacion->mensaje);
        $this->assertEquals('alta', $notificacion->prioridad);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $notificacion->id,
            'user_id' => $user->id,
            'mensaje' => 'Test mensaje',
        ]);
    }

    public function test_it_filters_notifications_by_user_and_global(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $manager = new NotificacionesManager();

        $manager->crear('Notif A', 'alta', $userA->id);
        $manager->crear('Notif B', 'media', $userB->id);
        $manager->crear('Notif Global', 'baja', null);

        $notificacionesUserA = $manager->noLeidas($userA->id);

        $this->assertCount(2, $notificacionesUserA);
        $this->assertTrue($notificacionesUserA->contains('mensaje', 'Notif A'));
        $this->assertTrue($notificacionesUserA->contains('mensaje', 'Notif Global'));
        $this->assertFalse($notificacionesUserA->contains('mensaje', 'Notif B'));
    }

    public function test_it_marks_a_notification_as_read(): void
    {
        $manager = new NotificacionesManager();
        $notificacion = $manager->crear('Mensaje a leer', 'alta', null);

        $result = $manager->marcarComoLeida($notificacion->id);

        $this->assertTrue($result);
        $this->assertDatabaseHas('notificaciones', [
            'id' => $notificacion->id,
            'leida' => 1,
        ]);
    }
}
