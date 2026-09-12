<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ProveedorSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::role('superadmin')->first();
        $admin = User::role('admin')->first();

        $vectorCRUD = ['create', 'update', 'read', 'update2', 'delete'];
        $adminCRUD = ['create', 'update', 'read', 'update2'];
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
    }
}