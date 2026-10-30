<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonInterface;

/**
 * Una petición autenticada registrada por el middleware RegistrarActividad.
 *
 * Es un registro inmutable: solo importa `created_at`, por eso no hay
 * `updated_at`.
 */
class ActividadRegistro extends Model
{
    public const ACCION_LOGIN = 'login';

    public const ACCION_LOGOUT = 'logout';

    protected $table = 'actividad_registros';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'nombre_usuario',
        'ruta_nombre',
        'ruta_uri',
        'metodo',
        'modulo',
        'accion',
        'controlador',
        'status',
        'duracion_ms',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'status' => 'integer',
        'duracion_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDesde(Builder $consulta, CarbonInterface $desde): Builder
    {
        return $consulta->where('created_at', '>=', $desde);
    }
}
