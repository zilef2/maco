<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReiniciarOrdenesProduccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) ($user && ($user->hasRole('superadmin') || $user->can('issuperadmin')));
    }

    public function rules(): array
    {
        return [
            'estado_op_id' => ['required', 'integer', 'exists:estado_operacions,id'],
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:10'],
            'borrar_existentes' => ['nullable', 'boolean'],
        ];
    }
}
