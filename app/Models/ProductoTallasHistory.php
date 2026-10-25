<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoTallasHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'producto_id',
        'user_id',
        'talla',
        'cantidad_planificada',
        'accion',
        'datos_anteriores',
        'datos_nuevos',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
    ];

    /**
     * Producto al que pertenece este historial.
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Usuario que realizó el cambio.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Registra un cambio en la distribución de tallas.
     *
     * @param int $productoId
     * @param string $talla
     * @param int $cantidad
     * @param string $accion 'create', 'update', 'delete'
     * @param array|null $datosAnteriores
     * @param array|null $datosNuevos
     * @return static
     */
    public static function registrar(
        int $productoId,
        string $talla,
        int $cantidad,
        string $accion,
        ?array $datosAnteriores = null,
        ?array $datosNuevos = null
    ): self {
        return self::create([
            'producto_id' => $productoId,
            'user_id' => auth()->id(),
            'talla' => $talla,
            'cantidad_planificada' => $cantidad,
            'accion' => $accion,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
        ]);
    }

    /**
     * Obtiene el historial de un producto, agrupado por fecha.
     *
     * @param int $productoId
     * @param int $limite Registros máximos a devolver
     * @return \Illuminate\Support\Collection
     */
    public static function porProducto(int $productoId, int $limite = 50)
    {
        return self::where('producto_id', $productoId)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));
    }
}
