<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialMovimiento extends Model
{
    use HasFactory;

    protected $table = 'material_movimientos';

    protected $fillable = [
        'material_id',
        'tipo_movimiento',
        'cantidad',
        'existencia_anterior',
        'existencia_nueva',
        'motivo',
        'user_id',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
