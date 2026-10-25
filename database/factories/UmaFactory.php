<?php

namespace Database\Factories;

use App\Models\Uma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Uma>
 */
class UmaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'anio' => fake()->unique()->numberBetween(2000, 2040),
            'saldo' => fake()->randomFloat(2, 50, 150),
        ];
    }
}
