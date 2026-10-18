<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrdenProduccionRequest extends FormRequest
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
            'numero_op' => ['nullable', 'string', 'regex:/^OP-\d+$/'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*' => ['integer', 'exists:productos,id'],
            'cantidad_planificada' => ['required', 'numeric', 'gt:0'],
            'fecha_creacion' => ['required', 'date'],
            'fecha_prevista_envio_taller' => ['nullable', 'date'],
            'taller_id' => ['required', 'integer', 'exists:tallers,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'id_shopify_producto' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
        ];
    }

    /**
     * Mensajes de validación personalizados.
     */
    public function messages(): array
    {
        return [
            'numero_op.regex' => 'El número de OP debe tener el formato OP-XXX, donde XXX es un número.',
            'productos.required' => 'Debe existir al menos un producto.',
            'productos.min' => 'Debe existir al menos un producto.',
            'productos.*.integer' => 'Cada producto seleccionado debe ser válido.',
            'productos.*.exists' => 'Uno de los productos seleccionados no existe.',
            'cantidad_planificada.required' => 'La cantidad planificada es obligatoria.',
            'cantidad_planificada.gt' => 'La cantidad planificada debe ser mayor a cero.',
            'fecha_creacion.required' => 'La fecha de creación es obligatoria.',
            'fecha_creacion.before_or_equal' => 'La fecha de creación debe ser del 15 de octubre de 2025 o anterior.',
            'taller_id.required' => 'El taller es obligatorio.',
            'taller_id.exists' => 'El taller seleccionado no existe.',
            'user_id.required' => 'El responsable es obligatorio.',
            'user_id.exists' => 'El responsable seleccionado no existe.',
        ];
    }
}
