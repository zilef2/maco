<?php

namespace Database\Seeders;

use App\Models\User;
use App\helpers\MyModels;
use Illuminate\Database\Seeder;

class UserSeederReal extends Seeder {
	
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run() {
		$sexos = ['Masculino', 'Femenino'];
		$arrayRoles = MyModels::CargosYModelos('soloNombresRoles');

		$datareal = [
			[
				'José pruebas',
				'alejofg2@gmail.com',
				1193231624,//2
				'3012124273',
				1600000,//4
				'01/08/2023',
			],
		];
		
		$unUsuario = User::create(['name'              => $datareal[0][0],
		                           'email'             => $datareal[0][1],
		                           'password'          => \bcrypt($datareal[0][2].'+'),
		                           'email_verified_at' => date('Y-m-d H:i'),
		                           'fecha_de_ingreso'  => date('Y-m-d', strtotime($datareal[0][5])),
		                           'cedula'            => $datareal[0][2],
		                           'sexo'              => $sexos[1],
		                           'celular'           => $datareal[0][3],
		                           'salario'           => $datareal[0][4],
		                          ]);
		$unUsuario->assignRole($arrayRoles[0]);
	}
}
