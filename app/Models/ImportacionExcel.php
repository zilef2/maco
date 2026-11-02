<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una carga de Excel: cuándo empezó, cuánto tardó y cómo terminó.
 */
class ImportacionExcel extends Model
{
    protected $table = 'importacion_excels';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PROCESANDO = 'procesando';

    public const ESTADO_COMPLETADO = 'completado';

    public const ESTADO_FALLIDO = 'fallido';

    protected $fillable = [
        'entidad',
        'archivo',
        'user_id',
        'estado',
        'filas',
        'creados',
        'actualizados',
        'subida_ms',
        'duracion_ms',
        'iniciado_en',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'filas' => 'integer',
            'creados' => 'integer',
            'actualizados' => 'integer',
            'subida_ms' => 'integer',
            'duracion_ms' => 'integer',
            'iniciado_en' => 'float',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
