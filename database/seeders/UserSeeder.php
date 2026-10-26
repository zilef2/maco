<?php

namespace Database\Seeders;

use App\helpers\MyModels;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $sexos = ['Masculino', 'Femenino'];
        $genPa = $this->validarEnv();
        $genericPas = $genPa[0];
        $theEmail = $genPa[1];
        $superadmin = User::create([
            'name' => 'Superadmin',
            'email' => $theEmail,
            'password' => bcrypt($genericPas.'0#*'.$genericPas), // 123_deeradmin00.+-*123_de  
            'email_verified_at' => date('Y-m-d H:i'),
            'sexo' => $sexos[0],
        ]);
        $superadmin->assignRole('superadmin');

        $liliana = User::create([
            'name' => 'liliana',
            'email' => $genPa[2],
            'password' => bcrypt('macondas+-'), //
            'email_verified_at' => date('Y-m-d H:i'),
            'sexo' => $sexos[1],
        ]);
        $liliana->assignRole('admin');

        $vectorCRUD = ['create', 'update', 'read', 'delete'];
        $vectorModelo = MyModels::CargosYModelos('soloModels');
        foreach ($vectorCRUD as $value) {
            foreach ($vectorModelo as $model) {
                $superadmin->givePermissionTo([$value.' '.$model]);
            }
        }

        $nombresGenericos = [
            'Alicia' => 707117711,
            'Jose' => 1034567890,
            'Juancho' => 1034567891,
        ];

        $rolesUser = MyModels::CargosYModelos('soloNombresRoles');

        foreach ($rolesUser as $rol) {
            foreach ($nombresGenericos as $key => $value) {
                $sexoRandom = (rand(0, 1));
                $unUsuario = User::create([
                    'name' => $key.' - '.$rol,
                    'email' => $key.'@'.$rol.'.com',
                    'password' => bcrypt($genPa[0].' _ '.$rol), // 135_d_ trabajador
                    'email_verified_at' => date('Y-m-d H:i'),
                    'sexo' => $sexos[$sexoRandom],
                    'celular' => $value * 2,
                    'identificacion' => $value,
                ]);
                $unUsuario->assignRole($rol);
            }
        }

    }

    public function validarEnv()
    {
        $genPa = [
            config('sap.gen'),
            config('sap.the_email'),
            config('sap.client_email'),
        ];
        if (is_null($genPa)) {
            dd('enviroment is null', $genPa);
            //			abort(403, 'No env');
        }

        return $genPa;
    }
}
