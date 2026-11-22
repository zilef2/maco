<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    /** Cuántos materiales aleatorios crear (editar aquí) */
    private const CANTIDAD = 30;

    /**
     * Run the database seeds.
     */public function run(): void
    {
        $proveedores = Proveedor::pluck('id')->all();
        $proveedorId = $proveedores ? $proveedores[array_rand($proveedores)] : null;

        // 1. Crear el material padre genérico (ej. TEL-ALG-001)
        Material::create([
            'generico'             => true,
            'nombre_base'          => 'Algodón',
            'categoria'            => 'Tela',
            'unidad_inventario'    => 'Metros',
            'existencia_actual'    => 0,
            'maneja_color'         => false,
            'maneja_talla'         => false,
            'maneja_otra_variante' => false,
            'estado'               => 'Activo',
            'material_procesado'   => 0,
            'observaciones'        => 'Familia genérica base.',
        ]);

        // 2. Crear 4 hijos concretos bajo esa misma familia (TEL-ALG-001-01 .. 04)
        $hijos = [
            ['detalle' => 'Premium',   'color' => true,  'talla' => false],
            ['detalle' => 'Standard',  'color' => false, 'talla' => true],
            ['detalle' => 'Reforzada', 'color' => true,  'talla' => false],
            ['detalle' => 'Natural',   'color' => false, 'talla' => false],
        ];

        foreach ($hijos as $item) {
            $material = Material::create([
                'generico'              => false,
                'nombre_base'           => 'Algodón',
                'categoria'             => 'Tela',
                'unidad_inventario'     => 'Metros',
                'existencia_actual'     => round(rand(1000, 50000) / 100, 2),
                'maneja_color'          => $item['color'],
                'maneja_talla'          => $item['talla'],
                'maneja_otra_variante'  => $item['talla'],
                'estado'                => 'Activo',
                'modelo_diseno_acabado' => $item['color'] ? 'Color' : ($item['talla'] ? 'Talla' : $item['detalle']),
                'color'                 => $item['color'] ? 'Rojo, Azul, Negro, Beige' : null,
                'talla_tamano'          => $item['talla'] ? 'S, M, L, XL' : null,
                'proveedor_habitual'    => $proveedorId,
                'material_procesado'    => 0,
                'observaciones'         => 'Material concreto ' . $item['detalle'] . '.',
            ]);

            if ($item['color']) {
                $material->sincronizarVariantes(['Rojo', 'Azul', 'Negro', 'Beige'], []);
            } elseif ($item['talla']) {
                $material->sincronizarVariantes([], ['S', 'M', 'L', 'XL']);
            }
        }
    }
}
