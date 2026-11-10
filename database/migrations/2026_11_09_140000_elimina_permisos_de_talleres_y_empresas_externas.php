<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Taller y Empresa externa dejaron de ser módulos independientes: su ficha se
 * administra desde Proveedor, así que sus permisos ya no tienen a qué apuntar.
 */
return new class extends Migration
{
    private const NOMBRES = [
        'create Taller',
        'update Taller',
        'read Taller',
        'delete Taller',
        'create EmpresaExterna',
        'update EmpresaExterna',
        'read EmpresaExterna',
        'delete EmpresaExterna',
    ];

    public function up(): void
    {
        DB::table('permissions')->whereIn('name', self::NOMBRES)->delete();
    }

    public function down(): void
    {
        $ahora = now();

        foreach (self::NOMBRES as $nombre) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $nombre,
                'guard_name' => 'web',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }
};
