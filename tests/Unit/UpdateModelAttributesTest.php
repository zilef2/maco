<?php

namespace Tests\Unit;

use Tests\TestCase;

class UpdateModelAttributesTest extends TestCase
{
    public function test_it_fails_when_model_is_not_configured(): void
    {
        $this->artisan('copy:attr', ['model' => 'ModeloInexistente'])
            ->expectsOutput("El modelo 'ModeloInexistente' no está configurado en el array.")
            ->assertExitCode(1);
    }

    public function test_it_rejects_a_real_model_that_is_not_allowlisted(): void
    {
        // 'Talla' existe como modelo en la app, pero no está en el array $modelos
        // del comando, por lo que el allow-list debe rechazarlo sin modificar archivos.
        $this->artisan('copy:attr', ['model' => 'Talla'])
            ->expectsOutput("El modelo 'Talla' no está configurado en el array.")
            ->assertExitCode(1);
    }
}
