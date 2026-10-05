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

    public function test_it_updates_configured_model(): void
    {
        $this->artisan('copy:attr', ['model' => 'Talla'])
            ->expectsOutput('Actualizando atributos para el modelo: Talla')
            ->assertExitCode(0);
    }
}
