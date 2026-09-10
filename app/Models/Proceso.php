<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proceso extends Model {
	
	/** @use HasFactory<\Database\Factories\ProcesoFactory> */
	use HasFactory;
	
	protected $fillable = [
		'id',
		'llave_proceso',
		'idProceso',
		'id_conexion',
		'fecha_proceso',
		'fecha_ultima_actuacion',
		'despacho',
		'departamento',
		'sujetos_procesales',
		'es_privado',
		'cant_filas',
		'validacioncini',
		'pdf_name',
		'pdf_size',
		'pdf_sumarized',
		'pdf_path',
		
		'Numprocesos',
		'numprocesos_anterior',
//		'UltimaActuacion',
		'UltimaActuacionForHumnas',
	];
	
	protected $casts = [
        'sujetos_procesales' => 'array', // Esto lo convierte en un array de PHP
    ];
	

    public static function getFillableWithTypes()
    {
        $table = (new static)->getTable();
        $columns = \DB::select("SHOW COLUMNS FROM {$table}");

        $fillable = (new static)->getFillable();
        $result = [];

        foreach ($columns as $column) {
            if (!in_array($column->Field, $fillable)) {
                continue;
            }

            // Detectar tipo
            $type = 'text'; // default
            if (str_contains($column->Type, 'int')) {
                $type = 'integer';
            } elseif (str_contains($column->Type, 'decimal') || str_contains($column->Type, 'float')) {
                $type = 'dinero';
            } elseif (str_contains($column->Type, 'foreign') || $column->Field === 'oferta_id') {
                $type = 'foreign';
            }

            $result[] = [
                'order' => $column->Field,
                'label' => $column->Field,
                'type'  => $type,
            ];
        }

        return $result;
    }

}