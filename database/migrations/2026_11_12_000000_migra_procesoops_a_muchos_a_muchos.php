<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Convierte la relación Procesoop ↔ Producto de uno-a-muchos a muchos-a-muchos.
 *
 * Antes, cada fila de procesoops pertenecía a un producto y guardaba el coste
 * (producto_id + coste_proceso). Ahora procesoops es un catálogo de nombres y el
 * coste vive en el pivote procesoop_producto, junto a cada producto.
 *
 * Los datos existentes se convierten: se desduplican los nombres (el primer
 * registro de cada nombre normalizado queda como canónico) y cada par
 * proceso-producto pasa al pivote con su coste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procesoop_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procesoop_id')->constrained('procesoops')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->decimal('coste_proceso', 62, 2)->default(0);
            $table->timestamps();

            $table->unique(['procesoop_id', 'producto_id']);
        });

        // Catálogo de nombres: nombre normalizado => id canónico.
        $catalogo = [];
        $duplicados = [];

        $filas = DB::table('procesoops')
            ->orderBy('id')
            ->get(['id', 'nombre', 'producto_id', 'coste_proceso']);

        foreach ($filas as $fila) {
            $clave = $this->normalizar((string) $fila->nombre);
            $canonico = $catalogo[$clave] ?? null;

            if ($canonico === null) {
                $catalogo[$clave] = $fila->id;
                $canonico = $fila->id;
            } elseif ($canonico !== $fila->id) {
                $duplicados[] = $fila->id;
            }

            if (!empty($fila->producto_id)) {
                DB::table('procesoop_producto')->insertOrIgnore([
                    'procesoop_id'  => $canonico,
                    'producto_id'   => $fila->producto_id,
                    'coste_proceso' => $fila->coste_proceso,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }

        // Elimina las filas cuyo nombre ya vive en el registro canónico.
        if (!empty($duplicados)) {
            DB::table('procesoops')->whereIn('id', $duplicados)->delete();
        }

        // Las columnas que ya pertenecen al pivote salen del catálogo.
        Schema::table('procesoops', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropColumn(['producto_id', 'coste_proceso']);
        });

        Schema::table('procesoops', function (Blueprint $table) {
            $table->unique('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('procesoops', function (Blueprint $table) {
            $table->dropUnique(['nombre']);
        });

        Schema::table('procesoops', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('nombre');
            $table->decimal('coste_proceso', 62, 2)->default(0)->after('producto_id');
        });

        // Reconstruye una fila por cada par proceso-producto del pivote.
        foreach (DB::table('procesoops')->get(['id', 'nombre']) as $procesoop) {
            $pares = DB::table('procesoop_producto')
                ->where('procesoop_id', $procesoop->id)
                ->orderBy('id')
                ->get();
            $primero = true;

            foreach ($pares as $par) {
                $datos = [
                    'producto_id'   => $par->producto_id,
                    'coste_proceso' => $par->coste_proceso,
                ];

                if ($primero) {
                    DB::table('procesoops')->where('id', $procesoop->id)->update($datos);
                    $primero = false;
                } else {
                    DB::table('procesoops')->insert($datos + [
                        'nombre'     => $procesoop->nombre,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Schema::dropIfExists('procesoop_producto');

        Schema::table('procesoops', function (Blueprint $table) {
            $table->foreign('producto_id')->references('id')->on('productos')->cascadeOnDelete();
        });
    }

    /** Nombre comparable: sin mayúsculas, sin acentos y con espacios simples. */
    private function normalizar(string $nombre): string
    {
        $nombre = Str::ascii(trim($nombre));

        return preg_replace('/\s+/', ' ', mb_strtolower($nombre)) ?? mb_strtolower($nombre);
    }
};
