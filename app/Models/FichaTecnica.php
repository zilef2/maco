<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FichaTecnica extends Model
{
    /** @use HasFactory<\Database\Factories\FichaTecnicaFactory> */
    use HasFactory;

    protected $fillable = ['nombre'];

    protected $appends = ['valor_total_ficha'];

    public function materiales(): HasMany
    {
        return $this->hasMany(FichaTecnicaMateriales::class);
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'ficha_tecnica_producto', 'ficha_tecnica_id', 'producto_id')->withTimestamps();
    }

    /**
     * Costo total de la ficha por unidad de producto.
     *
     * Cada línea aporta su costo unitario multiplicado por el consumo
     * estándar. Ejemplo: un material de 1.000 con consumo 2 aporta 2.000.
     */
    public function getValorTotalFichaAttribute(): float
    {
        if ($this->relationLoaded('materiales')) {
            return (float) $this->materiales->sum(
                fn (FichaTecnicaMateriales $material) => (float) $material->costo_total
            );
        }

        return (float) $this->materiales()
            ->get()
            ->sum(fn (FichaTecnicaMateriales $material) => (float) $material->costo_total);
    }

    /**
     * Ids de productos que ya tienen ficha (excluyendo la ficha indicada).
     */
    public static function productosConFicha(array $productoIds, ?int $exceptoFichaId = null): array
    {
        return self::query()
            ->whereKeyNot($exceptoFichaId ?? 0)
            ->whereHas('productos', fn ($q) => $q->whereIn('producto_id', $productoIds))
            ->with('productos:id')
            ->get()
            ->flatMap(fn ($ficha) => $ficha->productos->pluck('id'))
            ->intersect($productoIds)
            ->values()
            ->all();
    }
}
