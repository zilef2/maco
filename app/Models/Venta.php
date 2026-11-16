<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    protected $fillable = [
        'fecha',
        'forma_pago',
        'forma_pago_detalle',
        'descuento_tipo',
        'descuento_valor',
        'descuento_monto',
        'impuesto_tipo',
        'impuesto_valor',
        'impuesto_monto',
        'subtotal',
        'total',
        'observaciones',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'descuento_valor' => 'decimal:2',
        'descuento_monto' => 'decimal:2',
        'impuesto_valor' => 'decimal:2',
        'impuesto_monto' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
