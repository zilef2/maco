<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurgarDatosPruebaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) ($user && ($user->hasRole('superadmin') || $user->can('issuperadmin')));
    }

    public function rules(): array
    {
        return [
            'confirmacion' => ['required', 'string', 'in:BORRAR TODO DEFINITIVAMENTE'],
        ];
    }
}

