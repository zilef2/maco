<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    /** Cuántos materiales aleatorios crear (editar aquí) */
    private const CANTIDAD = 30;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bases = [
            'Tela Algodón', 'Tela Poliéster', 'Tela Mezclilla', 'Tela Licra', 'Tela Gabardina',
            'Cuero Sintético', 'Hilo Nylon', 'Hilo Algodón', 'Botón Resina', 'Botón Metal',
            'Cremallera Metal', 'Cremallera Nylon', 'Etiqueta Térmica', 'Forro Satín',
            'Entretela Adhesiva', 'Elástano', 'Ajiné', 'Malla Textil', 'Denim', 'Popelina',
        ];
        $adj = ['Premium', 'Standard', 'Económico', 'Reforzado', 'Natural', 'Sintético', 'Delgado', 'Grueso'];
        $categorias = ['Tela', 'Cuero', 'Hilo', 'Accesorio', 'Forro', 'Entretela', 'Etiqueta'];
        $unidades = ['Metros', 'Kilos', 'Unidades', 'Rollos', 'Yardas'];
        $estados = ['Activo', 'Activo', 'Activo', 'Inactivo', 'Descontinuado'];
        $variante = ['Color', 'Talla', 'Otro', null, null];
        $proveedores = [
            'Textiles del Norte', 'Importadora El Éxito', 'Distribuidora Andina', 'Comercializadora Sur',
            'Telas & Hilos SAS', 'Proveedora Global', null,
        ];
        $observaciones = [
            'Material de alta rotación.',
            'Requiere compra anticipada.',
            'Sujeto a variación de precio.',
            'Verificar disponibilidad antes de programar OP.',
            null, null, null,
        ];

        for ($i = 1; $i <= self::CANTIDAD; $i++) {
            Material::create([
                'codigo_material'            => 'MAT-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nombre_base'                => $bases[array_rand($bases)] . ' ' . $adj[array_rand($adj)],
                'categoria'                  => $categorias[array_rand($categorias)],
                'unidad_inventario'          => $unidades[array_rand($unidades)],
                'existencia_actual'          => round(rand(0, 50) / 10, 1)*100,
                'maneja_color'               => (bool) random_int(0, 1),
                'maneja_talla'               => (bool) random_int(0, 1),
                'maneja_otra_variante'       => (bool) random_int(0, 1),
                'estado'                     => $estados[array_rand($estados)],
                'variante_atributo_sugerido' => $variante[array_rand($variante)],
                'proveedor_habitual'         => $proveedores[array_rand($proveedores)],
                'observaciones'              => $observaciones[array_rand($observaciones)],
            ]);
        }
    }
}
