<?php

namespace App\Models;

use App\Events\MaterialesCambiados;
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

    /** Dígitos con los que se escribe el primer número del código: COM-CAP-055. */
    public const LARGO_NUMERO = 3;

    /** Dígitos del consecutivo que sigue: COM-CAP-055-04. */
    public const LARGO_CONSECUTIVO = 2;

    /** Los dos fragmentos de letras del código (el par): COM-CAP. */
    public const PATRON_FRAGMENTOS = '/^[A-Z]{1,' . self::LONGITUD_FRAGMENTO . '}-[A-Z]{1,' . self::LONGITUD_FRAGMENTO . '}$/';

    protected $fillable = ['codigo_material',
        'codigo_categoria',
        'categoria',
        'codigo_base',
        'generico',
        'nombre_base',
        'nombre_material',
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

        // El Index de Materiales vive en caché, y la caché se corta sola con el
        // listener de este evento (invalida la versión y programa la recarga en
        // la cola, una vez por tanda). Se dispara desde el modelo para que ninguna
        // ruta de escritura —formulario, Excel, ficha, tinker— tenga que
        // acordarse de avisar.
        static::created(function (Material $material): void {
            event(new MaterialesCambiados('creado', $material->id));
        });
        static::updated(function (Material $material): void {
            event(new MaterialesCambiados('actualizado', $material->id));
        });
        static::deleted(function (Material $material): void {
            event(new MaterialesCambiados('eliminado', $material->id));
        });
    }

    /**
     * El material base (la columna "Base si o no" del archivo) es un referente de
     * familia, no un artículo almacenado: no maneja talla ni color (se limpian los
     * indicadores y las variantes) y tampoco tiene inventario propio (existencia y
     * gasto diario viven en los materiales concretos de su familia). Se aplica en
     * saving para que valga en cualquier ruta de escritura (formulario, servicio,
     * tinker).
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
     * El código identifica al material y nada más: su forma no dice si el material
     * es base, hijo u huérfano. Lo que hace esta regla es dejar los fragmentos del
     * par en su forma canónica, rellenar los ceros del código que llega escrito y,
     * cuando viene vacío, dar el número libre siguiente del par.
     *
     * El vínculo con el material base sigue siendo el prefijo: COM-CAP-055-04 es
     * hijo de COM-CAP-055, pero solo lo es si COM-CAP-055 existe y está marcado
     * como base. Si no, el material está huérfano y se maneja solo.
     */
    public function aplicarReglaDelCodigo(): void
    {
        $this->codigo_categoria = self::normalizarFragmento($this->codigo_categoria)
            ?: self::abreviar((string) $this->categoria);
        $this->codigo_base = self::normalizarFragmento($this->codigo_base)
            ?: self::abreviar((string) $this->nombre_base);

        $codigo = self::normalizarCodigo($this->codigo_material);

        if ($codigo === null) {
            if ($this->codigo_categoria === '' || $this->codigo_base === '') {
                throw ValidationException::withMessages([
                    'codigo_categoria' => 'No se puede armar el código del material: digite el código de la categoría y el código base, o escriba su categoría y su nombre base.',
                ]);
            }

            $codigo = self::siguienteCodigoDelPar($this->codigo_categoria . '-' . $this->codigo_base);
        }

        $this->codigo_material = $codigo;

        $this->rechazarNumeroEnCero($codigo);
        $this->rechazarCodigoTomado();
    }

    /**
     * Los números del código arrancan en 1: COM-CAP-000 y COM-CAP-055-00 no
     * existen, así que se rechaza la escritura en vez de guardar un código que
     * luego no se puede volver a formar.
     */
    private function rechazarNumeroEnCero(string $codigo): void
    {
        foreach (explode('-', $codigo) as $indice => $tramo) {
            if ($indice < 2 || !ctype_digit($tramo) || (int) $tramo !== 0) {
                continue;
            }

            throw ValidationException::withMessages([
                'codigo_material' => 'Ningún número del código puede valer 0: ' . $codigo
                    . '. Los números arrancan en 1 (COM-CAP-001, COM-CAP-055-01).',
            ]);
        }
    }

    /**
     * El código identifica a un material: no puede estar usado por otro. Se revisa
     * aquí para que el aviso sea legible y no el error de la clave única de la
     * tabla.
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
            'codigo_material' => 'El código ' . $this->codigo_material . ' ya lo tiene el material '
                . ($existente->nombre_base ?: ('#' . $existente->id)) . '.',
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

        $tramos = explode('-', mb_strtoupper($limpio));

        if (count($tramos) < 3 || !ctype_digit($tramos[2]) || !preg_match(self::PATRON_FRAGMENTOS, $tramos[0] . '-' . $tramos[1])) {
            return implode('-', $tramos);
        }

        $tramos[2] = str_pad($tramos[2], self::LARGO_NUMERO, '0', STR_PAD_LEFT);

        // El tramo que sigue al número de la familia se rellena con dos dígitos,
        // que es como la app escribió siempre los consecutivos (COM-CAP-055-04).
        if (count($tramos) >= 4 && ctype_digit($tramos[3])) {
            $tramos[3] = str_pad($tramos[3], self::LARGO_CONSECUTIVO, '0', STR_PAD_LEFT);
        }

        return implode('-', $tramos);
    }

    /**
     * Familia a la que pertenece un código: la del material base. En un hijo es el
     * código sin el último tramo (COM-CAP-055-34 es COM-CAP-055) y en un material
     * de un solo número es él mismo. Null si la celda no tiene forma de código.
     *
     * La familia es solo el vínculo: si el registro de la familia no está marcado
     * como base, el material está huérfano ({@see basesDeHijos}).
     */
    public static function familiaDeCodigo(?string $codigo): ?string
    {
        $normalizado = self::normalizarCodigo($codigo);

        if ($normalizado === null) {
            return null;
        }

        $tramos = explode('-', $normalizado);

        if (count($tramos) < 3 || !ctype_digit($tramos[count($tramos) - 1])) {
            return null;
        }

        return count($tramos) >= 4
            ? implode('-', array_slice($tramos, 0, -1))
            : $normalizado;
    }

    /**
     * Genérico de cada material de la lista que es hijo de uno: id del material
     * => id de su base. Un material es hijo cuando su familia existe y está
     * marcada como base; los huérfanos (sin base arriba) no entran en el mapa.
     *
     * @param  iterable<int, Material>  $materiales
     * @return array<int, int>
     */
    public static function basesDeHijos(iterable $materiales): array
    {
        $familiasPorId = [];

        foreach ($materiales as $material) {
            $familia = self::familiaDeCodigo($material->codigo_material);

            if ($familia !== null && $familia !== self::normalizarCodigo($material->codigo_material)) {
                $familiasPorId[(int) $material->id] = $familia;
            }
        }

        if ($familiasPorId === []) {
            return [];
        }

        $basePorFamilia = static::query()
            ->where('generico', true)
            ->whereIn('codigo_material', array_unique(array_values($familiasPorId)))
            ->pluck('id', 'codigo_material');

        $bases = [];

        foreach ($familiasPorId as $materialId => $familia) {
            if (isset($basePorFamilia[$familia])) {
                $bases[$materialId] = (int) $basePorFamilia[$familia];
            }
        }

        return $bases;
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
     * Código libre del par cuando la fila o el formulario no digitó ninguno: el
     * número final más alto que el par ya tenga, más uno, con sus 3 dígitos.
     * El material sale huérfano; para que sea hijo de una base hay que escribir
     * el código de la base con su consecutivo (COM-CAP-055-04).
     */
    public static function siguienteCodigoDelPar(string $prefijo): string
    {
        $maximo = static::query()
            ->where('codigo_material', 'like', $prefijo . '-%')
            ->pluck('codigo_material')
            ->map(fn (string $codigo): ?string => self::normalizarCodigo($codigo))
            ->map(fn (string $codigo): int => (int) (explode('-', $codigo)[2] ?? 0))
            ->max();

        return $prefijo . '-' . str_pad((string) ((int) $maximo + 1), self::LARGO_NUMERO, '0', STR_PAD_LEFT);
    }

    /**
     * Código libre para un hijo de la base: su código más el consecutivo más alto
     * que ya tenga esa familia (TEL-ALG-001-03 existe → el siguiente es
     * TEL-ALG-001-04). El consecutivo arranca en 1 porque ningún número del
     * código puede valer 0.
     */
    public static function siguienteCodigoHijo(self $base): string
    {
        $prefijo = self::normalizarCodigo($base->codigo_material);
        $largo = count(explode('-', $prefijo));

        $mayor = static::query()
            ->where('codigo_material', 'like', $prefijo . '-%')
            ->pluck('codigo_material')
            ->map(fn (string $codigo): array => explode('-', self::normalizarCodigo($codigo)))
            ->filter(fn (array $tramos): bool => count($tramos) > $largo && ctype_digit($tramos[count($tramos) - 1]))
            ->map(fn (array $tramos): int => (int) $tramos[count($tramos) - 1])
            ->max();

        return $prefijo . '-' . str_pad((string) ((int) $mayor + 1), self::LARGO_CONSECUTIVO, '0', STR_PAD_LEFT);
    }

    /**
     * La base marcada que tiene arriba este material, o null si está huérfano.
     * El vínculo es el prefijo del código, así que solo vale cuando el registro
     * de la familia existe y está marcado como base.
     */
    public function base(): ?Material
    {
        if ($this->generico) {
            return null;
        }

        $familia = self::familiaDeCodigo($this->codigo_material);

        if ($familia === null || $familia === self::normalizarCodigo($this->codigo_material)) {
            return null;
        }

        return static::query()->where('generico', true)->where('codigo_material', $familia)->first();
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

    /**
     * Nombre con el que se muestra el material: el de la fila ("Nombre del
     * material" del archivo) y, si vino vacío, el nombre base de la familia.
     */
    public function getNombreMostrarAttribute(): string
    {
        return trim((string) $this->nombre_material) !== ''
            ? (string) $this->nombre_material
            : trim((string) $this->nombre_base);
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
