<?php

use App\Models\Material;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El código del material deja de ser un consecutivo global (MAT-30) y pasa a
 * armarse con el par categoría/base: TEX-LFI-4.
 */
return new class extends Migration
{
    /**
     * Formato que tenían los códigos al momento de esta migración. Se copia aquí
     * en vez de leerla del modelo: el modelo cambia con el tiempo y la migración
     * tiene que quedar reproducible en un migrate fresh.
     */
    private const PATRON_LEGADO = '/^[A-Z]{1,3}-[A-Z]{1,3}-\d{1,4}$/';

    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('codigo_categoria', 3)->nullable()->after('codigo_material');
            $table->string('codigo_base', 3)->nullable()->after('categoria');
        });

        $this->reescribirCodigosLegado();
        $this->protegerUnicidad();
    }

    public function down(): void
    {
        if ($this->indiceUnicoExiste()) {
            Schema::table('materials', function (Blueprint $table) {
                $table->dropUnique('materials_codigo_material_unique');
            });
        }

        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['codigo_categoria', 'codigo_base']);
        });
    }

    /**
     * Los materiales que ya traen el formato CAT-BASE-N conservan su número y
     * prestan el par de su propio código; los MAT-## (y cualquier código que no
     * lo cumpla) se renumeran según el par derivado de su categoría y su
     * nombre base.
     */
    private function reescribirCodigosLegado(): void
    {
        $filas = DB::table('materials')->orderBy('id')->get();

        $tomados = [];
        $plan = [];

        foreach ($filas as $fila) {
            $codigo = Material::normalizarCodigo($fila->codigo_material);
            $partes = $codigo !== null ? explode('-', $codigo) : [];
            $yaFormado = $codigo !== null && count($partes) === 3 && preg_match(self::PATRON_LEGADO, $codigo);

            // El par se lee del código solo cuando el código ya es válido; si
            // no, MAT-30 terminaría disfrazado de prefijo.
            $categoria = Material::normalizarFragmento($fila->codigo_categoria)
                ?: ($yaFormado ? $partes[0] : '')
                ?: Material::abreviar((string) $fila->categoria);
            $base = Material::normalizarFragmento($fila->codigo_base)
                ?: ($yaFormado ? $partes[1] : '')
                ?: Material::abreviar((string) $fila->nombre_base);

            if ($categoria === '' || $base === '') {
                // Sin par no hay forma de armar el código: se deja el anterior.
                continue;
            }

            $prefijo = $categoria . '-' . $base;
            $numero = $yaFormado ? max(1, (int) $partes[2]) : null;

            if ($numero !== null && isset($tomados[$prefijo][$numero])) {
                $numero = null;
            }
            if ($numero === null) {
                $numero = 1;
                while (isset($tomados[$prefijo][$numero])) {
                    $numero++;
                }
            }

            $tomados[$prefijo][$numero] = true;
            $plan[$fila->id] = [$prefijo . '-' . $numero, $categoria, $base];
        }

        foreach ($plan as $id => [$codigo, $categoria, $base]) {
            DB::table('materials')->where('id', $id)->update([
                'codigo_material'  => $codigo,
                'codigo_categoria' => $categoria,
                'codigo_base'      => $base,
            ]);
        }
    }

    /**
     * El consecutivo se calcula leyendo el máximo usado, así que dos códigos
     * iguales romperían la regla. Solo se bloquea la duplicación cuando el
     * dato actual ya es único.
     */
    private function protegerUnicidad(): void
    {
        if ($this->indiceUnicoExiste()) {
            return;
        }

        $duplicados = DB::table('materials')
            ->select('codigo_material')
            ->groupBy('codigo_material')
            ->havingRaw('count(*) > 1')
            ->pluck('codigo_material');

        if ($duplicados->isNotEmpty()) {
            return;
        }

        Schema::table('materials', function (Blueprint $table) {
            $table->unique('codigo_material');
        });
    }

    private function indiceUnicoExiste(): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'materials')
            ->where('INDEX_NAME', 'materials_codigo_material_unique')
            ->exists();
    }
};
