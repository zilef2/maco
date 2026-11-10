<?php

namespace App\Http\Requests;

use App\Models\Proveedor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo_proveedor' => ['nullable', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:255'],
            'nombre_comercial' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'observaciones_direccion' => ['nullable', 'string', 'max:1000'],
            'correo_electronico' => ['nullable', 'email', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'telefono_principal' => ['nullable', 'string', 'max:50', 'regex:/^\d+$/'],
            // Se acepta cualquier texto porque "Otro" permite escribir el tipo.
            'tipo_proveedor' => ['nullable', 'string', 'max:255'],
            'que_suministra' => ['nullable', 'string', 'max:1000'],
            'persona_contacto' => ['nullable', 'string', 'max:255'],
            'celular_contacto' => ['nullable', 'string', 'max:50', 'regex:/^\d+$/'],
            // Se acepta cualquier texto porque "Otro" permite escribir el plazo.
            'forma_pago' => ['nullable', 'string', 'max:255'],
            'tiempo_entrega' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', Rule::in(Proveedor::ESTADOS)],
            'observaciones_generales' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'telefono_principal.regex' => 'El teléfono solo admite números.',
            'celular_contacto.regex' => 'El celular solo admite números.',
        ];
    }
}
