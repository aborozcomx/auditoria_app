<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = fake()->company();

        return [
            'nombre' => $company,
            'rfc' => strtoupper(fake()->bothify('????######???')),
            'razon_social' => $company.' S.A. de C.V.',
            'dias_aguinaldo' => fake()->randomElement([15, 20, 30]),
            'prima_vacacional' => fake()->randomElement([25.00, 30.00, 50.00]),
        ];
    }
}
