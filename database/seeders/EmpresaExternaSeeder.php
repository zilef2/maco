<?php

namespace Database\Seeders;

use App\Models\EmpresaExterna;
use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class EmpresaExternaSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = [
            ['nombre_comercial' => 'Bordados Ramírez S.A.', 'que_suministra' => 'Bordado', 'direccion' => 'Calle 45 #12-30, Bogotá', 'telefono_principal' => '310 456 7890'],
            ['nombre_comercial' => 'Confecciones hilaneda Ltda.', 'que_suministra' => 'Confección', 'direccion' => 'Cra 30 #67-44, Medellín', 'telefono_principal' => '315 223 4455'],
            ['nombre_comercial' => 'Grabados Laser Andino', 'que_suministra' => 'Grabado', 'direccion' => 'Av. El Dorado #103-15, Bogotá', 'telefono_principal' => '301 987 6543'],
            ['nombre_comercial' => 'Estampados Creativos', 'que_suministra' => 'Estampado', 'direccion' => 'Calle 10 #4-35, Bucaramanga', 'telefono_principal' => '320 111 2233'],
            ['nombre_comercial' => 'Herrajes Industriales El Yunque', 'que_suministra' => 'Herrajes', 'direccion' => 'Zona Industrial #8, Itagüí', 'telefono_principal' => '313 555 6677'],
            ['nombre_comercial' => 'Brocalados Doña Rosa', 'que_suministra' => 'Brocado', 'direccion' => 'Carrera 7 #95-40, Bogotá', 'telefono_principal' => '318 444 9900'],
            ['nombre_comercial' => 'Lavandería Industrial JeanWash', 'que_suministra' => 'Lavado de prendas', 'direccion' => 'Vía La Felisa Bodega 12, Medellín', 'telefono_principal' => '312 777 8899'],
            ['nombre_comercial' => 'Etiquetas y Empaques Sigma', 'que_suministra' => 'Etiquetas', 'direccion' => 'Calle 26 #52-30, Bogotá', 'telefono_principal' => '305 121 3141'],
        ];

        foreach ($empresas as $empresa) {
            EmpresaExterna::firstOrCreate(
                ['nombre_comercial' => $empresa['nombre_comercial']],
                $empresa + [
                    'tipo_proveedor' => Proveedor::TIPOS_PROVEEDOR[1],
                    'estado' => Proveedor::ESTADOS[0],
                ]
            );
        }
    }
}
