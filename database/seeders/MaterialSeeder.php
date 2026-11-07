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
        $observaciones = [
            'Material de alta rotación.',
            'Requiere compra anticipada.',
            'Sujeto a variación de precio.',
            'Verificar disponibilidad antes de programar OP.',
            'Almacenar en lugar seco y ventilado.',
            'Revisar lote al recibir.',
            'No exponer a humedad prolongada.',
            'Certificado de calidad disponible.',
            'Mantener separado por color y lote.',
            'Controlar merma en corte.',
            'Proveedor único, considerar alternativa.',
            'Consumo variable según temporada.',
            'Revisar fecha de vencimiento del stock.',
            'Material de uso transversal en varias fichas.',
            'Solicitar muestra antes de compra grande.',
            null, null, null, null,
        ];
        $proveedores = Proveedor::pluck('id')->all();

        for ($i = 1; $i <= self::CANTIDAD; $i++) {
            // Un material maneja colores o una variante adicional, nunca ambas.
            $manejaColor = (bool) random_int(0, 1);
            $manejaOtra = !$manejaColor && (bool) random_int(0, 1);

            $material = Material::create([
                'codigo_material'            => 'MAT-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nombre_base'                => $bases[array_rand($bases)] . ' ' . $adj[array_rand($adj)],
                'categoria'                  => $categorias[array_rand($categorias)],
                'unidad_inventario'          => $unidades[array_rand($unidades)],
                'existencia_actual'          => round(rand(0, 50000) / 100, 2),
                'maneja_color'               => $manejaColor,
                'maneja_talla'               => $manejaOtra || (bool) random_int(0, 1),
                'maneja_otra_variante'       => $manejaOtra,
                'estado'                     => $estados[array_rand($estados)],
                'modelo_diseno_acabado'      => $manejaColor
                    ? 'Color'
                    : ($manejaOtra ? 'Talla' : $variante[array_rand($variante)]),
                'color'                      => $manejaColor ? 'Rojo, Azul, Negro, Beige' : null,
                'talla_tamano'               => $manejaOtra ? 'S, M, L, XL' : null,
                'proveedor_habitual'         => $proveedores ? $proveedores[array_rand($proveedores)] : null,
                'material_procesado'         => random_int(0, 1),
                'observaciones'              => $observaciones[array_rand($observaciones)],
            ]);

            if ($manejaColor) {
                $material->sincronizarVariantes(['Rojo', 'Azul', 'Negro', 'Beige'], []);
            } elseif ($manejaOtra) {
                $material->sincronizarVariantes([], ['S', 'M', 'L', 'XL']);
            }
        }
    }
}
