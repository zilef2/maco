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
            'fecha_prevista_envio_taller' => ['nullable', 'date'],
            'taller_id' => ['required', 'integer', 'exists:tallers,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
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
            'taller_id.required' => 'El taller es obligatorio.',
            'taller_id.exists' => 'El taller seleccionado no existe.',
            'user_id.required' => 'El responsable es obligatorio.',
            'user_id.exists' => 'El responsable seleccionado no existe.',
        ];
    }
}
