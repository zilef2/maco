<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fotos del producto por ángulo. Guardan el nombre del archivo dentro de
     * storage/app/public/produc_photos (la carpeta la expone el enlace
     * /storage de public/).
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (!Schema::hasColumn('productos', 'foto_frente')) {
                $table->string('foto_frente')->nullable()->after('instrucciones');
            }
            if (!Schema::hasColumn('productos', 'foto_perfil')) {
                $table->string('foto_perfil')->nullable()->after('foto_frente');
            }
            if (!Schema::hasColumn('productos', 'foto_trasera')) {
                $table->string('foto_trasera')->nullable()->after('foto_perfil');
            }
            if (!Schema::hasColumn('productos', 'foto_superior')) {
                $table->string('foto_superior')->nullable()->after('foto_trasera');
            }
        });
    }

    public function down(): void
    {
        $columnas = array_values(array_filter(
            ['foto_frente', 'foto_perfil', 'foto_trasera', 'foto_superior'],
            fn (string $columna) => Schema::hasColumn('productos', $columna)
        ));

        if (empty($columnas)) {
            return;
        }

        Schema::table('productos', function (Blueprint $table) use ($columnas) {
            $table->dropColumn($columnas);
        });
    }
};
