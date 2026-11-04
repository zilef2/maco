<?php

namespace App\Http\Requests;

use App\Models\Proveedor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmpresaExternaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La empresa externa comparte la ficha del proveedor y del taller.
     * El código lo genera el modelo con el prefijo EMP.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'nit' => ['nullable', 'string', 'max:255'],
            'nombre_comercial' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'observaciones_direccion' => ['nullable', 'string', 'max:1000'],
            'correo_electronico' => ['nullable', 'email', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'telefono_principal' => ['nullable', 'string', 'max:50'],
            'tipo_proveedor' => ['required', 'string', Rule::in(Proveedor::TIPOS_PROVEEDOR)],
            'que_suministra' => ['nullable', 'string', 'max:1000'],
            'persona_contacto' => ['nullable', 'string', 'max:255'],
            'celular_contacto' => ['nullable', 'string', 'max:50'],
            'forma_pago' => ['nullable', 'string', Rule::in(Proveedor::FORMAS_PAGO)],
            'tiempo_entrega' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'string', Rule::in(Proveedor::ESTADOS)],
            'observaciones_generales' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre_comercial' => 'nombre',
            'nit' => 'NIT',
            'tipo_proveedor' => 'tipo de proveedor',
            'que_suministra' => 'qué suministra',
            'correo_electronico' => 'correo',
        ];
    }
}
