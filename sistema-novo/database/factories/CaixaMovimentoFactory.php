<?php

namespace Database\Factories;

use App\Enums\CaixaTipo;
use App\Models\CaixaMovimento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaixaMovimento>
 */
class CaixaMovimentoFactory extends Factory
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
            'tipo' => CaixaTipo::Ajuste->value,
            'ref_tabela' => null,
            'ref_id' => null,
            'descricao' => $this->faker->sentence(4),
            'valor' => $this->faker->randomFloat(2, -200, 200),
        ];
    }
}
