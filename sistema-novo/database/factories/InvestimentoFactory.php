<?php

namespace Database\Factories;

use App\Models\Investimento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investimento>
 */
class InvestimentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => $this->faker->words(3, true),
            'categoria' => $this->faker->randomElement(['Equipamento', 'Bancada', 'Manutenção']),
            'data' => $this->faker->dateTimeBetween('-1 year')->format('Y-m-d'),
            'valor' => $this->faker->randomFloat(2, 20, 1000),
            'quitado' => true,
            'observacoes' => null,
        ];
    }
}
