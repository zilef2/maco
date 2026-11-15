<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Material concreto elegido en una OP para una línea genérica de la ficha.
 *
 * La ficha declara el genérico de la familia (COM-CAP-0) porque allí solo se
 * define el consumo por par; el color o la talla se deciden al registrar la OP
 * y es ese material (COM-CAP-37) el que recibe el descuento, la reserva y el
 * costo.
 */
class OrdenProduccionMaterialConcreto extends Model
{
    use HasFactory;

    public const OBJETIVO_PRODUCTO = 'producto';
    public const OBJETIVO_MATERIAL = 'material';

    protected $table = 'orden_produccion_material_concreto';

    protected $fillable = [
        'orden_produccion_id',
        'objetivo_tipo',
        'objetivo_id',
        'material_generico_id',
        'material_concreto_id',
    ];

    protected $casts = [
        'orden_produccion_id' => 'integer',
        'objetivo_id' => 'integer',
        'material_generico_id' => 'integer',
        'material_concreto_id' => 'integer',
    ];

    public function ordenProduccion(): BelongsTo
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }

    /** Producto de la OP que consume esta línea de la ficha. */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'objetivo_id');
    }

    /** Material procesado de la OP que consume esta línea de la ficha. */
    public function materialObjetivo(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'objetivo_id');
    }

    public function generico(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_generico_id');
    }

    public function concreto(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_concreto_id');
    }
}
