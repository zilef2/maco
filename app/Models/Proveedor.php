<?php

namespace App\Models;

use Database\Factories\ProveedorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Proveedor extends Model
{
    /** @use HasFactory<ProveedorFactory> */
    use HasFactory;

    public const TIPOS_PROVEEDOR = [
        'Materia prima',
        'Servicio',
        'Mixto',
        'Otro',
    ];

    public const ESTADOS = [
        'Activo',
        'Inactivo',
    ];

    public const FORMAS_PAGO = [
        'Contado',
        'Crédito 8 días',
        'Crédito 15 días',
        'Crédito 30 días',
        'Anticipo',
        'Otro',
    ];

    public const CLASIFICACIONES = [
        'proveedor',
        'taller de proceso externo',
        'macondas',
    ];

    protected $fillable = [
        'codigo_proveedor',
        'nit',
        'nombre_comercial',
        'razon_social',
        'direccion',
        'observaciones_direccion',
        'correo_electronico',
        'ciudad',
        'telefono_principal',
        'tipo_proveedor',
        'que_suministra',
        'persona_contacto',
        'celular_contacto',
        'forma_pago',
        'tiempo_entrega',
        'estado',
        'observaciones_generales',
        'clasificacion',
        'nombre',
        'categoria',
        'celular1',
        'celular2',
    ];

    protected static function booted(): void
    {
        static::saving(function (Proveedor $proveedor): void {
            if (empty($proveedor->nombre)) {
                $proveedor->nombre = $proveedor->nombre_comercial ?: ($proveedor->razon_social ?: 'Sin nombre');
            }

            // Auto-generate codigo_proveedor if not set (format: PRV-1, PRV-2, ...)
            if (empty($proveedor->codigo_proveedor)) {
                // Se ordena por la clave primaria, no por el código textual:
                // así la consulta usa el índice y "PRV-1000" no queda antes
                // que "PRV-999". En cargas masivas la generación se hace una
                // sola vez desde el servicio de Excel.
                $ultimo = Proveedor::query()
                    ->whereNotNull('codigo_proveedor')
                    ->orderByDesc('id')
                    ->value('codigo_proveedor');
                $numero = ((int) preg_replace('/\D+/', '', (string) $ultimo)) + 1;
                $proveedor->codigo_proveedor = 'PRV-' . str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function materialesHabituales(): HasMany
    {
        return $this->hasMany(Material::class, 'proveedor_habitual');
    }

    public function kitProcesos(): HasMany
    {
        return $this->hasMany(KitProceso::class, 'proveedor_id');
    }

    public static function getFillableWithTypes(): array
    {
        $table = (new static)->getTable();
        $columns = DB::select("SHOW COLUMNS FROM {$table}");
        $fillable = (new static)->getFillable();
        $result = [];

        foreach ($columns as $column) {
            if (!in_array($column->Field, $fillable) || $column->Field === 'id') {
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
