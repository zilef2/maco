<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('kit_tallers', 'kit_procesos');

        // estado_requerido queda nullable: el kit se crea con la OP y espera
        // a que el usuario lo digite.
        Schema::table('kit_procesos', function (Blueprint $table) {
            $table->string('estado_requerido')->nullable()->change();
        });

        DB::table('permissions')
            ->where('name', 'like', '%KitTaller%')
            ->update(['name' => DB::raw("REPLACE(name, 'KitTaller', 'KitProceso')")]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'like', '%KitProceso%')
            ->update(['name' => DB::raw("REPLACE(name, 'KitProceso', 'KitTaller')")]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::table('kit_procesos', function (Blueprint $table) {
            $table->string('estado_requerido')->nullable(false)->change();
        });

        Schema::rename('kit_procesos', 'kit_tallers');
    }
};
