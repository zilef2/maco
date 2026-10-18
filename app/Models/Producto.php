<?php

namespace App\Models;

use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    protected $fillable = [
        'Codigo_interno',
         'ID_producto_Shopify',
         'Categoria_original',
         'Familia_normalizada',
         'Titulo_Shopify',
         'Control_de_variante',
         'Opciones',
         'Estado',
         'Observaciones',
         'descripcion',
         'precio_predicho',
         'precio_preventa'
        ];

//     05 ficha ensamble
// OP
// Producto / referencia
// Cantidad total
// Colección
// Taller
// Fecha entrega

    public function ordenesProduccion()
    {
        return $this->belongsToMany(OrdenProduccion::class, 'orden_produccion_producto')->withTimestamps();
    }

    public function distribucionesPorTallas()
    {
        return $this->belongsToMany(Distribucion_por_tallas::class, 'distribucion_producto', 'producto_id', 'distribucion_por_talla_id')->withTimestamps();
    }

    public function fichaTecnica()
    {
        return $this->belongsToMany(FichaTecnica::class, 'ficha_tecnica_producto', 'producto_id', 'ficha_tecnica_id')->withTimestamps();
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
