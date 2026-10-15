<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migra materias_primas → materials (mapeando ids para conservar las FK),
     * copia el historial de movimientos y elimina las tablas de materias_primas.
     */
    public function up(): void
    {
        if (!Schema::hasTable('materias_primas')) {
            return;
        }

        $ahora = now();

        // 1. Copiar materias_primas → materials, deduplicando por codigo_material.
        //    $mapeo[old_id] = new_id (para re-apuntar las FK que referencian materias_primas).
        $mapeo = [];
        $materias = DB::table('materias_primas')->orderBy('id')->get();

        foreach ($materias as $mp) {
            $codigo = $mp->codigo;

            $newId = $codigo !== null && $codigo !== ''
                ? DB::table('materials')->where('codigo_material', $codigo)->value('id')
                : null;

            if ($newId === null) {
                $newId = DB::table('materials')->insertGetId([
                    'codigo_material'   => $codigo,
                    'nombre_base'       => $mp->nombre,
                    'categoria'         => $mp->categoria,
                    'unidad_inventario' => $mp->unidad_medida,
                    'existencia_actual' => (float) ($mp->existencia_actual ?? 0),
                    'estado'            => 'Activo',
                    'created_at'        => $mp->created_at ?? $ahora,
                    'updated_at'        => $mp->updated_at ?? $ahora,
                ]);
            }

            $mapeo[(int) $mp->id] = (int) $newId;
        }

        // 2. Quitar las FK que aún apuntan a materias_primas
        //    (existen dos tablas: proceso_externos y procesos_externos)
        $tablasFk = [
            'proceso_externos'          => 'material_origen_id',
            'procesos_externos'         => 'material_origen_id',
            'ficha_tecnica_materiales'  => 'material_componente_id',
        ];
        foreach ($tablasFk as $tabla => $columna) {
            if (Schema::hasTable($tabla) && $this->tieneFk($tabla, $columna)) {
                Schema::table($tabla, function (Blueprint $table) use ($columna) {
                    $table->dropForeign([$columna]);
                });
            }
        }

        // 3. Re-apuntar los ids viejos a los nuevos (UPDATE en un solo CASE para
        //    evitar remapeos en cadena ej. 3→7 y 7→12)
        foreach ($tablasFk as $tabla => $columna) {
            $this->remapear($tabla, $columna, $mapeo);
        }
        $this->remapear('kit_tallers', 'material_id', $mapeo);

        // 4. Re-crear las FK apuntando a materials
        foreach ($tablasFk as $tabla => $columna) {
            if (Schema::hasTable($tabla) && !$this->tieneFk($tabla, $columna)) {
                Schema::table($tabla, function (Blueprint $table) use ($columna) {
                    $table->foreign($columna)->references('id')->on('materials');
                });
            }
        }

        // 5. Copiar el historial de movimientos con los ids mapeados
        if (Schema::hasTable('materia_prima_movimientos')) {
            foreach (DB::table('materia_prima_movimientos')->orderBy('id')->cursor() as $mov) {
                $materialId = $mapeo[(int) $mov->materia_prima_id] ?? null;
                if ($materialId === null) {
                    continue;
                }

                DB::table('material_movimientos')->insert([
                    'material_id'        => $materialId,
                    'tipo_movimiento'    => $mov->tipo_movimiento,
                    'cantidad'           => $mov->cantidad,
                    'existencia_anterior' => $mov->existencia_anterior,
                    'existencia_nueva'   => $mov->existencia_nueva,
                    'motivo'             => $mov->motivo,
                    'user_id'            => $mov->user_id,
                    'created_at'         => $mov->created_at,
                    'updated_at'         => $mov->updated_at,
                ]);
            }

            Schema::dropIfExists('materia_prima_movimientos');
        }

        // 6. Eliminar la tabla vieja
        Schema::dropIfExists('materias_primas');
    }

    /**
     * Verifica en information_schema si la columna tiene una FK activa.
     */
    private function tieneFk(string $tabla, string $columna): bool
    {
        $filas = DB::select('
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ', [$tabla, $columna]);

        return count($filas) > 0;
    }

    /**
     * Re-apunta en un solo UPDATE los ids viejos a los nuevos usando CASE.
     */
    private function remapear(string $tabla, string $columna, array $mapeo): void
    {
        if (empty($mapeo) || !Schema::hasTable($tabla)) {
            return;
        }

        $casos = '';
        $viejos = [];
        foreach ($mapeo as $old => $new) {
            $casos .= " WHEN {$old} THEN {$new}";
            $viejos[] = $old;
        }

        $in = implode(', ', $viejos);
        DB::statement(
            "UPDATE {$tabla} SET {$columna} = CASE {$columna}{$casos} END WHERE {$columna} IN ({$in})"
        );
    }

    /**
     * Solo recrea las tablas vacías para que el rollback no falle a nivel estructural.
     * Los datos migrados a materials no se restauran.
     */
    public function down(): void
    {
        if (!Schema::hasTable('materias_primas')) {
            Schema::create('materias_primas', function (Blueprint $table) {
                $table->string('codigo')->nullable();
                $table->string('nombre')->nullable();
                $table->string('categoria')->nullable();
                $table->string('unidad_medida')->nullable();
                $table->integer('existencia_actual')->nullable();
                $table->decimal('costo_promedio_ponderado', 62, 2)->default(0);
                $table->datetime('fecha_creacion')->nullable();
                $table->id();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('materia_prima_movimientos')) {
            Schema::create('materia_prima_movimientos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('materia_prima_id')->constrained('materias_primas')->onDelete('cascade');
                $table->string('tipo_movimiento')->default('ajuste');
                $table->decimal('cantidad', 12, 2)->default(0);
                $table->decimal('existencia_anterior', 12, 2)->default(0);
                $table->decimal('existencia_nueva', 12, 2)->default(0);
                $table->decimal('costo_unitario', 12, 2)->nullable();
                $table->string('motivo')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }
};
