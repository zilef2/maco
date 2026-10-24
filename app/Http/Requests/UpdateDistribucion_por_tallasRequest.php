<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistribucion_por_tallasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'talla' => [
                'required',
                'string',
                'max:255',
                Rule::unique('distribucion_por_tallas', 'talla')->ignore($this->route('distribucion_por_tallas')?->id),
            ],
            'cantidad_planificada' => 'required|integer|min:0',
            'cantidad_recibida_buena' => 'nullable|integer|min:0',
            'cantidad_pendiente_reparacion' => 'nullable|integer|min:0',
        ];
    }
}
