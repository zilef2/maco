<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;
    use HasFillableWithTypes;

    /** Letras que valen en cada fragmento del código (categoría y base). */
    public const LONGITUD_FRAGMENTO = 3;

    /** Formato del código: prefijo CAT-BASE y consecutivo sin ceros. */
    public const PATRON_CODIGO = '/^[A-Z]{1,' . self::LONGITUD_FRAGMENTO . '}-[A-Z]{1,' . self::LONGITUD_FRAGMENTO . '}-\d{1,6}$/';

    protected $fillable = ['codigo_material',
        'codigo_categoria',
        'categoria',
        'codigo_base',
        'generico',
        'nombre_base',
        'unidad_inventario',
        'existencia_actual',
        'gasto_diario',
        'maneja_color',
        'maneja_talla',
        'maneja_otra_variante',
        'estado',
        'modelo_diseno_acabado',
        'color',
        'talla_tamano',
        'variante_atributo_sugerido',
        'proveedor_habitual',
        'material_procesado',
        'observaciones'];

    protected static function booted(): void
    {
        static::saving(function (Material $material): void {
            $material->aplicarReglaDelCodigo();
            if ($material->generico) {
                $material->limpiarVariantesPorGenerico();
            }
        });
    }

    /**
     * El genérico es un referente de familia, no un artículo almacenado: no
     * maneja talla ni color (se limpian los indicadores y las variantes) y
     * tampoco tiene inventario propio (existencia y gasto diario viven en los
     * materiales concretos del mismo par). Se aplica en saving para que valga
     * en cualquier ruta de escritura (formulario, servicio, tinker).
     */
    protected function limpiarVariantesPorGenerico(): void
    {
        $this->forceFill([
            'maneja_color' => false,
            'maneja_talla' => false,
            'maneja_otra_variante' => false,
            'color' => null,
            'talla_tamano' => null,
            'existencia_actual' => null,
            'gasto_diario' => null,
        ]);

        if ($this->exists) {
            $this->variants()->delete();
        }
    }

    /**
     * El código del material se forma con el par categoría/base y el
     * consecutivo libre más alto de ese par: TEX-LFI-4. El par se deriva del
     * nombre cuando viene vacío, así que el código nunca queda en el aire.
     *
     * Cambiar el par mueve el material al siguiente número libre del prefijo
     * nuevo; el número que deja queda disponible para otro material.
     *
     * El genérico es el único caso sin consecutivo: como debe haber uno solo
     * por par, siempre lleva el 0 (COM-CAP-0) y el 1+ queda para los específicos.
     */
    public function aplicarReglaDelCodigo(): void
    {
        $this->codigo_categoria = self::normalizarFragmento($this->codigo_categoria)
            ?: self::abreviar((string) $this->categoria);
        $this->codigo_base = self::normalizarFragmento($this->codigo_base)
            ?: self::abreviar((string) $this->nombre_base);

        if ($this->codigo_categoria === '' || $this->codigo_base === '') {
            throw ValidationException::withMessages([
                'codigo_categoria' => 'No se puede armar el código del material: digite el código de la categoría y el código base, o escriba su categoría y su nombre base.',
            ]);
        }

        $prefijo = $this->codigo_categoria . '-' . $this->codigo_base;

        if ($this->generico) {
            $this->codigo_material = $prefijo . '-0';
            $this->descartarGenericoDuplicado($prefijo);

            return;
        }

        $codigo = self::normalizarCodigo($this->codigo_material);

        $this->codigo_material = $codigo !== null && str_starts_with($codigo, $prefijo . '-')
            ? $codigo
            : $prefijo . '-' . self::siguienteConsecutivo($prefijo);
    }

    /**
     * El 0 del prefijo identifica al genérico del par, así que dos genéricos del
     * mismo par serían el mismo material: se rechaza el segundo.
     */
    private function descartarGenericoDuplicado(string $prefijo): void
    {
        $existente = static::query()
            ->where('codigo_material', $prefijo . '-0')
            ->when($this->exists, fn ($consulta) => $consulta->where('id', '<>', $this->getKey()))
            ->first();

        if (!$existente) {
            return;
        }

        throw ValidationException::withMessages([
            'codigo_material' => "El par {$prefijo} ya tiene su genérico: {$existente->codigo_material} ({$existente->nombre_base}). Debe haber uno solo por categoría y nombre base.",
        ]);
    }

    /**
     * Deja el fragmento del código en su forma canónica: mayúsculas, sin tildes
     * y con máximo 3 letras.
     */
    public static function normalizarFragmento(mixed $fragmento): string
    {
        $soloLetras = (string) preg_replace('/[^A-Z]/', '', mb_strtoupper(Str::ascii(trim((string) $fragmento))));

        return substr($soloLetras, 0, self::LONGITUD_FRAGMENTO);
    }

    /**
     * Forma canónica del código: mayúsculas, guiones y el consecutivo sin ceros
     * a la izquierda (TEX-LFL-005 pasa a TEX-LFL-5).
     */
    public static function normalizarCodigo(mixed $codigo): ?string
    {
        $limpio = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', Str::ascii(trim((string) $codigo))), '-');

        if ($limpio === '') {
            return null;
        }

        return (string) preg_replace_callback(
            '/\d+/',
            fn (array $digitos): string => (string) (int) $digitos[0],
            mb_strtoupper($limpio)
        );
    }

    /**
     * Abreviatura de hasta 3 letras para los códigos que no se digitan:
     * "Textil" es TEX y "Lana fibrada" es LFA (las iniciales y, si faltan
     * letras, las siguientes de cada palabra).
     */
    public static function abreviar(?string $nombre): string
    {
        preg_match_all('/[A-Za-z]+/', Str::ascii((string) $nombre), $palabras);
        $palabras = array_map(fn (string $palabra): string => mb_strtoupper($palabra), $palabras[0]);

        if (empty($palabras)) {
            return '';
        }

        if (count($palabras) === 1) {
            return substr($palabras[0], 0, self::LONGITUD_FRAGMENTO);
        }

        $abreviatura = '';
        for ($letra = 0; strlen($abreviatura) < self::LONGITUD_FRAGMENTO; $letra++) {
            $agrego = false;
            foreach ($palabras as $palabra) {
                if (isset($palabra[$letra]) && strlen($abreviatura) < self::LONGITUD_FRAGMENTO) {
                    $abreviatura .= $palabra[$letra];
                    $agrego = true;
                }
            }
            if (!$agrego) {
                break;
            }
        }

        return substr($abreviatura, 0, self::LONGITUD_FRAGMENTO);
    }

    /** Consecutivo libre del prefijo: el número más alto ya usado, más uno. */
    public static function siguienteConsecutivo(string $prefijo): int
    {
        $maximo = static::query()
            ->where('codigo_material', 'like', $prefijo . '-%')
            ->pluck('codigo_material')
            ->map(fn (string $codigo): int => (int) substr($codigo, strlen($prefijo) + 1))
            ->max();

        return (int) $maximo + 1;
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MaterialMovimiento::class, 'material_id');
    }

    /**
     * Presentaciones del material: colores u otras variantes (por ejemplo
     * tallas). Se ordenan tal como se registraron.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(MaterialVariant::class, 'material_id')
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function variantesColor(): HasMany
    {
        return $this->variants()->where('tipo', MaterialVariant::TIPO_COLOR);
    }

    public function variantesOtras(): HasMany
    {
        return $this->variants()->where('tipo', MaterialVariant::TIPO_VARIANTE);
    }

    /**
     * Normaliza una lista de variantes recibida como array o como texto
     * separado por comas o punto y coma. Elimina vacíos y duplicados.
     *
     * @return list<string>
     */
    public static function normalizarListaVariantes(array|string|null $valores): array
    {
        if (is_string($valores)) {
            $valores = preg_split('/[,;]+/', $valores) ?: [];
        }

        if (!is_array($valores)) {
            return [];
        }

        $limpios = [];
        $vistos = [];

        foreach ($valores as $valor) {
            if (!is_scalar($valor)) {
                continue;
            }

            $nombre = trim(strip_tags((string) $valor));
            if ($nombre === '') {
                continue;
            }

            $nombre = mb_substr($nombre, 0, 255);
            $clave = mb_strtolower($nombre);
            if (isset($vistos[$clave])) {
                continue;
            }

            $vistos[$clave] = true;
            $limpios[] = $nombre;
        }

        return $limpios;
    }

    /**
     * Reemplaza las variantes del material conservando el id de las que ya
     * existen (para no romper futuras referencias a la variante).
     *
     * Un material puede llevar colores y otro tipo de variante a la vez; cada
     * lista conserva su propio orden dentro de su tipo.
     *
     * @param  array<int, string>  $colores
     * @param  array<int, string>  $variantes
     */
    public function sincronizarVariantes(array $colores = [], array $variantes = []): void
    {
        // El genérico nunca lleva variantes: se limpian y no se vuelve a
        // escribir (el saving ya hizo la limpieza al guardar).
        if ($this->generico) {
            $this->variants()->delete();
            return;
        }

        $colores = self::normalizarListaVariantes($colores);
        $variantes = self::normalizarListaVariantes($variantes);

        $deseadas = [];

        foreach ($colores as $indice => $nombre) {
            $deseadas[] = [
                'nombre' => $nombre,
                'tipo' => MaterialVariant::TIPO_COLOR,
                'orden' => $indice,
            ];
        }

        foreach ($variantes as $indice => $nombre) {
            $deseadas[] = [
                'nombre' => $nombre,
                'tipo' => MaterialVariant::TIPO_VARIANTE,
                'orden' => $indice,
            ];
        }

        $ids = [];

        foreach ($deseadas as $deseada) {
            $variant = $this->variants()->updateOrCreate(
                [
                    'tipo' => $deseada['tipo'],
                    'nombre' => $deseada['nombre'],
                ],
                ['orden' => $deseada['orden']]
            );

            $ids[] = $variant->id;
        }

        $this->variants()->whereNotIn('id', $ids)->delete();

        // Los indicadores del material se derivan de las listas para que la
        // tabla y los filtros no queden desincronizados.
        $this->forceFill([
            'maneja_color' => !empty($colores),
            'maneja_otra_variante' => !empty($variantes),
            'color' => !empty($colores) ? implode(', ', $colores) : null,
            'talla_tamano' => !empty($variantes) ? implode(', ', $variantes) : null,
        ])->save();
    }

    public function getVarianteAtributoSugeridoAttribute(): ?string
    {
        return $this->attributes['modelo_diseno_acabado'] ?? null;
    }

    public function setVarianteAtributoSugeridoAttribute(?string $value): void
    {
        $this->attributes['modelo_diseno_acabado'] = $value;
    }

    public function proveedorHabitual()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_habitual');
    }

    /**
     * Ficha técnica con la que se fabrica este material procesado. Un material
     * solo puede pertenecer a una ficha.
     */
    public function fichaTecnica(): BelongsToMany
    {
        return $this->belongsToMany(FichaTecnica::class, 'ficha_tecnica_material', 'material_id', 'ficha_tecnica_id')->withTimestamps();
    }

    /** OPs que producen este material procesado. */
    public function ordenesProduccion(): BelongsToMany
    {
        return $this->belongsToMany(OrdenProduccion::class, 'orden_produccion_material', 'material_id', 'orden_produccion_id')
            ->withPivot('cantidad')
            ->withTimestamps();
    }

    public function prices()
    {
        return $this->hasMany(MaterialPrice::class, 'material_id')
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    public function lastPrice()
    {
        return $this->hasOne(MaterialPrice::class, 'material_id')
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    /**
     * Costo unitario vigente: el precio de la compra más reciente.
     * Es la fuente que usa la ficha técnica al elegir este material.
     */
    public function costoUnitarioActual(): float
    {
        $precio = $this->relationLoaded('lastPrice')
            ? $this->getRelation('lastPrice')
            : $this->lastPrice()->first();

        return round((float) ($precio?->precio ?? 0), 2);
    }

    /**
     * Costo unitario promedio: el promedio de todos los precios registrados
     * del material (cada cambio de precio es una fila en material_prices).
     */
    public function costoUnitarioPromedio(): float
    {
        if ($this->relationLoaded('prices')) {
            return round((float) $this->getRelation('prices')->avg('precio'), 2);
        }

        return round((float) $this->prices()->avg('precio'), 2);
    }

    protected static array $booleanFields = [
        'generico',
        'maneja_color',
        'maneja_talla',
        'maneja_otra_variante',
        'material_procesado',
    ];

    /**
     * Recalcula y guarda el gasto_diario de este material.
     *
     * El gasto diario = total de salidas a taller de las OPs en los últimos
     * $ventanaDias días ÷ $ventanaDias (misma lógica que la previsión de existencias).
     * Devuelve el valor calculado o null si no hay consumo en la ventana.
     * Un genérico siempre devuelve null: su salida nunca existirá, el consumo es
     * del material concreto que se eligió en la OP.
     */
    public function recalcularGastoDiario(int $ventanaDias = 30): ?float
    {
        if ($this->generico) {
            static::whereKey($this->getKey())->toBase()->update([
                'gasto_diario'                => null,
                'gasto_diario_actualizado_en' => now(),
            ]);

            $this->gasto_diario = null;
            $this->gasto_diario_actualizado_en = now();

            return null;
        }

        $total = MaterialMovimiento::query()
            ->where('material_id', $this->id)
            ->where('tipo_movimiento', 'salida')
            ->where('motivo', 'like', 'Salida de materiales a taller%')
            ->where('created_at', '>=', now()->subDays($ventanaDias))
            ->sum('cantidad');

        $total = round((float) $total, 2);
        if ($total <= 0) {
            // Sin consumo en la ventana: se limpia el valor anterior para que
            // no quede un gasto diario desactualizado.
            static::whereKey($this->getKey())->toBase()->update([
                'gasto_diario'                => null,
                'gasto_diario_actualizado_en' => now(),
            ]);

            $this->gasto_diario = null;
            $this->gasto_diario_actualizado_en = now();

            return null;
        }

        $gastoDiario = round($total / $ventanaDias, 2);
        $ahora = now();

        // Se escribe directo para no tocar updated_at (el listado ordena por
        // esa columna y un recálculo de sistema no debería reordenarlo).
        static::whereKey($this->getKey())->toBase()->update([
            'gasto_diario'                => $gastoDiario,
            'gasto_diario_actualizado_en' => $ahora,
        ]);

        $this->gasto_diario = $gastoDiario;
        $this->gasto_diario_actualizado_en = $ahora;

        return $gastoDiario;
    }

    /**
     * Recalcula el gasto_diario de todos los materiales activos. Los genéricos
     * se limpian una sola vez: su consumo pertenece a los materiales concretos.
     * Devuelve ['actualizados' => int, 'sin_consumo' => int].
     */
    public static function recalcularGastoDiarioDeTodos(int $ventanaDias = 30): array
    {
        static::query()
            ->where('generico', true)
            ->whereNotNull('gasto_diario')
            ->toBase()
            ->update(['gasto_diario' => null]);

        $actualizados = 0;
        $sinConsumo = 0;

        foreach (self::where('estado', 'Activo')->where('generico', false)->cursor() as $material) {
            if ($material->recalcularGastoDiario($ventanaDias) !== null) {
                $actualizados++;
            } else {
                $sinConsumo++;
            }
        }

        return compact('actualizados', 'sinConsumo');
    }
}
