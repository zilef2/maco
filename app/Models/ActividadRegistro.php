<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonInterface;

/**
 * Un paso de la bitácora: una petición HTTP (`tipo = peticion`) o una acción de
 * negocio registrada desde dentro de un controlador (`tipo = accion`).
 *
 * Es un registro inmutable: solo importa `created_at`, por eso no hay
 * `updated_at`.
 */
class ActividadRegistro extends Model
{
    public const ACCION_LOGIN = 'login';

    public const ACCION_LOGOUT = 'logout';

    public const ACCION_LOGIN_FALLIDO = 'login_fallido';

    public const TIPO_PETICION = 'peticion';

    public const TIPO_ACCION = 'accion';

    protected $table = 'actividad_registros';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'nombre_usuario',
        'ruta_nombre',
        'ruta_uri',
        'metodo',
        'tipo',
        'peticion',
        'modulo',
        'accion',
        'controlador',
        'entidad',
        'entidad_id',
        'detalle',
        'status',
        'error',
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

    public function scopeAcciones(Builder $consulta): Builder
    {
        return $consulta->where('tipo', self::TIPO_ACCION);
    }
}
