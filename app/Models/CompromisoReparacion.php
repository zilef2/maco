<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompromisoReparacion extends Model
{
    use HasFactory;

    protected $table = 'compromisos_reparacion';

    protected $fillable = [
        'orden_produccion_id',
        'producto_referencia_id',
        'talla',
        'cantidad_en_reparacion',
        'fecha_compromiso',
        'atendido',
    ];

    protected $casts = [
        'cantidad_en_reparacion' => 'integer',
        'fecha_compromiso' => 'date',
        'atendido' => 'boolean',
    ];

    public function ordenProduccion()
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }

    /**
     * El compromiso aún sin resolver de una talla (producto + talla) de la OP.
     */
    public static function abiertoPorTalla(int $ordenProduccionId, ?string $talla, ?int $productoId): ?self
    {
        $consulta = static::query()
            ->where('orden_produccion_id', $ordenProduccionId)
            ->where('atendido', false);

        if ($talla === null) {
            $consulta->whereNull('talla');
        } else {
            $consulta->where('talla', $talla);
        }

        if ($productoId === null) {
            $consulta->whereNull('producto_referencia_id');
        } else {
            $consulta->where('producto_referencia_id', $productoId);
        }

        return $consulta->orderByDesc('id')->first();
    }
}
