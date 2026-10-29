<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialVariant extends Model
{
    public const TIPO_COLOR = 'color';

    public const TIPO_VARIANTE = 'variante';

    protected $table = 'material_variants';

    protected $fillable = [
        'material_id',
        'nombre',
        'tipo',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function scopeColores(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_COLOR);
    }

    public function scopeOtrasVariantes(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_VARIANTE);
    }
}
