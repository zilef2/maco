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
            'tipo' => ['required', 'in:taller,externa'],
            'taller_id' => ['required_if:tipo,taller', 'nullable', 'integer', 'exists:tallers,id'],
            'empresa_externa_id' => ['required_if:tipo,externa', 'nullable', 'integer', 'exists:empresa_externas,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'estado_op_material_id' => ['nullable', 'integer', 'exists:estado_op_materials,id'],
            'externa' => ['nullable', 'boolean'],
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
            'tipo.required' => 'Debe indicar si la OP es de taller o externa.',
            'tipo.in' => 'El tipo debe ser taller o externa.',
            'taller_id.required_if' => 'El taller es obligatorio cuando la OP es de taller.',
            'taller_id.exists' => 'El taller seleccionado no existe.',
            'empresa_externa_id.required_if' => 'La empresa externa es obligatoria cuando la OP es externa.',
            'empresa_externa_id.exists' => 'La empresa externa seleccionada no existe.',
            'user_id.required' => 'El responsable es obligatorio.',
            'user_id.exists' => 'El responsable seleccionado no existe.',
            'estado_op_material_id.exists' => 'El estado de materiales seleccionado no existe.',
            'externa.boolean' => 'El campo externa debe ser verdadero o falso.',
        ];
    }
}
