<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;

class PermissionStoreModuloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modulo' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:255',
        ];
    }
}
