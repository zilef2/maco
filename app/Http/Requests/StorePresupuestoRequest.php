<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePresupuestoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {   
        return [
            // Obligatorios
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'monto_total' => ['required', 'numeric', 'min:0'],
            'monto_ejecutado' => ['nullable', 'numeric'],
            'monto_disponible' => ['nullable', 'numeric'],
            'total_salarios' => ['nullable', 'numeric'],
            'total_viaticos' => ['nullable', 'numeric'],
            'total_materiales' => ['nullable', 'numeric'],
            'estado' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string'],
            'porcentaje_ejecutado' => ['nullable', 'numeric'],
            'umbral_alerta' => ['nullable', 'numeric'],
            'fecha_ultimo_reajuste' => ['nullable', 'date'],
            'motivo_reajuste' => ['nullable', 'string'],

            // Opcionales (comentados en la lista de titulos)
            'user_id_aprobado' => ['nullable', 'integer'],
            'aprobado_por' => ['nullable', 'string', 'max:255'],
            'fecha_aprobacion' => ['nullable', 'date'],
            'codigo_interno' => ['nullable', 'string', 'max:255'],
            ];
        }
    }
