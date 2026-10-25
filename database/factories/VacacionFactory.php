<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Vacacion;
/**
 * @extends Factory<Vacacion>
 */
use Illuminate\Database\Eloquent\Factories\Factory;

class VacacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoria_id' => Categoria::factory(),
            'anios' => fake()->numberBetween(1, 30),
            'dias' => fake()->numberBetween(12, 35),
        ];
    }
}
