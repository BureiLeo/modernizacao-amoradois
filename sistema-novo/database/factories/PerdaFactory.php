<?php

namespace Database\Factories;

use App\Enums\PerdaTipo;
use App\Models\Material;
use App\Models\Perda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Perda>
 */
class PerdaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data' => now(),
            'tipo' => PerdaTipo::Material->value,
            'produto_id' => null,
            'material_id' => Material::factory(),
            'quantidade' => $this->faker->randomFloat(3, 1, 10),
            'motivo' => $this->faker->sentence(3),
            'observacoes' => null,
            'custo_total' => $this->faker->randomFloat(2, 5, 100),
            'user_id' => null,
        ];
    }
}
