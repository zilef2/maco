<?php

namespace Database\Seeders;

use App\Models\EmpresaExterna;
use Illuminate\Database\Seeder;

class EmpresaExternaSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = [
            ['nombre' => 'Bordados Ramírez S.A.', 'actividad' => 'Bordado', 'direccion' => 'Calle 45 #12-30, Bogotá', 'numero_contacto' => '310 456 7890'],
            ['nombre' => 'Confecciones hilaneda Ltda.', 'actividad' => 'Confección', 'direccion' => 'Cra 30 #67-44, Medellín', 'numero_contacto' => '315 223 4455'],
            ['nombre' => 'Grabados Laser Andino', 'actividad' => 'Grabado', 'direccion' => 'Av. El Dorado #103-15, Bogotá', 'numero_contacto' => '301 987 6543'],
            ['nombre' => 'Estampados Creativos', 'actividad' => 'Estampado', 'direccion' => 'Calle 10 #4-35, Bucaramanga', 'numero_contacto' => '320 111 2233'],
            ['nombre' => 'Herrajes Industriales El Yunque', 'actividad' => 'Herrajes', 'direccion' => 'Zona Industrial #8, Itagüí', 'numero_contacto' => '313 555 6677'],
            ['nombre' => 'Brocalados Doña Rosa', 'actividad' => 'Brocado', 'direccion' => 'Carrera 7 #95-40, Bogotá', 'numero_contacto' => '318 444 9900'],
            ['nombre' => 'Lavandería Industrial JeanWash', 'actividad' => 'Lavado de prendas', 'direccion' => 'Vía La Felisa Bodega 12, Medellín', 'numero_contacto' => '312 777 8899'],
            ['nombre' => 'Etiquetas y Empaques Sigma', 'actividad' => 'Etiquetas', 'direccion' => 'Calle 26 #52-30, Bogotá', 'numero_contacto' => '305 121 3141'],
        ];

        foreach ($empresas as $empresa) {
            EmpresaExterna::firstOrCreate(
                ['nombre' => $empresa['nombre']],
                $empresa
            );
        }
    }
}
