<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'productos' => ['nullable', 'array'],
            'productos.*' => ['integer', 'exists:productos,id'],
            'materiales' => ['nullable', 'array'],
            'materiales.*' => ['integer', 'exists:materials,id'],
            'materiales_cantidad' => ['nullable', 'array'],
            'materiales_cantidad.*' => ['integer', 'min:1'],
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
            'productos.*.integer' => 'Cada producto seleccionado debe ser válido.',
            'productos.*.exists' => 'Uno de los productos seleccionados no existe.',
            'materiales.*.integer' => 'Cada material seleccionado debe ser válido.',
            'materiales.*.exists' => 'Uno de los materiales seleccionados no existe.',
            'materiales_cantidad.*.integer' => 'La cantidad de cada material debe ser un número entero.',
            'materiales_cantidad.*.min' => 'La cantidad de cada material debe ser mayor a cero.',
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

    /**
     * La OP produce productos o materiales procesados, no ambos. Además, un
     * material solo puede elegirse si está marcado como procesado y tiene ficha
     * técnica (sin receta no se puede generar su kit).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $productos = array_filter((array) $this->input('productos', []));
            $materiales = array_values(array_unique(array_map('intval', (array) $this->input('materiales', []))));

            if (empty($productos) && empty($materiales)) {
                $validator->errors()->add('productos', 'Debe seleccionar al menos un producto o un material procesado.');
            }

            if (!empty($productos) && !empty($materiales)) {
                $validator->errors()->add('materiales', 'Seleccione productos o materiales procesados, no ambos.');
            }

            if (empty($materiales)) {
                return;
            }

            $elegibles = Material::query()
                ->whereIn('id', $materiales)
                ->where('material_procesado', true)
                ->whereHas('fichaTecnica')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($elegibles) !== count($materiales)) {
                $validator->errors()->add(
                    'materiales',
                    'Solo se pueden producir materiales con proceso y ficha técnica.'
                );
            }

            foreach ($materiales as $materialId) {
                if ((int) ($this->input('materiales_cantidad.' . $materialId, 0)) <= 0) {
                    $validator->errors()->add(
                        'materiales_cantidad.' . $materialId,
                        'Indique una cantidad mayor a cero para cada material.'
                    );
                }
            }
        });
    }
}
