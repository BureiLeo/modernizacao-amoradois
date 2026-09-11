<?php

namespace Database\Factories;

use App\Enums\FormaPagamento;
use App\Enums\StatusPagamento;
use App\Models\Pagamento;
use App\Models\Venda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pagamento>
 */
class PagamentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venda_id' => Venda::factory(),
            'forma' => FormaPagamento::Pix->value,
            'valor' => $this->faker->randomFloat(2, 10, 300),
            'status' => StatusPagamento::Pago->value,
            'pago_em' => now(),
            'observacoes' => null,
        ];
    }
}
