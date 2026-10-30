<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyBulkMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->can('issuperadmin') || $user->hasRole('superadmin');
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'array', 'min:1'],
            'id.*' => ['required', 'integer', 'exists:materials,id'],
            'con_dependientes' => ['sometimes', 'boolean'],
        ];
    }

    public function ids(): array
    {
        return array_map('intval', $this->validated()['id']);
    }

    public function conDependientes(): bool
    {
        return (bool) ($this->validated()['con_dependientes'] ?? false);
    }
}
