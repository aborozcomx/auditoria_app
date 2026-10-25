<?php

namespace App\Http\Requests;

use App\Models\Empresa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpresaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('rfc')) {
            $this->merge([
                'rfc' => strtoupper(trim((string) $this->input('rfc'))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Empresa|null $empresa */
        $empresa = $this->route('empresa');

        return [
            'nombre' => ['required', 'string', 'max:150'],
            'rfc' => [
                'required',
                'string',
                'min:12',
                'max:13',
                'regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/',
                Rule::unique('empresas', 'rfc')->ignore($empresa?->id),
            ],
            'razon_social' => ['required', 'string', 'max:255'],
            'dias_aguinaldo' => ['required', 'integer', 'min:1', 'max:365'],
            'prima_vacacional' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre comercial de la empresa es obligatorio.',
            'rfc.required' => 'El RFC es obligatorio.',
            'rfc.regex' => 'El formato del RFC no es válido (debe tener 12 o 13 caracteres alfanuméricos oficiales).',
            'rfc.unique' => 'Ya existe una empresa registrada con este RFC.',
            'razon_social.required' => 'La razón social es obligatoria.',
            'dias_aguinaldo.required' => 'Los días de aguinaldo son obligatorios.',
            'dias_aguinaldo.integer' => 'Los días de aguinaldo deben ser un número entero.',
            'dias_aguinaldo.min' => 'Los días de aguinaldo deben ser al menos 1.',
            'prima_vacacional.required' => 'El porcentaje de prima vacacional es obligatorio.',
            'prima_vacacional.numeric' => 'La prima vacacional debe ser un número.',
            'prima_vacacional.min' => 'La prima vacacional no puede ser negativa.',
            'prima_vacacional.max' => 'La prima vacacional no puede superar el 100%.',
        ];
    }
}
