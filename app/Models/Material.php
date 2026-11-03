<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;
    use HasFillableWithTypes;

    protected $fillable = ['codigo_material',
        'categoria',
        'nombre_base',
        'unidad_inventario',
        'existencia_actual',
        'gasto_diario',
        'maneja_color',
        'maneja_talla',
        'maneja_otra_variante',
        'estado',
        'variante_atributo_sugerido',
        'proveedor_habitual',
        'material_procesado',
        'observaciones',
        'costo_material'];

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
     * Regla de negocio: un material se identifica por colores o por otro tipo
     * de variante, nunca por ambos a la vez.
     *
     * @param  array<int, string>  $colores
     * @param  array<int, string>  $variantes
     *
     * @throws ValidationException
     */
    public function sincronizarVariantes(array $colores = [], array $variantes = []): void
    {
        $colores = self::normalizarListaVariantes($colores);
        $variantes = self::normalizarListaVariantes($variantes);

        if (!empty($colores) && !empty($variantes)) {
            throw ValidationException::withMessages([
                'colores' => 'Un material no puede tener colores y otras variantes al mismo tiempo. Usa solo una de las dos listas.',
            ]);
        }

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
        ])->save();
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
     */
    public function recalcularGastoDiario(int $ventanaDias = 30): ?float
    {
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
     * Recalcula el gasto_diario de todos los materiales activos.
     * Devuelve ['actualizados' => int, 'sin_consumo' => int].
     */
    public static function recalcularGastoDiarioDeTodos(int $ventanaDias = 30): array
    {
        $actualizados = 0;
        $sinConsumo = 0;

        foreach (self::where('estado', 'Activo')->cursor() as $material) {
            if ($material->recalcularGastoDiario($ventanaDias) !== null) {
                $actualizados++;
            } else {
                $sinConsumo++;
            }
        }

        return compact('actualizados', 'sinConsumo');
    }
}
