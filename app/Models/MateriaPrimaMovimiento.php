<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MateriaPrimaMovimiento extends Model
{
    use HasFactory;

    protected $table = 'materia_prima_movimientos';

    protected $fillable = [
        'materia_prima_id',
        'tipo_movimiento',
        'cantidad',
        'existencia_anterior',
        'existencia_nueva',
        'costo_unitario',
        'motivo',
        'user_id',
    ];

    public function materiaPrima(): BelongsTo
    {
        return $this->belongsTo(materias_primas::class, 'materia_prima_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
