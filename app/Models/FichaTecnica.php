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

    public function getValorTotalFichaAttribute(): float
    {
        if ($this->relationLoaded('materiales')) {
            return (float) $this->materiales->sum('costo_asociado_teorico');
        }
        return (float) $this->materiales()->sum('costo_asociado_teorico');
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
