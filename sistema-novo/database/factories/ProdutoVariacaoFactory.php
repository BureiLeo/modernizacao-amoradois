<?php

namespace Database\Factories;

use App\Models\Produto;
use App\Models\ProdutoVariacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProdutoVariacao>
 */
class ProdutoVariacaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'produto_id' => Produto::factory(),
            'nome' => $this->faker->word(),
            'sku' => strtoupper($this->faker->unique()->bothify('VAR-###')),
            'preco_venda' => null,
            'custo_referencia' => null,
            'imagem' => null,
            'ativo' => true,
        ];
    }
}
