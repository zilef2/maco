<?php

namespace App\Models;

use App\Traits\HasFillableWithTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reserva de material producido por una OP (origen) para otra OP (destino).
 *
 * La necesidad del consumidor se cubre primero con existencia física y solo lo
 * que falta se compromete contra produccion pendiente de otras OP.
 */
class Compromisomaterial extends Model
{
    /** @use HasFactory<\Database\Factories\CompromisomaterialFactory> */
    use HasFactory;
    use HasFillableWithTypes;

    /** Reserva vigente: el material aun no se ha recibido en inventario. */
    public const ESTADO_COMPROMETIDO = 'comprometido';

    /** Reserva cubierta por material ya recibido, pero aun no consumida. */
    public const ESTADO_CUMPLIDO = 'cumplido';

    /** La OP destino dejo de necesitar el material (cambio o cancelacion). */
    public const ESTADO_LIBERADO = 'liberado';

    /** La OP destino ya entro a taller y consumio el material. */
    public const ESTADO_CONSUMIDO = 'consumido';

    /** Estados que siguen reservando unidades en la proyeccion. */
    public const ESTADOS_ACTIVOS = [self::ESTADO_COMPROMETIDO, self::ESTADO_CUMPLIDO];

    protected $fillable = [
        'material_id',
        'op_origen_id',
        'op_destino_id',
        'cantidad',
        'cantidad_cumplida',
        'estado',
    ];

    protected $casts = [
        'material_id' => 'integer',
        'op_origen_id' => 'integer',
        'op_destino_id' => 'integer',
        'cantidad' => 'float',
        'cantidad_cumplida' => 'float',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function opOrigen(): BelongsTo
    {
        return $this->belongsTo(OrdenProduccion::class, 'op_origen_id');
    }

    public function opDestino(): BelongsTo
    {
        return $this->belongsTo(OrdenProduccion::class, 'op_destino_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->whereIn('estado', self::ESTADOS_ACTIVOS);
    }

    /** Unidades reservadas que todavia no tienen material recibido detras. */
    public function pendienteDeCumplir(): float
    {
        return max(0, (float) $this->cantidad - (float) $this->cantidad_cumplida);
    }
}
