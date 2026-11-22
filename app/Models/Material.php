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

    /** Dígitos con los que se escribe el número de la familia: COM-CAP-055. */
    public const LARGO_FAMILIA = 3;

    /** Dígitos con los que se escribe el número del hijo: COM-CAP-055-34. */
    public const LARGO_HIJO = 2;

    /** Código de un genérico: el par y el número de familia, sin hijo. */
    public const PATRON_FAMILIA = '/^[A-Z]{1,' . self::LONGITUD_FRAGMENTO . '}-[A-Z]{1,' . self::LONGITUD_FRAGMENTO . '}-\d{1,4}$/';

    /** Código de un material concreto: el código de su genérico más el hijo. */
    public const PATRON_HIJO = '/^[A-Z]{1,' . self::LONGITUD_FRAGMENTO . '}-[A-Z]{1,' . self::LONGITUD_FRAGMENTO . '}-\d{1,4}-\d{1,4}$/';

    /** Formato del código: familia (CAT-BASE-055) o hijo de esa familia (CAT-BASE-055-34). */
    public const PATRON_CODIGO = '/^[A-Z]{1,' . self::LONGITUD_FRAGMENTO . '}-[A-Z]{1,' . self::LONGITUD_FRAGMENTO . '}-\d{1,4}(-\d{1,4})?$/';

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
     * El código dice de qué familia es el material. El genérico es la familia y
     * se codifica con el par y su número de tres dígitos (COM-CAP-055); sus
     * hijos —los que tienen color o talla y los que se gastan— le agregan el
     * número del hijo (COM-CAP-055-34).
     *
     * Un mismo par admite varios genéricos (COM-CAP-055 y COM-CAP-056), así que
     * lo único prohibido es repetir un código: cada familia tiene su genérico.
     *
     * El código que llega escrito se respeta cuando pertenece al par, porque el
     * archivo MPP es quien los trae. Cuando viene vacío, el genérico toma el
     * siguiente número de familia libre del par y el concreto se registra bajo
     * la familia más alta que el par ya tenga (si el par no tiene genérico, hay
     * que crearlo primero: sin familia no hay código de hijo posible).
     *
     * Cambiar el par mueve el material a la familia nueva; el código que deja
     * queda disponible para otro material.
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
        $codigo = self::normalizarCodigo($this->codigo_material);
        $delPar = $codigo !== null && str_starts_with($codigo, $prefijo . '-');

        if ($this->generico) {
            // Un genérico es una familia: si llegó código de hijo se descarta y
            // se le asigna el siguiente número de familia libre del par.
            $this->codigo_material = $delPar && self::esCodigoFamilia($codigo)
                ? $codigo
                : $prefijo . '-' . self::siguienteNumeroFamilia($prefijo);

            $this->rechazarCodigoTomado();

            return;
        }

        if ($delPar) {
            // El hijo se reescribe con sus ceros (COM-CAP-55-4 es COM-CAP-055-04);
            // un código de tres tramos es del formato antiguo y queda tal cual.
            $this->codigo_material = self::esCodigoHijo($codigo)
                ? $codigo
                : trim((string) $this->codigo_material);

            $this->rechazarCodigoTomado();

            return;
        }

        $familia = self::familiaMasAltaDelPar($prefijo);

        if ($familia === null) {
            throw ValidationException::withMessages([
                'codigo_material' => "El par {$prefijo} no tiene material genérico: primero registre su familia ({$prefijo}-" . self::siguienteNumeroFamilia($prefijo) . ") y el concreto quedará como hijo de ella.",
            ]);
        }

        $this->codigo_material = $familia . '-' . self::siguienteNumeroHijo($familia);

        $this->rechazarCodigoTomado();
    }

    /**
     * El código identifica a un material: no puede estar usado por otro, ni el
     * de una familia puede quedar como código de un concreto. Se revisa aquí
     * para que el aviso sea legible y no el error de la clave única de la tabla.
     */
    private function rechazarCodigoTomado(): void
    {
        if ($this->codigo_material === $this->getOriginal('codigo_material')) {
            return;
        }

        $existente = static::query()
            ->where('codigo_material', $this->codigo_material)
            ->when($this->exists, fn ($consulta) => $consulta->where('id', '<>', $this->getKey()))
            ->first();

        if (!$existente) {
            return;
        }

        throw ValidationException::withMessages([
            'codigo_material' => $existente->generico
                ? "El código {$this->codigo_material} es de un material genérico: los concretos de esa familia se escriben {$this->codigo_material}-34."
                : "El código {$this->codigo_material} ya lo tiene el material {$existente->nombre_base}.",
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
     * Forma canónica del código: mayúsculas, guiones y los números con su
     * relleno (COM-CAP-55-4 pasa a COM-CAP-055-04). Lo que no tiene formato de
     * código de material devuelve tal cual, para que el importador lo reporte.
     */
    public static function normalizarCodigo(mixed $codigo): ?string
    {
        $limpio = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', Str::ascii(trim((string) $codigo))), '-');

        if ($limpio === '') {
            return null;
        }

        $limpio = mb_strtoupper($limpio);

        if (preg_match('/^([A-Z]{1,' . self::LONGITUD_FRAGMENTO . '}-[A-Z]{1,' . self::LONGITUD_FRAGMENTO . '})-(\d{1,4})(?:-(\d{1,4}))?$/', $limpio, $coincidencia) !== 1) {
            return $limpio;
        }

        return $coincidencia[1] . '-' . str_pad($coincidencia[2], self::LARGO_FAMILIA, '0', STR_PAD_LEFT)
            . (isset($coincidencia[3]) && $coincidencia[3] !== ''
                ? '-' . str_pad($coincidencia[3], self::LARGO_HIJO, '0', STR_PAD_LEFT)
                : '');
    }

    /** El código es de una familia (COM-CAP-055): tres tramos y nada de hijo. */
    public static function esCodigoFamilia(?string $codigo): bool
    {
        return $codigo !== null && $codigo !== '' && preg_match(self::PATRON_FAMILIA, $codigo) === 1;
    }

    /** El código es de un hijo de familia (COM-CAP-055-34). */
    public static function esCodigoHijo(?string $codigo): bool
    {
        return $codigo !== null && $codigo !== '' && preg_match(self::PATRON_HIJO, $codigo) === 1;
    }

    /**
     * Familia a la que pertenece un código: la del material genérico. En un
     * hijo es el código sin el último tramo (COM-CAP-055-34 => COM-CAP-055); en
     * una familia es ella misma. Null si la celda no tiene formato de código.
     */
    public static function familiaDeCodigo(?string $codigo): ?string
    {
        $normalizado = self::normalizarCodigo($codigo);

        if ($normalizado === null || preg_match(self::PATRON_CODIGO, $normalizado) !== 1) {
            return null;
        }

        return self::esCodigoHijo($normalizado)
            ? substr($normalizado, 0, (int) strrpos($normalizado, '-'))
            : $normalizado;
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

    /**
     * Número libre para un genérico nuevo del par, ya con sus 3 dígitos:
     * el número de familia más alto usado, más uno.
     */
    public static function siguienteNumeroFamilia(string $prefijo): string
    {
        $maximo = static::query()
            ->where('codigo_material', 'like', $prefijo . '-%')
            ->pluck('codigo_material')
            ->map(fn (string $codigo): ?string => self::normalizarCodigo($codigo))
            ->filter(fn (?string $codigo): bool => self::esCodigoFamilia((string) $codigo))
            ->map(fn (string $codigo): int => (int) substr($codigo, strlen($prefijo) + 1))
            ->max();

        return str_pad((string) ((int) $maximo + 1), self::LARGO_FAMILIA, '0', STR_PAD_LEFT);
    }

    /** Número libre para un hijo nuevo de la familia, con sus 2 dígitos. */
    public static function siguienteNumeroHijo(string $familia): string
    {
        $maximo = static::query()
            ->where('codigo_material', 'like', $familia . '-%')
            ->pluck('codigo_material')
            ->map(fn (string $codigo): ?string => self::normalizarCodigo($codigo))
            ->filter(fn (?string $codigo): bool => self::esCodigoHijo((string) $codigo))
            ->map(fn (string $codigo): int => (int) substr($codigo, strlen($familia) + 1))
            ->max();

        return str_pad((string) ((int) $maximo + 1), self::LARGO_HIJO, '0', STR_PAD_LEFT);
    }

    /**
     * Familia más alta del par: la del genérico con el número de familia más
     * grande, que es a la que se agrega un concreto registrado a mano. Null
     * cuando el par todavía no tiene ningún genérico.
     */
    public static function familiaMasAltaDelPar(string $prefijo): ?string
    {
        return static::query()
            ->where('generico', true)
            ->where('codigo_material', 'like', $prefijo . '-%')
            ->pluck('codigo_material')
            ->map(fn (string $codigo): ?string => self::normalizarCodigo($codigo))
            ->filter(fn (?string $codigo): bool => self::esCodigoFamilia((string) $codigo))
            ->sort()
            ->last();
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
