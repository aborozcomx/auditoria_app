<?php

namespace App\Http\Requests;

use App\Models\Uma;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUmaRequest extends FormRequest
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
        /** @var Uma|null $uma */
        $uma = $this->route('uma');

        return [
            'anio' => [
                'required',
                'integer',
                'between:1990,2100',
                Rule::unique('umas', 'anio')->ignore($uma?->id),
            ],
            'saldo' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'anio.required' => 'El año es obligatorio.',
            'anio.integer' => 'El año debe ser un número entero.',
            'anio.between' => 'El año debe estar entre 1990 y 2100.',
            'anio.unique' => 'Ya existe un registro de UMA para este año.',
            'saldo.required' => 'El saldo o valor diario es obligatorio.',
            'saldo.numeric' => 'El saldo debe ser un número válido.',
            'saldo.min' => 'El saldo debe ser mayor a cero.',
        ];
    }
}
