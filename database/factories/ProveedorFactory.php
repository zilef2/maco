<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    public function definition(): array
    {
        $nombre = fake()->company();

        return [
            'codigo_proveedor' => 'PROV-' . fake()->unique()->numberBetween(100, 9999),
            'nit' => fake()->numerify('#########-#'),
            'nombre_comercial' => $nombre,
            'razon_social' => $nombre . ' S.A.S.',
            'direccion' => fake()->address(),
            'observaciones_direccion' => null,
            'correo_electronico' => fake()->companyEmail(),
            'ciudad' => fake()->city(),
            'telefono_principal' => fake()->phoneNumber(),
            'tipo_proveedor' => fake()->randomElement(Proveedor::TIPOS_PROVEEDOR),
            'que_suministra' => fake()->sentence(3),
            'persona_contacto' => fake()->name(),
            'celular_contacto' => fake()->numerify('3#########'),
            'forma_pago' => fake()->randomElement(Proveedor::FORMAS_PAGO),
            'tiempo_entrega' => '3 a 5 días',
            'estado' => 'Activo',
            'observaciones_generales' => null,
            'nombre' => $nombre,
            'categoria' => 'General',
        ];
    }
}
