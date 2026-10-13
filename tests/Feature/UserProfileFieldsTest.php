<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserProfileFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        Role::firstOrCreate(['name' => 'superadmin']);
        Role::firstOrCreate(['name' => 'trabajador']);

        $admin = User::factory()->create([
            'identificacion' => '1111111111',
            'sexo' => 'Masculino',
        ]);
        $admin->assignRole('superadmin');
        $this->actingAs($admin);

        return $admin;
    }

    public function test_store_saves_fecha_nacimiento_area_y_cargo(): void
    {
        $this->superadmin();

        $response = $this->post('/user', [
            'name' => 'Nuevo Trabajador',
            'email' => 'nuevo@test.com',
            'role' => 'trabajador',
            'identificacion' => '9876543210',
            'sexo' => 'Masculino',
            'celular' => '3001112223',
            'area' => 'Calidad',
            'cargo' => 'Inspector',
            'fecha_nacimiento' => '2000-01-15',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user = User::where('email', 'nuevo@test.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('2000-01-15', $user->fecha_nacimiento);
        $this->assertSame('Calidad', $user->area);
        $this->assertSame('Inspector', $user->cargo);
    }

    public function test_update_guarda_fecha_nacimiento_area_y_cargo(): void
    {
        $this->superadmin();

        $target = User::factory()->create([
            'email' => 'objetivo@test.com',
            'identificacion' => '1234567890',
            'sexo' => 'Masculino',
            'fecha_nacimiento' => '1985-03-10',
            'area' => 'Vieja',
            'cargo' => 'Viejo',
        ]);
        $target->assignRole('trabajador');

        $response = $this->put("/user/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'trabajador',
            'identificacion' => '1234567890',
            'sexo' => ['title' => 'Femenino', 'value' => 'Femenino'],
            'celular' => '3001234567',
            'area' => 'Produccion',
            'cargo' => 'Operario',
            'fecha_nacimiento' => '1990-05-15',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $target->refresh();
        $this->assertSame('1990-05-15', $target->fecha_nacimiento);
        $this->assertSame('Produccion', $target->area);
        $this->assertSame('Operario', $target->cargo);
        $this->assertSame('Femenino', $target->sexo);
    }

    public function test_update_permite_limpiar_fecha_nacimiento(): void
    {
        $this->superadmin();

        $target = User::factory()->create([
            'email' => 'sinfecha@test.com',
            'identificacion' => '5555555555',
            'sexo' => 'Masculino',
            'fecha_nacimiento' => '1980-08-08',
        ]);
        $target->assignRole('trabajador');

        $response = $this->put("/user/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'trabajador',
            'identificacion' => '5555555555',
            'sexo' => 'Masculino',
            'area' => null,
            'cargo' => null,
            'fecha_nacimiento' => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $target->refresh();
        $this->assertNull($target->fecha_nacimiento);
        $this->assertNull($target->area);
        $this->assertNull($target->cargo);
    }
}
