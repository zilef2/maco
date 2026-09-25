<?php

namespace Database\Seeders;

use App\Models\Producto;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
        public function run(): void
    {
        $productos = [
            [
                'codigo'           => 'PROD-001',
                'nombre'           => 'Auriculares Pro X',
                'descripcion'      => 'Auriculares inalámbricos con cancelación activa de ruido, 40h de batería y sonido Hi-Res.',
                'precio_normal'    => 89900,
                'precio_ponderado' => 74900,
                'precio_preventa'  => 69900,
                'esta_activo'      => true,
                'foto_principal'   => 'productos/zap.png',
                'foto_lateral'     => 'productos/auriculares-pro-x-lateral.jpg',
                'foto_trasera'     => 'productos/auriculares-pro-x-trasera.jpg',
                'foto_derecha'     => 'productos/auriculares-pro-x-derecha.jpg',
                'foto_izquierda'   => 'productos/auriculares-pro-x-izquierda.jpg',
            ],
            [
                'codigo'           => 'PROD-002',
                'nombre'           => 'Zapatillas Runner',
                'descripcion'      => 'Zapatillas deportivas con amortiguación de alto impacto, ideales para running y entrenamiento.',
                'precio_normal'    => 129900,
                'precio_ponderado' => 109900,
                'precio_preventa'  => 99900,
                'esta_activo'      => true,
                'foto_principal'   => 'productos/zapatillas-runner-principal.jpg',
                'foto_lateral'     => 'productos/zapatillas-runner-lateral.jpg',
                'foto_trasera'     => 'productos/zapatillas-runner-trasera.jpg',
                'foto_derecha'     => 'productos/zapatillas-runner-derecha.jpg',
                'foto_izquierda'   => 'productos/zapatillas-runner-izquierda.jpg',
            ],
            [
                'codigo'           => 'PROD-003',
                'nombre'           => 'Cafetera Aroma',
                'descripcion'      => 'Cafetera de goteo con capacidad de 1.5L, filtro reutilizable y función de mantenimiento de temperatura.',
                'precio_normal'    => 79900,
                'precio_ponderado' => 69900,
                'precio_preventa'  => null,
                'esta_activo'      => true,
                'foto_principal'   => 'productos/cafetera-aroma-principal.jpg',
                'foto_lateral'     => 'productos/cafetera-aroma-lateral.jpg',
                'foto_trasera'     => 'productos/cafetera-aroma-trasera.jpg',
                'foto_derecha'     => 'productos/cafetera-aroma-derecha.jpg',
                'foto_izquierda'   => 'productos/cafetera-aroma-izquierda.jpg',
            ],
            [
                'codigo'           => 'PROD-004',
                'nombre'           => 'Chaqueta Urbana',
                'descripcion'      => 'Chaqueta impermeable con capucha desmontable, forro térmico y múltiples bolsillos.',
                'precio_normal'    => 59900,
                'precio_ponderado' => 49900,
                'precio_preventa'  => 44900,
                'esta_activo'      => true,
                'foto_principal'   => 'productos/chaqueta-urbana-principal.jpg',
                'foto_lateral'     => 'productos/chaqueta-urbana-lateral.jpg',
                'foto_trasera'     => 'productos/chaqueta-urbana-trasera.jpg',
                'foto_derecha'     => 'productos/chaqueta-urbana-derecha.jpg',
                'foto_izquierda'   => 'productos/chaqueta-urbana-izquierda.jpg',
            ],
            [
                'codigo'           => 'PROD-005',
                'nombre'           => 'Smartwatch Fit',
                'descripcion'      => 'Reloj inteligente con monitor de ritmo cardíaco, GPS integrado y 14 días de batería.',
                'precio_normal'    => 149900,
                'precio_ponderado' => 129900,
                'precio_preventa'  => 119900,
                'esta_activo'      => true,
                'foto_principal'   => 'productos/smartwatch-fit-principal.jpg',
                'foto_lateral'     => 'productos/smartwatch-fit-lateral.jpg',
                'foto_trasera'     => 'productos/smartwatch-fit-trasera.jpg',
                'foto_derecha'     => 'productos/smartwatch-fit-derecha.jpg',
                'foto_izquierda'   => 'productos/smartwatch-fit-izquierda.jpg',
            ],
            [
                'codigo'           => 'PROD-006',
                'nombre'           => 'Mochila Trek 30',
                'descripcion'      => 'Mochila de senderismo de 30L, resistente al agua, con compartimento para laptop de 15".',
                'precio_normal'    => 49900,
                'precio_ponderado' => 42900,
                'precio_preventa'  => null,
                'esta_activo'      => true,
                'foto_principal'   => 'productos/mochila-trek-30-principal.jpg',
                'foto_lateral'     => 'productos/mochila-trek-30-lateral.jpg',
                'foto_trasera'     => 'productos/mochila-trek-30-trasera.jpg',
                'foto_derecha'     => 'productos/mochila-trek-30-derecha.jpg',
                'foto_izquierda'   => 'productos/mochila-trek-30-izquierda.jpg',
            ],
            [
                'codigo'           => 'PROD-007',
                'nombre'           => 'Teclado Mecánico RGB',
                'descripcion'      => 'Teclado mecánico con switches rojos, retroiluminación RGB personalizable y reposamuñecas.',
                'precio_normal'    => 119900,
                'precio_ponderado' => 99900,
                'precio_preventa'  => 89900,
                'esta_activo'      => true,
                'foto_principal'   => 'productos/teclado-mecanico-rgb-principal.jpg',
                'foto_lateral'     => 'productos/teclado-mecanico-rgb-lateral.jpg',
                'foto_trasera'     => 'productos/teclado-mecanico-rgb-trasera.jpg',
                'foto_derecha'     => 'productos/teclado-mecanico-rgb-derecha.jpg',
                'foto_izquierda'   => 'productos/teclado-mecanico-rgb-izquierda.jpg',
            ],
            [
                'codigo'           => 'PROD-008',
                'nombre'           => 'Lámpara de Escritorio LED',
                'descripcion'      => 'Lámpara LED con 3 temperaturas de color, 5 niveles de brillo y puerto USB de carga.',
                'precio_normal'    => 39900,
                'precio_ponderado' => 34900,
                'precio_preventa'  => null,
                'esta_activo'      => false,
                'foto_principal'   => 'productos/lampara-led-principal.jpg',
                'foto_lateral'     => 'productos/lampara-led-lateral.jpg',
                'foto_trasera'     => 'productos/lampara-led-trasera.jpg',
                'foto_derecha'     => 'productos/lampara-led-derecha.jpg',
                'foto_izquierda'   => 'productos/lampara-led-izquierda.jpg',
            ],
            [
                'codigo'           => 'PROD-009',
                'nombre'           => 'Botella Térmica 1L',
                'descripcion'      => 'Botella de acero inoxidable con doble pared, mantiene bebidas frías 24h o calientes 12h.',
                'precio_normal'    => 29900,
                'precio_ponderado' => 24900,
                'precio_preventa'  => null,
                'esta_activo'      => true,
                'foto_principal'   => 'productos/botella-termica-principal.jpg',
                'foto_lateral'     => 'productos/botella-termica-lateral.jpg',
                'foto_trasera'     => 'productos/botella-termica-trasera.jpg',
                'foto_derecha'     => 'productos/botella-termica-derecha.jpg',
                'foto_izquierda'   => 'productos/botella-termica-izquierda.jpg',
            ],
            [
                'codigo'           => 'PROD-010',
                'nombre'           => 'Gafas de Sol Polarizadas',
                'descripcion'      => 'Gafas con lentes polarizadas UV400, montura ligera de acetato y estuche rígido incluido.',
                'precio_normal'    => 45900,
                'precio_ponderado' => 38900,
                'precio_preventa'  => 34900,
                'esta_activo'      => true,
                'foto_principal'   => 'productos/gafas-sol-principal.jpg',
                'foto_lateral'     => 'productos/gafas-sol-lateral.jpg',
                'foto_trasera'     => 'productos/gafas-sol-trasera.jpg',
                'foto_derecha'     => 'productos/gafas-sol-derecha.jpg',
                'foto_izquierda'   => 'productos/gafas-sol-izquierda.jpg',
            ],
        ];

        foreach ($productos as $producto) {
            Producto::updateOrCreate(
                ['codigo' => $producto['codigo']],
                $producto
            );
        }
    }
}
