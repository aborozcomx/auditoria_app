<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVacacionRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'anios' => [
                'required',
                'integer',
                'between:1,60',
                Rule::unique('vacaciones', 'anios')->where(function ($query) {
                    return $query->where('categoria_id', $this->input('categoria_id'));
                }),
            ],
            'dias' => ['required', 'integer', 'between:1,365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categoria_id.required' => 'Debe seleccionar una categoría.',
            'categoria_id.exists' => 'La categoría seleccionada no es válida.',
            'anios.required' => 'El número de años de antigüedad es obligatorio.',
            'anios.integer' => 'El número de años debe ser un entero.',
            'anios.between' => 'Los años de antigüedad deben estar entre 1 y 60.',
            'anios.unique' => 'Ya existe una regla de vacaciones para este número de años en la categoría seleccionada.',
            'dias.required' => 'El número de días de vacaciones es obligatorio.',
            'dias.integer' => 'Los días de vacaciones deben ser un entero.',
            'dias.between' => 'Los días de vacaciones deben estar entre 1 y 365.',
        ];
    }
}
