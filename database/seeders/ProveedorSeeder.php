<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class ProveedorSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::role('superadmin')->first();
        $admin = User::role('admin')->first();

        $vectorCRUD = ['create', 'update', 'read', 'update2', 'delete']; //superadmin puede hacer todo
        $adminCRUD = ['create', 'update', 'read', 'update2']; //permisos que se le dan al admin
        $vectorModelo = ['Proveedor'];

        foreach ($vectorCRUD as $crud) {
            Permission::create(['name' => $crud . ' ' . $vectorModelo[0]]);
        }

        foreach ($vectorCRUD as $value) {
            foreach ($vectorModelo as $model) {
                $superadmin->givePermissionTo([$value.' '.$model]);
            }
        }

        foreach ($adminCRUD as $value) {
            foreach ($vectorModelo as $model) {
                $admin->givePermissionTo([$value.' '.$model]);
            }
        }

        Proveedor::create([
            'nombre' => 'Pedro Hernandez',
            'categoria' => 'Cuero',
            'celular1' => 812312312312,
        ]);
    }
}