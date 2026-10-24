<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTallaRequest extends FormRequest
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
            'referencia' => 'required|string|max:255',
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tallas', 'nombre')->ignore($this->route('talla')?->id),
            ],
            'descripcion' => 'nullable|string',
            'tipo' => 'required|string|max:255',
            'estado' => 'nullable|integer',
        ];
    }
}
