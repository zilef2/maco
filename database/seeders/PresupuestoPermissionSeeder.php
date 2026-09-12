<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PresupuestoPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'create Presupuesto',
            'read Presupuesto',
            'update Presupuesto',
            'delete Presupuesto',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        /** @var Role $role */
        $roles = Role::whereIn('name', ['superadmin', 'admin'])->get();
        foreach ($roles as $role) {
            foreach ($permissions as $permissionName) {
                if (!$role->hasPermissionTo($permissionName)) {
                    $role->givePermissionTo($permissionName);
                }
            }
        }
    }
}
