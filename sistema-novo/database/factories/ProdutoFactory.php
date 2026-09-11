<?php

namespace Database\Factories;

use App\Models\Produto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produto>
 */
class ProdutoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoria_id' => null,
            'nome' => ucfirst($this->faker->unique()->words(3, true)),
            'sku' => strtoupper($this->faker->unique()->bothify('???-###')),
            'preco_venda' => $this->faker->randomFloat(2, 20, 200),
            'custo_referencia' => null,
            'estoque_minimo' => null,
            'imagem' => null,
            'ativo' => true,
        ];
    }
}
