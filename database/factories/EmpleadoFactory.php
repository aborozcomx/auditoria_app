<?php

namespace Database\Factories;

use App\Models\Empleado;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empleado>
 */
class EmpleadoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'numero_empleado' => (string) fake()->unique()->numberBetween(1000, 99999),
            'nombre' => fake()->name(),
            'rfc_receptor' => strtoupper(fake()->bothify('????######???')),
            'curp' => strtoupper(fake()->bothify('????######??????##')),
            'numero_seguro_social' => fake()->numerify('###########'),
            'salario_base' => fake()->randomFloat(2, 200, 1500),
            'salario_diario_integrado' => fake()->randomFloat(2, 250, 1800),
        ];
    }
}
