<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // La app administra los materiales desde la tabla `materials` (codigo_material),
        // no desde `materias_primas`: la FK debe apuntar a materials.
        Schema::table('kit_tallers', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
        });

        DB::statement('
            ALTER TABLE kit_tallers
            ADD CONSTRAINT kit_tallers_material_id_foreign
            FOREIGN KEY (material_id) REFERENCES materials (id)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kit_tallers', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
        });

        DB::statement('
            ALTER TABLE kit_tallers
            ADD CONSTRAINT kit_tallers_material_id_foreign
            FOREIGN KEY (material_id) REFERENCES materias_primas (id)
        ');
    }
};
