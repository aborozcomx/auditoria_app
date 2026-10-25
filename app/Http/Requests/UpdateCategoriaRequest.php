<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoriaRequest extends FormRequest
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
        /** @var Categoria|null $categoria */
        $categoria = $this->route('categoria');

        return [
            'nombre' => [
                'required',
                'string',
                'max:150',
                Rule::unique('categorias', 'nombre')->ignore($categoria?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.max' => 'El nombre no debe superar los 150 caracteres.',
            'nombre.unique' => 'Ya existe una categoría con este nombre.',
        ];
    }
}
