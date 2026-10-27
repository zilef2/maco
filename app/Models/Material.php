<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'maneja_color',
        'maneja_talla',
        'maneja_otra_variante',
        'estado',
        'variante_atributo_sugerido',
        'proveedor_habitual',
        'material_procesado',
        'observaciones'];

    public function movimientos(): HasMany
    {
        return $this->hasMany(MaterialMovimiento::class, 'material_id');
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
}
