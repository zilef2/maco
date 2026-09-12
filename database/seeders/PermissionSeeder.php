<?php

namespace Database\Seeders;

use App\helpers\MyModels;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
		$AllRols = MyModels::CargosYModelos('soloNombresRoles');
        /*
        ejemplo:
            array:3 [
                0 => "trabajador"
                1 => "admin"
            ]
        */
		foreach ($AllRols as $all_rol) {
            Permission::create(['name' => 'is' . $all_rol]); //ejemplo: isadmin,, istrabajador
		}
        
        //reporte
        $vectorModelo = MyModels::CargosYModelos('soloModels');
;
        $vectorCRUD = ['create', 'update','read','delete'];
        foreach ($vectorCRUD as $crud) {
            foreach ($vectorModelo as $model) {
                Permission::create(['name' => $crud.' '.$model]); //ejemplo create role
            }
        }
    }
}
