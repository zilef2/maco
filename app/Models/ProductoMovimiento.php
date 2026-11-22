<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ProductoMovimiento extends Model
{
    use HasFactory;

    protected $table = 'producto_movimientos';

    protected $fillable = [
        'producto_id',
        'talla',
        'orden_produccion_id',
        'tipo_movimiento',
        'cantidad',
        'existencia_anterior',
        'existencia_nueva',
        'motivo',
        'user_id',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function ordenProduccion(): BelongsTo
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registro de la diferencia entre la existencia anterior y la nueva de una
     * talla. Sin diferencia no hay movimiento.
     */
    public static function registrar(
        int $productoId,
        ?string $talla,
        int $existenciaAnterior,
        int $existenciaNueva,
        ?int $ordenProduccionId = null,
        ?string $motivo = null,
        ?int $usuarioId = null
    ): ?self {
        if ($existenciaAnterior === $existenciaNueva) {
            return null;
        }

        return self::create([
            'producto_id' => $productoId,
            'talla' => $talla,
            'orden_produccion_id' => $ordenProduccionId,
            'tipo_movimiento' => $existenciaNueva > $existenciaAnterior ? 'entrada' : 'salida',
            'cantidad' => abs($existenciaNueva - $existenciaAnterior),
            'existencia_anterior' => $existenciaAnterior,
            'existencia_nueva' => $existenciaNueva,
            'motivo' => $motivo,
            // En la importación en segundo plano no hay sesión: el job pasa el
            // id de quien subió el archivo.
            'user_id' => $usuarioId ?? Auth::id(),
        ]);
    }

    /**
     * Unidades netas que una OP ya metió en el inventario de una talla. Es lo
     * que hay que descontar cuando la misma OP corrige su recepción, para no
     * contar dos veces lo que ya entró.
     */
    public static function unidadesDeOrden(int $productoId, string $talla, int $ordenProduccionId): int
    {
        $entradas = (int) self::where('producto_id', $productoId)
            ->where('talla', $talla)
            ->where('orden_produccion_id', $ordenProduccionId)
            ->where('tipo_movimiento', 'entrada')
            ->sum('cantidad');

        $salidas = (int) self::where('producto_id', $productoId)
            ->where('talla', $talla)
            ->where('orden_produccion_id', $ordenProduccionId)
            ->where('tipo_movimiento', 'salida')
            ->sum('cantidad');

        return $entradas - $salidas;
    }
}
