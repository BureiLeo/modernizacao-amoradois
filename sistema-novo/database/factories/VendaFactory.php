<?php

namespace Database\Factories;

use App\Enums\VendaStatus;
use App\Models\Venda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venda>
 */
class VendaFactory extends Factory
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
            'cliente_id' => null,
            'user_id' => null,
            'subtotal' => 0,
            'desconto' => 0,
            'total' => 0,
            'custo_total' => 0,
            'lucro_total' => 0,
            'forma_pagamento' => $this->faker->randomElement(['pix', 'dinheiro', 'outro']),
            'pago' => true,
            'canal' => null,
            'observacoes' => null,
            'status' => VendaStatus::Concluida->value,
        ];
    }
}
