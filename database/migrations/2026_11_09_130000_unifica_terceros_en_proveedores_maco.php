<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proveedor, taller y empresa externa comparten exactamente la misma ficha y
 * los mismos consumidores (OP, kit, materiales, procesos externos y fichas de
 * ensamble). Se unifican en `proveedores_maco` con una columna `tipo` que
 * distingue las tres entidades.
 *
 * Los ids de las tres tablas se pisan entre sí, así que la copia guarda un
 * mapa id_viejo => id_nuevo y con él se repuntan todas las llaves foráneas
 * antes de borrar las tablas originales.
 */
return new class extends Migration
{
    private const TABLA = 'proveedores_maco';

    /**
     * Columnas que referencian a alguno de los tres terceros.
     *
     * @var array<int, array{tabla: string, columna: string, tipo: string}>
     */
    private const REFERENCIAS = [
        ['tabla' => 'orden_produccions', 'columna' => 'taller_id', 'tipo' => 'Taller'],
        ['tabla' => 'orden_produccions', 'columna' => 'empresa_externa_id', 'tipo' => 'Empresa externa'],
        ['tabla' => 'kit_procesos', 'columna' => 'proveedor_id', 'tipo' => 'Proveedor'],
        ['tabla' => 'materials', 'columna' => 'proveedor_habitual', 'tipo' => 'Proveedor'],
        ['tabla' => 'proceso_externos', 'columna' => 'proveedor_destino_id', 'tipo' => 'Proveedor'],
        ['tabla' => 'ficha_ensambles', 'columna' => 'taller_id', 'tipo' => 'Taller'],
    ];

    public function up(): void
    {
        $this->eliminarLlavesForaneas();

        Schema::create(self::TABLA, function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20)->default('Proveedor')->index();
            $table->string('codigo')->nullable()->index();
            $table->string('nit')->nullable();
            $table->string('nombre')->nullable();
            $table->string('nombre_comercial')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('direccion')->nullable();
            $table->text('observaciones_direccion')->nullable();
            $table->string('correo_electronico')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('telefono_principal')->nullable();
            $table->string('tipo_proveedor')->nullable();
            $table->text('que_suministra')->nullable();
            $table->string('persona_contacto')->nullable();
            $table->string('celular_contacto')->nullable();
            $table->string('forma_pago')->nullable();
            $table->string('tiempo_entrega')->nullable();
            $table->string('estado')->default('Activo');
            $table->text('observaciones_generales')->nullable();

            // Columnas heredadas de la ficha antigua de proveedores.
            $table->string('categoria')->nullable();
            $table->bigInteger('celular1')->nullable();
            $table->bigInteger('celular2')->nullable();

            $table->timestamps();
        });

        $mapas = [
            'Proveedor' => $this->copiar('proveedors', 'codigo_proveedor', 'Proveedor'),
            'Taller' => $this->copiar('tallers', 'codigo_taller', 'Taller'),
            'Empresa externa' => $this->copiar('empresa_externas', 'codigo_empresa_externa', 'Empresa externa'),
        ];

        $this->asignarCodigosFaltantes();

        foreach (self::REFERENCIAS as $referencia) {
            if (!Schema::hasTable($referencia['tabla'])) {
                continue;
            }

            $this->remapear($referencia['tabla'], $referencia['columna'], $mapas[$referencia['tipo']]);
        }

        // Tablas históricas que también guardaban el proveedor destino.
        if (Schema::hasTable('procesos_externos')) {
            $this->remapear('procesos_externos', 'proveedor_destino_id', $mapas['Proveedor']);
        }

        Schema::dropIfExists('empresa_externas');
        Schema::dropIfExists('tallers');
        Schema::dropIfExists('proveedors');

        $this->crearLlavesForaneas();
    }

    public function down(): void
    {
        $this->eliminarLlavesForaneas();

        $this->crearTablasAnteriores();

        $mapas = [
            'Proveedor' => $this->copiarDeVuelta('proveedors', 'codigo_proveedor', 'Proveedor'),
            'Taller' => $this->copiarDeVuelta('tallers', 'codigo_taller', 'Taller'),
            'Empresa externa' => $this->copiarDeVuelta('empresa_externas', 'codigo_empresa_externa', 'Empresa externa'),
        ];

        foreach (self::REFERENCIAS as $referencia) {
            if (!Schema::hasTable($referencia['tabla'])) {
                continue;
            }

            $this->remapear($referencia['tabla'], $referencia['columna'], $mapas[$referencia['tipo']]);
        }

        if (Schema::hasTable('procesos_externos')) {
            $this->remapear('procesos_externos', 'proveedor_destino_id', $mapas['Proveedor']);
        }

        Schema::dropIfExists(self::TABLA);

        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->foreign('taller_id')->references('id')->on('tallers');
            $table->foreign('empresa_externa_id')->references('id')->on('empresa_externas')->nullOnDelete();
        });

        Schema::table('kit_procesos', function (Blueprint $table) {
            $table->foreign('proveedor_id')->references('id')->on('proveedors')->nullOnDelete();
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreign('proveedor_habitual')->references('id')->on('proveedors')->nullOnDelete();
        });

        Schema::table('proceso_externos', function (Blueprint $table) {
            $table->foreign('proveedor_destino_id')->references('id')->on('proveedors');
        });

        Schema::table('ficha_ensambles', function (Blueprint $table) {
            $table->foreign('taller_id')->references('id')->on('tallers')->nullOnDelete();
        });
    }

    private function eliminarLlavesForaneas(): void
    {
        $llaves = [
            'orden_produccions' => ['taller_id', 'empresa_externa_id'],
            'kit_procesos' => ['proveedor_id'],
            'materials' => ['proveedor_habitual'],
            'proceso_externos' => ['proveedor_destino_id'],
            'ficha_ensambles' => ['taller_id'],
        ];

        foreach ($llaves as $tabla => $columnas) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $blueprint) use ($columnas) {
                foreach ($columnas as $columna) {
                    try {
                        $blueprint->dropForeign([$columna]);
                    } catch (\Throwable) {
                        // La llave pudo haberse creado con otro nombre; la copia
                        // de datos no depende de ella.
                    }
                }
            });
        }
    }

    private function crearLlavesForaneas(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->foreign('taller_id')->references('id')->on(self::TABLA);
            $table->foreign('empresa_externa_id')->references('id')->on(self::TABLA)->nullOnDelete();
        });

        Schema::table('kit_procesos', function (Blueprint $table) {
            $table->foreign('proveedor_id')->references('id')->on(self::TABLA)->nullOnDelete();
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreign('proveedor_habitual')->references('id')->on(self::TABLA)->nullOnDelete();
        });

        Schema::table('proceso_externos', function (Blueprint $table) {
            $table->foreign('proveedor_destino_id')->references('id')->on(self::TABLA);
        });

        Schema::table('ficha_ensambles', function (Blueprint $table) {
            $table->foreign('taller_id')->references('id')->on(self::TABLA)->nullOnDelete();
        });
    }

    /**
     * Los registros históricos creados a mano no tienen código. Se les asigna
     * el siguiente disponible de su tipo para que todos aparezcan con código
     * en los listados.
     */
    private function asignarCodigosFaltantes(): void
    {
        $prefijos = [
            'Proveedor' => 'PRV',
            'Taller' => 'TAL',
            'Empresa externa' => 'EMP',
        ];

        foreach ($prefijos as $tipo => $prefijo) {
            $maximo = 0;

            foreach (DB::table(self::TABLA)->where('tipo', $tipo)->pluck('codigo') as $codigo) {
                $numero = (int) preg_replace('/\D+/', '', (string) $codigo);
                $maximo = max($maximo, $numero);
            }

            $faltantes = DB::table(self::TABLA)
                ->where('tipo', $tipo)
                ->where(fn ($query) => $query->whereNull('codigo')->orWhere('codigo', ''))
                ->orderBy('id')
                ->pluck('id');

            foreach ($faltantes as $id) {
                DB::table(self::TABLA)->where('id', $id)->update([
                    'codigo' => $prefijo . '-' . str_pad((string) ++$maximo, 3, '0', STR_PAD_LEFT),
                ]);
            }
        }
    }

    /**
     * Copia una tabla histórica a la unificada y devuelve id_viejo => id_nuevo.
     *
     * @return array<int, int>
     */
    private function copiar(string $tabla, string $columnaCodigo, string $tipo): array
    {
        if (!Schema::hasTable($tabla)) {
            return [];
        }

        $mapa = [];

        DB::table($tabla)->orderBy('id')->chunkById(500, function ($filas) use (&$mapa, $columnaCodigo, $tipo) {
            foreach ($filas as $fila) {
                $datos = (array) $fila;
                $idViejo = (int) $datos['id'];
                unset($datos['id']);

                $datos['tipo'] = $tipo;
                $datos['codigo'] = $datos[$columnaCodigo] ?? null;
                unset($datos[$columnaCodigo]);

                $mapa[$idViejo] = (int) DB::table(self::TABLA)->insertGetId($datos);
            }
        });

        return $mapa;
    }

    /**
     * Copia la tabla unificada a una histórica y devuelve id_nuevo => id_viejo.
     *
     * @return array<int, int>
     */
    private function copiarDeVuelta(string $tabla, string $columnaCodigo, string $tipo): array
    {
        $mapa = [];
        $columnas = Schema::getColumnListing($tabla);

        DB::table(self::TABLA)->where('tipo', $tipo)->orderBy('id')->chunkById(500, function ($filas) use (&$mapa, $columnaCodigo, $columnas) {
            foreach ($filas as $fila) {
                $datos = (array) $fila;
                $idNuevo = (int) $datos['id'];
                unset($datos['id'], $datos['tipo']);

                $datos[$columnaCodigo] = $datos['codigo'] ?? null;
                unset($datos['codigo']);

                $datos = array_intersect_key($datos, array_flip($columnas));

                $mapa[$idNuevo] = (int) DB::table($tabla)->insertGetId($datos);
            }
        });

        return $mapa;
    }

    /**
     * @param  array<int, int>  $mapa
     */
    private function remapear(string $tabla, string $columna, array $mapa): void
    {
        if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, $columna)) {
            return;
        }

        DB::table($tabla)
            ->whereNotNull($columna)
            ->orderBy('id')
            ->chunkById(500, function ($filas) use ($tabla, $columna, $mapa) {
                foreach ($filas as $fila) {
                    $valorNuevo = $mapa[(int) $fila->{$columna}] ?? null;

                    DB::table($tabla)
                        ->where('id', $fila->id)
                        ->update([$columna => $valorNuevo]);
                }
            });
    }

    private function crearTablasAnteriores(): void
    {
        Schema::create('proveedors', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_proveedor')->nullable();
            $table->string('nit')->nullable();
            $table->string('nombre_comercial')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('direccion')->nullable();
            $table->text('observaciones_direccion')->nullable();
            $table->string('correo_electronico')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('telefono_principal')->nullable();
            $table->string('tipo_proveedor')->nullable();
            $table->text('que_suministra')->nullable();
            $table->string('persona_contacto')->nullable();
            $table->string('celular_contacto')->nullable();
            $table->string('forma_pago')->nullable();
            $table->string('tiempo_entrega')->nullable();
            $table->string('estado')->default('Activo');
            $table->text('observaciones_generales')->nullable();
            $table->string('nombre')->nullable();
            $table->string('categoria')->nullable();
            $table->bigInteger('celular1')->nullable();
            $table->bigInteger('celular2')->nullable();
            $table->timestamps();
        });

        Schema::create('tallers', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_taller')->nullable();
            $table->string('nit')->nullable();
            $table->string('nombre_comercial')->nullable();
            $table->string('nombre')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('direccion')->nullable();
            $table->text('observaciones_direccion')->nullable();
            $table->string('correo_electronico')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('telefono_principal')->nullable();
            $table->string('tipo_proveedor')->nullable();
            $table->text('que_suministra')->nullable();
            $table->string('persona_contacto')->nullable();
            $table->string('celular_contacto')->nullable();
            $table->string('forma_pago')->nullable();
            $table->string('tiempo_entrega')->nullable();
            $table->string('estado')->default('Activo');
            $table->text('observaciones_generales')->nullable();
            $table->timestamps();
        });

        Schema::create('empresa_externas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_empresa_externa')->nullable();
            $table->string('nit')->nullable();
            $table->string('nombre_comercial')->nullable();
            $table->string('nombre')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('direccion')->nullable();
            $table->text('observaciones_direccion')->nullable();
            $table->string('correo_electronico')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('telefono_principal')->nullable();
            $table->string('tipo_proveedor')->nullable();
            $table->text('que_suministra')->nullable();
            $table->string('persona_contacto')->nullable();
            $table->string('celular_contacto')->nullable();
            $table->string('forma_pago')->nullable();
            $table->string('tiempo_entrega')->nullable();
            $table->string('estado')->default('Activo');
            $table->text('observaciones_generales')->nullable();
            $table->timestamps();
        });
    }
};
