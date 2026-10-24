<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

    class OrdenProduccion extends Model
{
    /** @use HasFactory<\Database\Factories\OrdenProduccionFactory> */
    use HasFactory;
    protected $fillable = [
        'numero_op',
         'producto_referencia_id',
         'cantidad_planificada',
         'fecha_prevista_envio_taller',
         'taller_id',
         'tipo',
         'empresa_externa_id',
         'estado_op_id',
         'estado_op_secundario_id',
         'estado_op_material_id', 'externa', 'user_id', 'id_shopify_producto', 'observaciones'];

    protected $casts = [
        'externa' => 'boolean',
        'empresa_externa_id' => 'integer',
        'estado_op_secundario_id' => 'integer',
        'recepcion_desde' => 'datetime',
    ];

    /**
     * Siguiente número de OP disponible: toma el mayor `numero_op` registrado
     * (formato OP-XXX) y le suma uno. Nunca baja de OP-100 ni repite un número.
     */
    public static function siguienteNumeroOp(): string
    {
        $maxOp = static::query()
            ->where('numero_op', 'like', 'OP-%')
            ->selectRaw("MAX(CAST(REPLACE(numero_op, 'OP-', '') AS UNSIGNED)) as max_op")
            ->value('max_op');

        return 'OP-' . max(100, ((int) $maxOp) + 1);
    }

    public function productoReferencia()
    {
        return $this->belongsTo(Producto::class, 'producto_referencia_id');
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'orden_produccion_producto')->withTimestamps();
    }

    public function taller()
    {
        return $this->belongsTo(Taller::class, 'taller_id');
    }

    public function empresaExterna()
    {
        return $this->belongsTo(EmpresaExterna::class, 'empresa_externa_id');
    }

    public function estadoOp()
    {
        return $this->belongsTo(EstadoOperacion::class, 'estado_op_id');
    }

    public function estadoOpSecundario()
    {
        return $this->belongsTo(EstadoOperacion::class, 'estado_op_secundario_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estadoOpMaterial()
    {
        return $this->belongsTo(EstadoOpMaterial::class, 'estado_op_material_id');
    }

    public function distribucionesPorTallas()
    {
        return $this->belongsToMany(Distribucion_por_tallas::class, 'distribucion_orden_produccion', 'orden_produccion_id', 'distribucion_por_talla_id')->withTimestamps();
    }

    /**
     * Recepciones de calidad registradas para la OP (una por producto + talla).
     */
    public function recepcionesCalidad()
    {
        return $this->hasMany(RecepcionCalidad::class, 'orden_produccion_id');
    }

    /**
     * Kits de materiales generados desde la ficha técnica de los productos.
     */
    public function kitProcesos()
    {
        return $this->hasMany(KitProceso::class, 'orden_produccion_id');
    }

    /**
     * Materiales que la OP tiene fuera en procesos externos.
     */
    public function procesosExternos()
    {
        return $this->hasMany(ProcesoExterno::class, 'orden_produccion_id');
    }

    /**
     * Pendientes de producción abiertos para la OP.
     */
    public function pendientesProducciones()
    {
        return $this->hasMany(PendientesProduccion::class, 'orden_produccion_id');
    }

    /**
     * Registro de cierre (conciliación) de la OP.
     */
    public function cierres()
    {
        return $this->hasMany(CierreOrdenProduccion::class, 'orden_produccion_id');
    }

    public static function getFillableWithTypes(): array
    {
        $table = (new static)->getTable();
        $columns = DB::select("SHOW COLUMNS FROM {$table}");

        $fillable = (new static)->getFillable();
        $result = [];

        foreach ($columns as $column) {
            if (! in_array($column->Field, $fillable) || $column->Field === 'id') {
                continue;
            }

            $type = 'text';
            if (str_contains($column->Type, 'foreign') || str_ends_with($column->Field, '_id')) {
                $type = 'foreign';
            } elseif (str_contains($column->Type, 'int')) {
                $type = 'integer';
            } elseif (str_contains($column->Type, 'decimal') || str_contains($column->Type, 'float')) {
                $type = 'dinero';
            } elseif (str_contains($column->Type, 'date') || str_contains($column->Type, 'datetime')) {
                $type = 'date';
            }

            $result[] = [
                'order' => $column->Field,
                'label' => $column->Field,
                'type' => $type,
            ];
        }

        return $result;
    }
}
