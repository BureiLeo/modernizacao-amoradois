<?php

namespace Database\Factories;

use App\Models\Compra;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compra>
 */
class CompraFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qtdEmbalagem = $this->faker->numberBetween(1, 10);
        $custoUnitario = $this->faker->randomFloat(6, 0.1, 20);

        return [
            'data' => $this->faker->dateTimeBetween('-6 months')->format('Y-m-d'),
            'material_id' => Material::factory(),
            'fornecedor_id' => null,
            'quantidade_embalagem' => $qtdEmbalagem,
            'quantidade' => $qtdEmbalagem,
            'valor' => round($qtdEmbalagem * $custoUnitario, 2),
            'custo_unitario' => $custoUnitario,
            'frete' => null,
            'desconto' => null,
            'forma_pagamento' => $this->faker->randomElement(['pix', 'dinheiro', 'outro']),
            'pago' => true,
            'anexo' => null,
            'observacoes' => null,
        ];
    }
}
