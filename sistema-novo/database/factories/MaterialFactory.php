<?php

namespace Database\Factories;

use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => ucfirst($this->faker->unique()->word()),
            'unidade_base' => $this->faker->randomElement(['un', 'pct', 'cx', 'rolo']),
            'fator_embalagem' => 1,
            'estoque' => $this->faker->randomFloat(3, 0, 500),
            'estoque_minimo' => $this->faker->randomFloat(3, 0, 20),
            'custo_medio' => $this->faker->randomFloat(6, 0.01, 50),
            'observacoes' => null,
        ];
    }
}
