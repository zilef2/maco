<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            if (Schema::hasColumn('materials', 'variante_atributo_sugerido') && !Schema::hasColumn('materials', 'modelo_diseno_acabado')) {
                $table->renameColumn('variante_atributo_sugerido', 'modelo_diseno_acabado');
            } elseif (!Schema::hasColumn('materials', 'modelo_diseno_acabado')) {
                $table->string('modelo_diseno_acabado')->nullable()->after('estado');
            }

            if (!Schema::hasColumn('materials', 'color')) {
                $table->string('color')->nullable()->after('modelo_diseno_acabado');
            }

            if (!Schema::hasColumn('materials', 'talla_tamano')) {
                $table->string('talla_tamano')->nullable()->after('color');
            }
        });

        if (Schema::hasTable('listas_desplegables')) {
            DB::table('listas_desplegables')
                ->where('subcategoria', 'variante_atributo_sugerido')
                ->update(['subcategoria' => 'modelo_diseno_acabado']);
        }

        if (Schema::hasTable('material_variants')) {
            $materiales = DB::table('materials')->select('id')->get();
            foreach ($materiales as $material) {
                $colores = DB::table('material_variants')
                    ->where('material_id', $material->id)
                    ->where('tipo', 'color')
                    ->orderBy('orden')
                    ->orderBy('id')
                    ->pluck('nombre')
                    ->all();

                $tallas = DB::table('material_variants')
                    ->where('material_id', $material->id)
                    ->where('tipo', 'variante')
                    ->orderBy('orden')
                    ->orderBy('id')
                    ->pluck('nombre')
                    ->all();

                $updates = [];
                if (!empty($colores)) {
                    $updates['color'] = implode(', ', $colores);
                }
                if (!empty($tallas)) {
                    $updates['talla_tamano'] = implode(', ', $tallas);
                }
                if (!empty($updates)) {
                    DB::table('materials')->where('id', $material->id)->update($updates);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            if (Schema::hasColumn('materials', 'color')) {
                $table->dropColumn('color');
            }
            if (Schema::hasColumn('materials', 'talla_tamano')) {
                $table->dropColumn('talla_tamano');
            }
            if (Schema::hasColumn('materials', 'modelo_diseno_acabado') && !Schema::hasColumn('materials', 'variante_atributo_sugerido')) {
                $table->renameColumn('modelo_diseno_acabado', 'variante_atributo_sugerido');
            }
        });

        if (Schema::hasTable('listas_desplegables')) {
            DB::table('listas_desplegables')
                ->where('subcategoria', 'modelo_diseno_acabado')
                ->update(['subcategoria' => 'variante_atributo_sugerido']);
        }
    }
};

