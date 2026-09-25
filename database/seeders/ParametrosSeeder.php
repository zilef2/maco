<?php

namespace Database\Seeders;

use App\Models\ListaDesplegable;
use App\Models\Parametro;
use Illuminate\Database\Seeder;

class ParametrosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Parametro::create([
                'nombre'=> 'jefe',
                'valor'=> 'Liliana Madrid',
                'valor2'=> 'macondascolombia@gmail.com',
                'valor_date'=> '2026-09-10',
                'valor_int'=> null,
                'valor_double'=> null,
                'valor_string1m'=> null,
        ]);
        
    }
}
