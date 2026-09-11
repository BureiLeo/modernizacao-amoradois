<?php

namespace Database\Factories;

use App\Models\Produto;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendaItem>
 */
class VendaItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qtd = $this->faker->numberBetween(1, 5);
        $preco = $this->faker->randomFloat(2, 20, 100);

        return [
            'venda_id' => Venda::factory(),
            'produto_id' => Produto::factory(),
            'produto_variacao_id' => null,
            'quantidade' => $qtd,
            'preco_unitario' => $preco,
            'custo_unitario_calculado' => 0,
            'lucro_unitario' => $preco,
            'subtotal' => $qtd * $preco,
        ];
    }
}
