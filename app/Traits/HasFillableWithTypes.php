<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait HasFillableWithTypes
{
    /**
     * Devuelve los campos fillable del modelo con el tipo que usa el
     * frontend para renderizar los formularios.
     *
     * @return array<int, array{order: string, label: string, type: string}>
     */
    public static function getFillableWithTypes(): array
    {
        $model = new static();
        $table = $model->getTable();
        $columns = DB::select("SHOW COLUMNS FROM {$table}");

        $fillable = $model->getFillable();
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
