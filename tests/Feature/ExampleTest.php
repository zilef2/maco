<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_show_environment_info()
    {
        dump([
            'Versión PHP' => phpversion(),
            'Ejecutable PHP' => PHP_BINARY,
            'Archivo php.ini' => php_ini_loaded_file(),
            'Conexión DB' => env('DB_CONNECTION', config('database.default')),
            'Nombre DB' => DB::connection()->getDatabaseName(),
        ]);

        $this->assertTrue(true);
    }
}
